<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\InsufficientFunds;
use App\Domain\Ledger\Ledger;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Maker/checker refunds to the original payment method.
 *
 *   requested ─approve→ processing ─Razorpay processed→ processed
 *       │                   └──────Razorpay failed────→ failed (money returned to the wallet)
 *       └─reject→ rejected
 *
 * Only unspent paid wallet money can be refunded: approval moves it from the customer's cash
 * balance into "refunds in flight" before Razorpay is called, so it cannot be spent twice.
 */
class RefundService
{
    public function __construct(private RazorpayClient $rzp, private Ledger $ledger, private AuditLogger $audit) {}

    public function request(Payment $payment, int $amount, string $reason, User $staff): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $staff) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($amount <= 0 || $amount > $p->refundableMinor()) {
                throw new RuntimeException('The refund amount is more than what can be refunded for this payment ('.Money::format($p->refundableMinor()).').');
            }
            $cash = $this->ledger->balance(Ledger::customer('customer_cash', $p->user_id));
            if ($amount > $cash) {
                throw new RuntimeException('The customer has only '.Money::format($cash).' of unspent paid balance. Cancel or release their bookings first, or refund less.');
            }
            $r = Refund::create(['payment_id' => $p->id, 'user_id' => $p->user_id, 'amount_minor' => $amount, 'reason' => $reason,
                'status' => 'requested', 'requested_by' => $staff->id]);
            $this->audit->record('refund.requested', $r, ['payment' => $p->ref, 'amount_minor' => $amount], $staff);

            return $r;
        });
    }

    public function reject(Refund $refund, User $checker, string $note): Refund
    {
        return DB::transaction(function () use ($refund, $checker, $note) {
            $r = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'requested') {
                throw new RuntimeException('Only requested refunds can be rejected.');
            }
            $r->update(['status' => 'rejected', 'decided_by' => $checker->id, 'decision_note' => $note, 'decided_at' => now()]);
            $this->audit->record('refund.rejected', $r, [], $checker);

            return $r;
        });
    }

    public function approve(Refund $refund, User $checker, ?string $note = null): Refund
    {
        $r = DB::transaction(function () use ($refund, $checker, $note) {
            $r = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'requested') {
                throw new RuntimeException('This refund has already been decided.');
            }
            $self = $r->requested_by === $checker->id;
            if ($self && ! $checker->hasRole('owner')) {
                throw new RuntimeException('A different person must approve a refund you requested.');
            }
            try {
                $this->ledger->post('refund_hold', "refund:{$r->id}:hold", [
                    [Ledger::customer('customer_cash', $r->user_id), 'debit', $r->amount_minor],
                    ['refunds_in_flight', 'credit', $r->amount_minor],
                ], 'Refund to original payment method', $r, $checker->id);
            } catch (InsufficientFunds) {
                throw new RuntimeException('The customer no longer has enough unspent paid balance for this refund.');
            }
            $r->update(['status' => 'processing', 'decided_by' => $checker->id, 'decision_note' => $note, 'decided_at' => now()]);
            $this->audit->record('refund.approved', $r, ['self_approved' => $self, 'amount_minor' => $r->amount_minor], $checker);

            return $r;
        });

        return $this->submit($r);
    }

    /** Sends a processing refund to Razorpay once. Safe to retry: checks for an existing refund first. */
    public function submit(Refund $r): Refund
    {
        $r->refresh();
        if ($r->status !== 'processing' || $r->provider_refund_id) {
            return $r;
        }
        $p = $r->payment;
        try {
            $existing = collect($this->rzp->fetchPaymentRefunds($p->provider_payment_id))->firstWhere('receipt', $r->ref);
            $entity = $existing ?? $this->rzp->refund($p->provider_payment_id, $r->amount_minor, $r->ref, ['refund_ref' => $r->ref]);
        } catch (RazorpayException $e) {
            if ($e->transient) {
                $r->update(['failure_reason' => 'Waiting to retry: '.mb_substr($e->getMessage(), 0, 200)]);

                return $r; // the scheduler retries
            }
            $this->fail($r, mb_substr($e->getMessage(), 0, 255));

            return $r->refresh();
        }
        $r->update(['provider_refund_id' => $entity['id'] ?? null, 'provider_status' => $entity['status'] ?? null, 'failure_reason' => null]);
        $this->applyProvider($r, $entity);

        return $r->refresh();
    }

    /** Applies a Razorpay refund entity (API response or verified webhook). Idempotent. */
    public function applyProvider(Refund $refund, array $entity): void
    {
        $status = $entity['status'] ?? null;
        if ($status === 'processed') {
            DB::transaction(function () use ($refund, $entity) {
                $r = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
                if ($r->status !== 'processing') {
                    return;
                }
                if ((int) ($entity['amount'] ?? -1) !== $r->amount_minor) {
                    Findings::raise('refund.amount_mismatch', 'critical', $r, ['provider_amount' => $entity['amount'] ?? null]);

                    return;
                }
                $this->ledger->post('refund_paid', "refund:{$r->id}:paid", [
                    ['refunds_in_flight', 'debit', $r->amount_minor], ['gateway_clearing', 'credit', $r->amount_minor],
                ], 'Refund paid out by Razorpay', $r);
                $r->update(['status' => 'processed', 'provider_status' => 'processed', 'processed_at' => now()]);
                Payment::whereKey($r->payment_id)->increment('refunded_minor', $r->amount_minor);
                $this->audit->record('refund.processed', $r, [], null, 'system');
            });
        } elseif ($status === 'failed') {
            $this->fail($refund, 'Razorpay could not process the refund.');
        } elseif ($status) {
            $refund->update(['provider_status' => $status]);
        }
    }

    private function fail(Refund $refund, string $why): void
    {
        DB::transaction(function () use ($refund, $why) {
            $r = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'processing') {
                return;
            }
            $this->ledger->post('refund_reversed', "refund:{$r->id}:reverse", [
                ['refunds_in_flight', 'debit', $r->amount_minor], [Ledger::customer('customer_cash', $r->user_id), 'credit', $r->amount_minor],
            ], 'Refund failed; money returned to wallet', $r);
            $r->update(['status' => 'failed', 'failure_reason' => $why]);
            Findings::raise('refund.failed', 'warning', $r, ['why' => $why]);
            $this->audit->record('refund.failed', $r, ['why' => $why], null, 'system');
        });
    }

    public function poll(Refund $r): Refund
    {
        if ($r->status !== 'processing') {
            return $r;
        }
        if (! $r->provider_refund_id) {
            return $this->submit($r);
        }
        try {
            $this->applyProvider($r, $this->rzp->fetchRefund($r->provider_refund_id));
        } catch (RazorpayException) {
            // try again on the next run
        }

        return $r->refresh();
    }
}
