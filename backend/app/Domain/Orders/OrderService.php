<?php

namespace App\Domain\Orders;

use App\Domain\Ledger\InsufficientFunds;
use App\Domain\Ledger\Wallet;
use App\Models\Offering;
use App\Models\OfferingVariant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orders with immutable snapshots. Money always flows through the wallet:
 *  - pay from wallet: reserve the order total;
 *  - pay directly: the captured Razorpay payment credits the wallet and is reserved at once.
 * Fulfilment consumes the reservation; cancellation releases it.
 *
 * States: pending_payment → paid → fulfilling → completed; pending_payment|paid → cancelled.
 */
class OrderService
{
    private const TRANSITIONS = [
        'pending_payment' => ['paid', 'cancelled'],
        'paid' => ['fulfilling', 'completed', 'cancelled'],
        'fulfilling' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(private Wallet $wallet, private AuditLogger $audit) {}

    public function create(User $user, OfferingVariant $variant, int $quantity = 1, ?string $idempotencyKey = null, array $policyVersions = []): Order
    {
        if ($idempotencyKey && ($o = Order::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first())) {
            return $o;
        }
        $offering = Offering::with('translations', 'family')->findOrFail($variant->offering_id);
        if ($offering->status !== 'active' || ! $variant->active || $variant->price_minor === null) {
            throw new RuntimeException('This option cannot be purchased online.');
        }
        $t = $offering->t('en');

        return DB::transaction(function () use ($user, $variant, $offering, $t, $quantity, $idempotencyKey, $policyVersions) {
            $total = $variant->price_minor * $quantity;
            $order = Order::create(['user_id' => $user->id, 'status' => 'pending_payment', 'total_minor' => $total, 'currency' => 'INR',
                'idempotency_key' => $idempotencyKey, 'policy_versions' => $policyVersions]);
            $order->items()->create([
                'offering_id' => $offering->id, 'variant_id' => $variant->id, 'quantity' => $quantity,
                'unit_price_minor' => $variant->price_minor, 'total_minor' => $total, 'fulfilment_status' => 'pending',
                'offering_snapshot' => ['ref' => $offering->ref, 'slug' => $offering->slug, 'family' => $offering->family->code, 'title' => $t?->title,
                    'summary' => $t?->summary, 'includes' => $t?->includes, 'exclusions' => $t?->exclusions, 'disclaimer' => $t?->disclaimer],
                'variant_snapshot' => ['ref' => $variant->ref, 'name' => $variant->name, 'mode' => $variant->mode, 'duration_min' => $variant->duration_min,
                    'participants' => $variant->participants, 'urgent' => $variant->urgent, 'price_minor' => $variant->price_minor, 'currency' => $variant->currency],
            ]);

            return $order;
        });
    }

    /** @throws InsufficientFunds */
    public function payFromWallet(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'pending_payment') {
                return $o;
            }
            $r = $this->wallet->reserve($o->user_id, $o->total_minor, 'Order', $o->ref);
            $this->transition($o, 'paid', ['reservation_id' => $r->id]);

            return $o;
        });
    }

    /** Called inside PaymentService's capture transaction for purpose=order payments. */
    public function onPaymentCaptured(Payment $p): void
    {
        $o = Order::whereKey($p->order_id)->lockForUpdate()->first();
        if (! $o || $o->status !== 'pending_payment') {
            return; // already paid (e.g. from wallet) or cancelled: the money stays in the wallet
        }
        $r = $this->wallet->reserve($o->user_id, $o->total_minor, 'Order', $o->ref);
        $this->transition($o, 'paid', ['reservation_id' => $r->id]);
    }

    public function complete(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($o->status === 'completed') {
                return $o;
            }
            $r = $o->reservation()->firstOrFail();
            if ($r->heldMinor() > 0) {
                $this->wallet->consume($r, $r->heldMinor(), 'order-complete');
            }
            $this->transition($o, 'completed');
            $o->items()->update(['fulfilment_status' => 'completed']);

            return $o;
        });
    }

    public function cancel(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($o->status === 'cancelled') {
                return $o;
            }
            if ($o->reservation_id) {
                $this->wallet->release($o->reservation()->firstOrFail());
            }
            $this->transition($o, 'cancelled', [], $reason);

            return $o;
        });
    }

    private function transition(Order $o, string $to, array $extra = [], ?string $reason = null): void
    {
        if (! in_array($to, self::TRANSITIONS[$o->status] ?? [], true)) {
            throw new RuntimeException("Order cannot move from {$o->status} to {$to}.");
        }
        $from = $o->status;
        $o->update(['status' => $to] + $extra);
        $this->audit->record('order.'.$to, $o, array_filter(['from' => $from, 'reason' => $reason]));
    }
}
