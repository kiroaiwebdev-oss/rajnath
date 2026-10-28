<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\Ledger;
use App\Models\Offering;
use App\Models\OfferingVariant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Order lifecycle:
 *   pending_payment ──capture──▶ paid ──refund──▶ partially_refunded / refunded
 *          └──cancel/fail──▶ cancelled / failed
 * Captures are applied exactly once, whether they arrive via the browser callback, the webhook
 * or reconciliation polling (idempotency key = provider payment id). Amount mismatches are
 * never accepted.
 */
class Checkout
{
    public function __construct(private Razorpay $rzp, private Ledger $ledger, private AuditLogger $audit) {}

    public function startServiceOrder(User $user, OfferingVariant $variant, bool $useWallet, string $idempotencyKey): array
    {
        $o = Offering::with(['translations', 'family'])->findOrFail($variant->offering_id);
        if ($o->status !== 'active' || ! $variant->active || $variant->price_minor === null || $variant->price_minor <= 0) {
            throw ValidationException::withMessages(['variant' => 'This option cannot be bought online yet.']);
        }
        $snapshot = [
            'offering' => ['ref' => $o->ref, 'slug' => $o->slug, 'title' => $o->t('en')?->title, 'family' => $o->family->code],
            'variant' => ['ref' => $variant->ref, 'name' => $variant->name, 'mode' => $variant->mode, 'duration_min' => $variant->duration_min, 'participants' => $variant->participants, 'urgent' => $variant->urgent],
            'price_minor' => $variant->price_minor, 'currency' => 'INR', 'terms_version' => \App\Domain\Identity\CustomerAuth::TERMS_VERSION,
            'disclaimer' => $o->t('en')?->disclaimer,
        ];

        return $this->start($user, 'service', $variant->price_minor, $snapshot, $useWallet, $idempotencyKey);
    }

    public function startRecharge(User $user, object $package, string $idempotencyKey): array
    {
        return $this->start($user, 'recharge', (int) $package->amount_minor, ['package_id' => $package->id, 'amount_minor' => (int) $package->amount_minor, 'bonus_minor' => (int) $package->bonus_minor], false, $idempotencyKey);
    }

    private function start(User $user, string $kind, int $amount, array $snapshot, bool $useWallet, string $key): array
    {
        $key = 'u'.$user->id.':'.$key;
        $existing = DB::table('orders')->where('idempotency_key', $key)->first();
        if ($existing) {
            return $this->checkoutPayload($existing);
        }

        $order = DB::transaction(function () use ($user, $kind, $amount, $snapshot, $useWallet, $key) {
            $walletApplied = 0;
            if ($useWallet && $kind === 'service') {
                $this->ledger->wallet($user->id);
                $walletApplied = min($amount, $this->ledger->walletBalance($user->id));
            }
            $id = DB::table('orders')->insertGetId([
                'ref' => (string) Str::ulid(), 'user_id' => $user->id, 'kind' => $kind, 'status' => 'pending_payment',
                'subtotal_minor' => $amount, 'wallet_applied_minor' => $walletApplied, 'payable_minor' => $amount - $walletApplied,
                'currency' => 'INR', 'snapshot' => json_encode($snapshot), 'idempotency_key' => $key, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('orders')->find($id);
        });

        if ($order->payable_minor === 0) {
            // Fully paid from wallet: consume immediately.
            $this->markPaid($order, null);

            return ['order' => DB::table('orders')->find($order->id), 'razorpay' => null];
        }
        $rzpOrder = $this->rzp->createOrder((int) $order->payable_minor, $order->ref, ['order_ref' => $order->ref]);
        DB::table('payments')->insert([
            'ref' => (string) Str::ulid(), 'order_id' => $order->id, 'provider_order_id' => $rzpOrder['id'],
            'amount_minor' => $order->payable_minor, 'status' => 'created', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->record('order.created', null, ['order' => $order->ref, 'kind' => $kind, 'amount' => $amount], $user);

        return $this->checkoutPayload($order);
    }

    public function checkoutPayload(object $order): array
    {
        $p = DB::table('payments')->where('order_id', $order->id)->orderByDesc('id')->first();

        return ['order' => $order, 'razorpay' => $p && $order->status === 'pending_payment' ? ['key' => $this->rzp->keyId(), 'order_id' => $p->provider_order_id, 'amount' => (int) $p->amount_minor, 'currency' => 'INR'] : null];
    }

    /** Apply a captured payment once. Returns a short result string. */
    public function applyCapture(string $providerOrderId, string $providerPaymentId, int $amountMinor, ?string $method = null): string
    {
        return DB::transaction(function () use ($providerOrderId, $providerPaymentId, $amountMinor, $method) {
            $p = DB::table('payments')->where('provider_order_id', $providerOrderId)->lockForUpdate()->first();
            if (! $p) {
                return 'unknown_order';
            }
            $order = DB::table('orders')->where('id', $p->order_id)->lockForUpdate()->first();
            if ($p->status === 'captured') {
                return $p->provider_payment_id === $providerPaymentId ? 'duplicate' : 'second_payment';
            }
            if ($amountMinor !== (int) $p->amount_minor) {
                DB::table('payments')->where('id', $p->id)->update(['status' => 'ambiguous', 'error' => "Amount mismatch: got $amountMinor", 'updated_at' => now()]);
                DB::table('reconciliation_findings')->insert(['check' => 'amount_mismatch', 'severity' => 'critical', 'subject' => 'order:'.$order->ref, 'detail' => "Expected {$p->amount_minor}, provider reported $amountMinor for $providerPaymentId.", 'created_at' => now(), 'updated_at' => now()]);

                return 'amount_mismatch';
            }
            DB::table('payments')->where('id', $p->id)->update(['status' => 'captured', 'provider_payment_id' => $providerPaymentId, 'method' => $method, 'captured_at' => now(), 'updated_at' => now()]);
            if ($order->status !== 'pending_payment') {
                // Paid after the order was cancelled: money must go back. Recorded for finance.
                DB::table('reconciliation_findings')->insert(['check' => 'capture_after_cancel', 'severity' => 'warning', 'subject' => 'order:'.$order->ref, 'detail' => "Payment $providerPaymentId captured while order was {$order->status}. Refund required.", 'created_at' => now(), 'updated_at' => now()]);
                $clearing = $this->ledger->account('gateway_clearing', 'asset');
                $this->ledger->post('payment_capture_orphan', 'cap:'.$providerPaymentId, [[$clearing, 'debit', $amountMinor], [$this->ledger->account('refunds_payable', 'liability'), 'credit', $amountMinor]], 'Capture after cancel', 'order', $order->id);

                return 'captured_after_cancel';
            }
            $this->markPaid($order, $providerPaymentId);

            return 'applied';
        });
    }

    private function markPaid(object $order, ?string $paymentId): void
    {
        $snap = json_decode($order->snapshot, true);
        $clearing = $this->ledger->account('gateway_clearing', 'asset');
        $wallet = $this->ledger->wallet($order->user_id);
        $lines = [];
        if ($order->kind === 'recharge') {
            $lines[] = [$clearing, 'debit', (int) $order->payable_minor];
            $lines[] = [$wallet, 'credit', (int) $order->payable_minor];
            if (($snap['bonus_minor'] ?? 0) > 0) {
                $lines[] = [$this->ledger->account('bonus_expense', 'expense'), 'debit', (int) $snap['bonus_minor']];
                $lines[] = [$wallet, 'credit', (int) $snap['bonus_minor']];
            }
        } else {
            if ($order->payable_minor > 0) {
                $lines[] = [$clearing, 'debit', (int) $order->payable_minor];
            }
            if ($order->wallet_applied_minor > 0) {
                $lines[] = [$wallet, 'debit', (int) $order->wallet_applied_minor];
            }
            $lines[] = [$this->ledger->account('revenue', 'revenue'), 'credit', (int) $order->subtotal_minor];
        }
        $this->ledger->post($order->kind === 'recharge' ? 'wallet_recharge' : 'service_purchase', 'order-paid:'.$order->ref, $lines, $snap['offering']['title'] ?? 'Wallet recharge', 'order', $order->id);
        DB::table('orders')->where('id', $order->id)->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
        $this->audit->record('order.paid', null, ['order' => $order->ref, 'payment' => $paymentId], null, 'system');
    }

    public function markFailed(string $providerOrderId, ?string $paymentId, string $error): void
    {
        DB::table('payments')->where('provider_order_id', $providerOrderId)->where('status', '!=', 'captured')
            ->update(['status' => 'failed', 'provider_payment_id' => DB::raw('COALESCE(provider_payment_id, '.DB::getPdo()->quote((string) $paymentId).')'), 'error' => mb_substr($error, 0, 300), 'updated_at' => now()]);
    }

    public function cancel(object $order): void
    {
        DB::table('orders')->where('id', $order->id)->where('status', 'pending_payment')->update(['status' => 'cancelled', 'updated_at' => now()]);
    }
}
