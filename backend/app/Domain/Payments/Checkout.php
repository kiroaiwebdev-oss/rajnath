<?php

namespace App\Domain\Payments;

use App\Domain\Booking\Slots;
use App\Domain\Ledger\Ledger;
use App\Domain\Legal\LegalDocuments;
use App\Domain\Notify\Notifier;
use App\Domain\Palmistry\PalmReadings;
use App\Domain\Puja\PujaBookings;
use App\Domain\Reports\ReportJobs;
use App\Domain\Store\Store;
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
    public function __construct(private Razorpay $rzp, private Ledger $ledger, private AuditLogger $audit, private Coupons $coupons, private Invoices $invoices) {}

    public function startServiceOrder(User $user, OfferingVariant $variant, bool $useWallet, string $idempotencyKey, array $extra = [], ?string $couponCode = null): array
    {
        $o = Offering::with(['translations', 'family'])->findOrFail($variant->offering_id);
        if ($o->status !== 'active' || ! $variant->active || $variant->price_minor === null || $variant->price_minor <= 0) {
            throw ValidationException::withMessages(['variant' => 'This option cannot be bought online yet.']);
        }
        if (Slots::bookable($variant) && ! isset($extra['booking_ref'])) {
            throw ValidationException::withMessages(['variant' => 'Choose a consultation time first.']);
        }
        $snapshot = [
            'offering' => ['ref' => $o->ref, 'slug' => $o->slug, 'title' => $o->t('en')?->title, 'family' => $o->family->code],
            'variant' => ['ref' => $variant->ref, 'name' => $variant->name, 'mode' => $variant->mode, 'duration_min' => $variant->duration_min, 'participants' => $variant->participants, 'urgent' => $variant->urgent],
            'price_minor' => $variant->price_minor, 'currency' => 'INR', 'terms_version' => LegalDocuments::consentVersion(),
            'disclaimer' => $o->t('en')?->disclaimer,
        ] + $extra;

        return $this->start($user, 'service', $variant->price_minor, $snapshot, $useWallet, $idempotencyKey, $couponCode);
    }

    public function startProductOrder(User $user, array $snapshot, string $idempotencyKey, ?string $couponCode = null): array
    {
        return $this->start($user, 'product', (int) $snapshot['price_minor'], $snapshot, false, $idempotencyKey, $couponCode);
    }

    public function startRecharge(User $user, object $package, string $idempotencyKey): array
    {
        return $this->start($user, 'recharge', (int) $package->amount_minor, ['package_id' => $package->id, 'amount_minor' => (int) $package->amount_minor, 'bonus_minor' => (int) $package->bonus_minor], false, $idempotencyKey);
    }

    private function start(User $user, string $kind, int $amount, array $snapshot, bool $useWallet, string $key, ?string $couponCode = null): array
    {
        $key = 'u'.$user->id.':'.$key;
        $existing = DB::table('orders')->where('idempotency_key', $key)->first();
        if ($existing) {
            return $this->checkoutPayload($existing);
        }

        $couponCode = $kind === 'recharge' ? null : (Coupons::normalise($couponCode) ?: null);
        $order = DB::transaction(function () use ($user, $kind, $amount, $snapshot, $useWallet, $key, $couponCode) {
            $list = $amount;
            $coupon = null;
            $discount = 0;
            if ($couponCode !== null) {
                $scope = $kind === 'product' ? 'product' : ($snapshot['offering']['family'] ?? 'service');
                ['coupon' => $coupon, 'discount' => $discount] = $this->coupons->quote($user, $couponCode, $scope, $amount, lock: true);
                if ($kind === 'product') {
                    $discount = min($discount, $amount - 100);   // gem orders always go through the gateway (delivery address checks)
                }
                $amount -= $discount;
                $snapshot['coupon'] = ['code' => $coupon->code, 'discount_minor' => $discount, 'description' => Coupons::describe($coupon)];
            }
            $walletApplied = 0;
            if ($useWallet && $kind === 'service') {
                $this->ledger->wallet($user->id);
                $walletApplied = min($amount, $this->ledger->walletBalance($user->id));
            }
            $id = DB::table('orders')->insertGetId([
                'ref' => (string) Str::ulid(), 'user_id' => $user->id, 'kind' => $kind, 'status' => 'pending_payment',
                'subtotal_minor' => $amount, 'list_minor' => $list, 'discount_minor' => $discount, 'coupon_id' => $coupon?->id,
                'wallet_applied_minor' => $walletApplied, 'payable_minor' => $amount - $walletApplied,
                'currency' => 'INR', 'snapshot' => json_encode($snapshot), 'idempotency_key' => $key, 'created_at' => now(), 'updated_at' => now(),
            ]);

            if ($coupon) {
                DB::table('coupon_redemptions')->insert(['coupon_id' => $coupon->id, 'order_id' => $id, 'user_id' => $user->id, 'discount_minor' => $discount, 'created_at' => now()]);
            }

            return DB::table('orders')->find($id);
        });

        if ((int) $order->payable_minor === 0) {
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

    public static function label(string $kind, array $snap): string
    {
        return match ($kind) {
            'recharge' => 'Wallet recharge',
            'product' => $snap['product']['name'].' × '.$snap['quantity'],
            default => $snap['offering']['title'].' — '.$snap['variant']['name'],
        };
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
            // GST included in the price is a liability, not revenue (only when the owner has turned GST on).
            $tax = $this->invoices->enabled() ? Invoices::taxIncluded((int) $order->subtotal_minor, $this->invoices->rateFor($order->kind)) : 0;
            $lines[] = [$this->ledger->account('revenue', 'revenue'), 'credit', (int) $order->subtotal_minor - $tax];
            if ($tax > 0) {
                $lines[] = [$this->ledger->account('gst_output', 'liability'), 'credit', $tax];
            }
            DB::table('orders')->where('id', $order->id)->update(['tax_minor' => $tax]);
            $order->tax_minor = $tax;
        }
        $kind = ['recharge' => 'wallet_recharge', 'product' => 'product_sale'][$order->kind] ?? 'service_purchase';
        $memo = $snap['offering']['title'] ?? ($snap['product']['name'] ?? 'Wallet recharge');
        $lines = array_values(array_filter($lines, fn ($l) => $l[2] > 0));   // a fully discounted order moves no money
        if ($lines) {
            $this->ledger->post($kind, 'order-paid:'.$order->ref, $lines, $memo, 'order', $order->id);
        }
        DB::table('orders')->where('id', $order->id)->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
        if (in_array($order->kind, ['service', 'product'], true)) {
            $this->invoices->issueForOrder(DB::table('orders')->find($order->id));
        }
        $this->audit->record('order.paid', null, ['order' => $order->ref, 'payment' => $paymentId], null, 'system');
        $this->fulfil(DB::table('orders')->find($order->id), $snap);
    }

    /** Starts post-payment work for the item bought. Runs inside the capture transaction. */
    private function fulfil(object $order, array $snap): void
    {
        if ($order->kind === 'product') {
            app(Store::class)->onPaid($order);

            return;
        }
        if ($order->kind !== 'service') {
            return;
        }
        $slug = $snap['offering']['slug'] ?? null;
        $family = $snap['offering']['family'] ?? null;
        if ($slug === PalmReadings::OFFERING_SLUG) {
            app(PalmReadings::class)->onOrderPaid($order);
        } elseif (isset($snap['booking_ref'])) {
            app(Slots::class)->onOrderPaid($order);
        } elseif (in_array($family, ['puja', 'chadhava'], true)) {
            app(PujaBookings::class)->onOrderPaid($order, $snap);
        } elseif (in_array($family, ['report', 'bundle', 'written_question'], true)) {
            app(ReportJobs::class)->onOrderPaid($order, $snap);
        }
        $this->paidNotice($order, $snap);
    }

    /** "Payment received" with the customer's next step. Slot bookings send their own confirmation. */
    private function paidNotice(object $order, array $snap): void
    {
        if (isset($snap['booking_ref'])) {
            return;
        }
        $next = self::next($order);
        $step = match ($next['type'] ?? null) {
            'palm_upload' => ' Next: upload photos of both palms.',
            'puja_details' => ' Next: add the sankalp details (names, gotra) for the puja.',
            'report_details' => ' Next: choose the birth details for your report.',
            'shipment' => ' We will email you when it is dispatched.',
            default => '',
        };
        app(Notifier::class)->send((int) $order->user_id, 'order', 'Payment received',
            'We received ₹'.number_format($order->subtotal_minor / 100, 2).' for '.self::label($order->kind, $snap).'.'.$step, $order->ref, route('account.receipt', $order->ref));
    }

    /** The customer's next step for a paid order, used by the web receipt, the app and notices. */
    public static function next(object $order): ?array
    {
        $find = fn (string $table) => DB::table($table)->where('order_id', $order->id)->first();
        if ($order->kind === 'product') {
            return ['type' => 'shipment', 'ref' => $order->ref];
        }
        if ($b = $find('bookings')) {
            return ['type' => 'booking', 'ref' => $b->ref];
        }
        if (($p = $find('palm_readings')) && in_array($p->status, ['awaiting_photos', 'needs_new_photos'], true)) {
            return ['type' => 'palm_upload', 'ref' => $p->ref];
        }
        if (($p = $find('puja_bookings')) && $p->status === 'awaiting_details') {
            return ['type' => 'puja_details', 'ref' => $p->ref];
        }
        if (($j = $find('report_jobs')) && $j->status === 'awaiting_details') {
            return ['type' => 'report_details', 'ref' => $j->ref];
        }

        return null;
    }

    public function markFailed(string $providerOrderId, ?string $paymentId, string $error): void
    {
        DB::table('payments')->where('provider_order_id', $providerOrderId)->where('status', '!=', 'captured')
            ->update(['status' => 'failed', 'provider_payment_id' => DB::raw('COALESCE(provider_payment_id, '.DB::getPdo()->quote((string) $paymentId).')'), 'error' => mb_substr($error, 0, 300), 'updated_at' => now()]);
    }

    public function cancel(object $order): void
    {
        $n = DB::table('orders')->where('id', $order->id)->where('status', 'pending_payment')->update(['status' => 'cancelled', 'updated_at' => now()]);
        if ($n && $order->kind === 'product') {
            app(Store::class)->release($order->id);
        }
        if ($n && $order->kind === 'service') {
            app(Slots::class)->onOrderCancelled($order);
        }
    }
}
