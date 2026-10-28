<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\Ledger;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Maker/checker refunds: a staff member with refunds.request creates the request; a different
 * person with refunds.approve approves it. Approved refunds go back to the original payment
 * (Razorpay) or to the customer's wallet. Total refunded can never exceed what was paid.
 */
class Refunds
{
    public function __construct(private Ledger $ledger, private Razorpay $rzp, private AuditLogger $audit) {}

    public function request(object $order, int $amount, string $destination, string $reason, User $by): object
    {
        if (! in_array($order->status, ['paid', 'partially_refunded'], true)) {
            throw ValidationException::withMessages(['amount' => 'Only paid orders can be refunded.']);
        }
        if ($order->kind === 'recharge' && $destination === 'wallet') {
            throw ValidationException::withMessages(['destination' => 'Recharges can only be refunded to the original payment method.']);
        }
        $pending = (int) DB::table('refunds')->where('order_id', $order->id)->whereIn('status', ['requested', 'approved', 'processing', 'processed'])->sum('amount_minor');
        $max = (int) $order->subtotal_minor - $pending;
        if ($amount <= 0 || $amount > $max) {
            throw ValidationException::withMessages(['amount' => 'Amount must be between ₹0.01 and the refundable balance ('.\App\Support\Money::format($max).').']);
        }
        if ($destination === 'source' && $amount > (int) $order->payable_minor - $this->sourceRefunded($order)) {
            throw ValidationException::withMessages(['destination' => 'Card/UPI refund cannot exceed the amount paid online; refund the rest to wallet.']);
        }
        $id = DB::table('refunds')->insertGetId([
            'ref' => (string) Str::ulid(), 'order_id' => $order->id,
            'payment_id' => DB::table('payments')->where('order_id', $order->id)->where('status', 'captured')->value('id'),
            'amount_minor' => $amount, 'destination' => $destination, 'reason' => $reason, 'status' => 'requested',
            'requested_by' => $by->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $r = DB::table('refunds')->find($id);
        $this->audit->record('refund.requested', null, ['refund' => $r->ref, 'order' => $order->ref, 'amount' => $amount, 'destination' => $destination], $by);

        return $r;
    }

    private function sourceRefunded(object $order): int
    {
        return (int) DB::table('refunds')->where('order_id', $order->id)->where('destination', 'source')->whereIn('status', ['requested', 'approved', 'processing', 'processed'])->sum('amount_minor');
    }

    /**
     * Refund to wallet under a published policy (e.g. customer cancels ≥ 24 h before a booking).
     * No maker/checker because no staff discretion is involved; recorded and audited as 'system'.
     */
    public function policyRefundToWallet(object $order, int $amount, string $reason): ?object
    {
        if ($amount <= 0 || ! in_array($order->status, ['paid', 'partially_refunded'], true)) {
            return null;
        }
        $id = DB::table('refunds')->insertGetId([
            'ref' => (string) Str::ulid(), 'order_id' => $order->id, 'payment_id' => null, 'amount_minor' => $amount, 'destination' => 'wallet',
            'reason' => $reason, 'status' => 'approved', 'requested_by' => $order->user_id, 'decided_by' => null, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $r = DB::table('refunds')->find($id);
        $this->execute($r);
        $this->audit->record('refund.policy', null, ['refund' => $r->ref, 'order' => $order->ref, 'amount' => $amount], null, 'system');

        return DB::table('refunds')->find($id);
    }

    public function decide(string $ref, bool $approve, User $by): object
    {
        $r = DB::transaction(function () use ($ref, $approve, $by) {
            $r = DB::table('refunds')->where('ref', $ref)->lockForUpdate()->first();
            if (! $r || $r->status !== 'requested') {
                throw ValidationException::withMessages(['refund' => 'This refund is not awaiting a decision.']);
            }
            if ((int) $r->requested_by === $by->id) {
                throw ValidationException::withMessages(['refund' => 'A different person must approve a refund you requested.']);
            }
            DB::table('refunds')->where('id', $r->id)->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'decided_at' => now(), 'updated_at' => now()]);

            return DB::table('refunds')->find($r->id);
        });
        $this->audit->record($approve ? 'refund.approved' : 'refund.rejected', null, ['refund' => $r->ref], $by);
        if ($approve) {
            $this->execute($r);
        }

        return DB::table('refunds')->find($r->id);
    }

    public function execute(object $r): void
    {
        $order = DB::table('orders')->find($r->order_id);
        $revenue = $this->ledger->account($order->kind === 'recharge' ? "customer_wallet:{$order->user_id}" : 'revenue', $order->kind === 'recharge' ? 'liability' : 'revenue', $order->kind === 'recharge' ? $order->user_id : null);
        if ($r->destination === 'wallet') {
            $this->ledger->post('refund_to_wallet', 'refund:'.$r->ref, [[$revenue, 'debit', (int) $r->amount_minor], [$this->ledger->wallet($order->user_id), 'credit', (int) $r->amount_minor]], 'Refund to wallet', 'refund', $r->id);
            $this->finish($r, $order, null);

            return;
        }
        $payment = DB::table('payments')->find($r->payment_id);
        $hold = $this->ledger->account('refunds_payable', 'liability');
        // 1) Hold: move the amount out of revenue (or the recharge wallet) before calling the gateway,
        //    so a wallet cannot be spent while its refund is in flight.
        try {
            $this->ledger->post('refund_hold', 'refund-hold:'.$r->ref, [[$revenue, 'debit', (int) $r->amount_minor], [$hold, 'credit', (int) $r->amount_minor]], 'Refund hold', 'refund', $r->id);
        } catch (\App\Domain\Ledger\InsufficientFunds) {
            DB::table('refunds')->where('id', $r->id)->update(['status' => 'failed', 'error' => 'Wallet balance already spent; cannot refund this recharge to source.', 'updated_at' => now()]);

            return;
        }
        DB::table('refunds')->where('id', $r->id)->update(['status' => 'processing', 'updated_at' => now()]);
        try {
            $res = $this->rzp->refund($payment->provider_payment_id, (int) $r->amount_minor, $r->ref);
        } catch (Throwable $e) {
            // 2a) Gateway refused: release the hold back.
            $this->ledger->post('refund_release', 'refund-release:'.$r->ref, [[$hold, 'debit', (int) $r->amount_minor], [$revenue, 'credit', (int) $r->amount_minor]], 'Refund failed — hold released', 'refund', $r->id);
            DB::table('refunds')->where('id', $r->id)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300), 'updated_at' => now()]);
            $this->audit->record('refund.failed', null, ['refund' => $r->ref]);

            return;
        }
        // 2b) Gateway accepted: money leaves gateway clearing.
        $this->ledger->post('refund_to_source', 'refund:'.$r->ref, [[$hold, 'debit', (int) $r->amount_minor], [$this->ledger->account('gateway_clearing', 'asset'), 'credit', (int) $r->amount_minor]], 'Refund to original payment', 'refund', $r->id);
        $this->finish($r, $order, $res['id']);
    }

    private function finish(object $r, object $order, ?string $providerId): void
    {
        DB::transaction(function () use ($r, $order, $providerId) {
            DB::table('refunds')->where('id', $r->id)->update(['status' => 'processed', 'provider_refund_id' => $providerId, 'updated_at' => now()]);
            $total = (int) $order->refunded_minor + (int) $r->amount_minor;
            DB::table('orders')->where('id', $order->id)->update(['refunded_minor' => $total, 'status' => $total >= (int) $order->subtotal_minor ? 'refunded' : 'partially_refunded', 'updated_at' => now()]);
        });
        app(\App\Domain\Practitioners\Payouts::class)->onRefund($order);
        app(\App\Domain\Notify\Notifier::class)->send((int) $order->user_id, 'order', 'Refund processed',
            'A refund of ₹'.number_format($r->amount_minor / 100, 2).' was processed '.($r->destination === 'wallet' ? 'to your wallet.' : 'to your original payment method. Banks usually take 5–7 working days.'),
            $order->ref, route('account.receipt', $order->ref));
        $this->audit->record('refund.processed', null, ['refund' => $r->ref, 'provider_refund' => $providerId], null, 'system');
    }
}
