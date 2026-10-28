<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\Wallet;
use App\Domain\Orders\OrderService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RechargePackage;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Payment lifecycle. One Payment row = one Razorpay order. The only way money enters the
 * platform is settle(), which re-checks the payment with Razorpay's API (amount, currency,
 * order id, status) before crediting the wallet. Client callbacks and webhooks only trigger
 * that check; neither is trusted on its own.
 *
 * States: created → pending → captured | failed | cancelled | ambiguous.
 * "failed" and "cancelled" are not terminal for late captures: if Razorpay later reports a
 * capture for the same order, the money is credited and a finding is raised.
 */
class PaymentService
{
    public function __construct(
        private RazorpayClient $rzp,
        private RazorpayConfig $cfg,
        private Wallet $wallet,
        private AuditLogger $audit,
    ) {}

    public function startRecharge(User $user, RechargePackage $package, ?string $idempotencyKey = null): Payment
    {
        if (! $package->active) {
            throw new PaymentUnavailable('This recharge option is no longer available.');
        }

        return $this->start($user, 'wallet_recharge', $package->amount_minor, $package->bonus_minor, [
            'package_ref' => $package->ref, 'name' => $package->name, 'amount_minor' => $package->amount_minor, 'bonus_minor' => $package->bonus_minor,
        ], $idempotencyKey, recharge: $package);
    }

    public function startOrderPayment(User $user, Order $order, ?string $idempotencyKey = null): Payment
    {
        if ($order->user_id !== $user->id || $order->status !== 'pending_payment') {
            throw new PaymentUnavailable('This order cannot be paid.');
        }

        return $this->start($user, 'order', $order->total_minor, 0, ['order_ref' => $order->ref, 'amount_minor' => $order->total_minor], $idempotencyKey, order: $order);
    }

    private function start(User $user, string $purpose, int $amount, int $bonus, array $snapshot, ?string $key, ?RechargePackage $recharge = null, ?Order $order = null): Payment
    {
        if (! $this->cfg->ready()) {
            throw new PaymentUnavailable('Online payment is not available right now. Please try again later or contact us.');
        }
        if ($amount < 100) {
            throw new PaymentUnavailable('The minimum online payment is ₹1.');
        }
        if ($this->cfg->mode() === 'live' && $amount > $this->cfg->liveLimitMinor()) {
            throw new PaymentUnavailable('This amount is above the current online payment limit. Please contact us.');
        }
        if ($key && ($existing = Payment::where('user_id', $user->id)->where('idempotency_key', $key)->first())) {
            return $existing;
        }

        $payment = Payment::create([
            'user_id' => $user->id, 'purpose' => $purpose, 'order_id' => $order?->id, 'recharge_package_id' => $recharge?->id,
            'snapshot' => $snapshot, 'amount_minor' => $amount, 'bonus_minor' => $bonus, 'currency' => 'INR',
            'provider' => 'razorpay', 'provider_mode' => $this->cfg->mode(), 'status' => 'created', 'idempotency_key' => $key,
        ]);
        try {
            $rz = $this->rzp->createOrder($amount, $payment->ref, ['payment_ref' => $payment->ref, 'purpose' => $purpose]);
        } catch (RazorpayException $e) {
            $payment->update(['status' => 'failed', 'status_reason' => 'Could not start payment with Razorpay.']);
            Log::warning('razorpay.create_order_failed', ['payment' => $payment->ref, 'error' => $e->getMessage()]);
            throw new PaymentUnavailable('Could not start the payment. Please try again in a moment.');
        }
        if ((int) ($rz['amount'] ?? 0) !== $amount || ($rz['currency'] ?? '') !== 'INR' || empty($rz['id'])) {
            $payment->update(['status' => 'failed', 'status_reason' => 'Razorpay returned an unexpected order.']);
            throw new PaymentUnavailable('Could not start the payment. Please try again.');
        }
        $payment->update(['provider_order_id' => $rz['id']]);
        $this->audit->record('payment.created', $payment, ['purpose' => $purpose, 'amount_minor' => $amount, 'mode' => $payment->provider_mode], $user);

        return $payment;
    }

    /** Data for Razorpay Checkout (web JS or Flutter SDK). Contains no secrets. */
    public function checkoutOptions(Payment $p, User $u): array
    {
        return [
            'key' => $this->cfg->keyId(), 'order_id' => $p->provider_order_id, 'amount' => $p->amount_minor, 'currency' => $p->currency,
            'name' => config('brand')['brand.name'], 'description' => $p->snapshot['name'] ?? $p->label(),
            'prefill' => array_filter(['name' => $u->name, 'email' => $u->email, 'contact' => $u->phone_e164]),
            'notes' => ['payment_ref' => $p->ref], 'theme' => ['color' => '#141a3c'],
        ];
    }

    /** Called with the values Razorpay Checkout returns to the browser/app. */
    public function confirmFromCheckout(Payment $p, string $providerOrderId, string $providerPaymentId, string $signature): Payment
    {
        if ($providerOrderId !== $p->provider_order_id || ! $this->rzp->checkoutSignatureValid($p->provider_order_id, $providerPaymentId, $signature)) {
            $this->audit->record('payment.bad_checkout_signature', $p, []);
            throw new RuntimeException('Payment confirmation could not be verified.');
        }
        if ($p->status === 'created') {
            $p->update(['status' => 'pending']);
        }
        try {
            $this->settle($p, $this->rzp->fetchPayment($providerPaymentId));
        } catch (RazorpayException $e) {
            // Signature was valid, so a payment exists; the reconcile job will finish this.
            $p->update(['status_reason' => 'Waiting for Razorpay confirmation.']);
        }

        return $p->refresh();
    }

    /**
     * Applies an authoritative Razorpay payment entity (from the API, or from a verified webhook
     * which we re-fetch if it claims a capture). Safe to call any number of times in any order.
     */
    public function settle(Payment $p, array $entity, bool $trusted = true): void
    {
        $status = (string) ($entity['status'] ?? '');
        if (($entity['order_id'] ?? null) !== $p->provider_order_id) {
            $this->markAmbiguous($p, 'Razorpay payment belongs to a different order.', $entity);

            return;
        }
        if ($status === 'authorized') {
            try {
                $entity = $this->rzp->capture($entity['id'], $p->amount_minor);
                $status = (string) ($entity['status'] ?? '');
            } catch (RazorpayException $e) {
                $p->update(['status' => 'pending', 'status_reason' => 'Payment authorised; capture pending.']);

                return;
            }
        }
        if ($status === 'captured') {
            if ((int) ($entity['amount'] ?? -1) !== $p->amount_minor || ($entity['currency'] ?? '') !== $p->currency) {
                $this->markAmbiguous($p, 'Captured amount or currency does not match.', $entity);

                return;
            }
            $this->markCaptured($p, $entity);

            return;
        }
        if ($status === 'failed') {
            DB::transaction(function () use ($p, $entity) {
                $locked = Payment::whereKey($p->id)->lockForUpdate()->first();
                if (in_array($locked->status, ['created', 'pending', 'ambiguous'], true)) {
                    $locked->update(['status' => 'failed', 'status_reason' => mb_substr((string) ($entity['error_description'] ?? 'Payment failed.'), 0, 255), 'method' => $entity['method'] ?? null]);
                }
            });
        }
    }

    private function markCaptured(Payment $p, array $entity): void
    {
        DB::transaction(function () use ($p, $entity) {
            $locked = Payment::whereKey($p->id)->lockForUpdate()->first();
            if ($locked->status === 'captured') {
                return; // already applied (duplicate webhook / callback)
            }
            $previous = $locked->status;
            $tx = $this->wallet->creditPayment($locked->user_id, $locked->id, $locked->amount_minor, $locked->bonus_minor, $locked);
            $locked->update([
                'status' => 'captured', 'status_reason' => null, 'provider_payment_id' => $entity['id'] ?? $locked->provider_payment_id,
                'method' => $entity['method'] ?? null, 'captured_at' => now(), 'credit_transaction_id' => $tx->id,
            ]);
            if (in_array($previous, ['failed', 'cancelled'], true)) {
                Findings::raise('payment.late_capture', 'info', $locked, ['previous' => $previous]);
            }
            if ($locked->purpose === 'order' && $locked->order_id) {
                app(OrderService::class)->onPaymentCaptured($locked);
            }
            $this->audit->record('payment.captured', $locked, ['amount_minor' => $locked->amount_minor, 'previous' => $previous], null, 'system');
        });
    }

    private function markAmbiguous(Payment $p, string $why, array $entity): void
    {
        $p->update(['status' => $p->status === 'captured' ? 'captured' : 'ambiguous', 'status_reason' => $why]);
        Findings::raise('payment.mismatch', 'critical', $p, ['why' => $why, 'provider_payment_id' => $entity['id'] ?? null, 'amount' => $entity['amount'] ?? null, 'currency' => $entity['currency'] ?? null]);
    }

    /** Server-side status check against Razorpay; used by the scheduler and the admin "Re-check" button. */
    public function reconcile(Payment $p): Payment
    {
        if ($p->status === 'captured' || ! $p->provider_order_id) {
            return $p;
        }
        try {
            $items = $this->rzp->fetchOrderPayments($p->provider_order_id);
        } catch (RazorpayException $e) {
            $attempts = $p->check_attempts + 1;
            $p->update(['check_attempts' => $attempts, 'last_checked_at' => now()]
                + ($attempts >= 6 ? ['status' => 'ambiguous', 'status_reason' => 'Razorpay could not be reached to confirm this payment.'] : []));
            if ($attempts >= 6) {
                Findings::raise('payment.unconfirmed', 'warning', $p, ['error' => $e->getMessage()]);
            }

            return $p->refresh();
        }
        $p->update(['check_attempts' => $p->check_attempts + 1, 'last_checked_at' => now()]);
        $pick = collect($items)->firstWhere('status', 'captured') ?? collect($items)->firstWhere('status', 'authorized');
        if ($pick) {
            $this->settle($p, $pick);
        } elseif ($items !== [] && collect($items)->every(fn ($i) => $i['status'] === 'failed')) {
            $this->settle($p, end($items));
        } elseif ($items === [] && $p->created_at->lt(now()->subDay()) && in_array($p->status, ['created', 'pending', 'failed', 'ambiguous'], true)) {
            $p->update(['status' => 'cancelled', 'status_reason' => 'No payment was made within 24 hours.']);
        }
        if ($p->refresh()->status === 'failed' && $p->created_at->lt(now()->subDay())) {
            $p->update(['status' => 'cancelled']);
        }

        return $p->refresh();
    }
}
