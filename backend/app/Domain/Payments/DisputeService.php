<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\Ledger;
use App\Models\Dispute;
use App\Models\Payment;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Chargebacks / payment disputes reported by Razorpay webhooks.
 *  created  → hold up to the disputed amount from the customer's unspent paid balance
 *  won      → release the hold back to the wallet
 *  lost     → the hold (and any shortfall as chargeback loss) leaves via gateway clearing
 *  closed   → release any remaining hold unless already lost
 */
class DisputeService
{
    public function __construct(private Ledger $ledger, private AuditLogger $audit) {}

    public function apply(string $event, array $dispute, ?Payment $payment): ?Dispute
    {
        if (! $payment || empty($dispute['id'])) {
            return null;
        }

        return DB::transaction(function () use ($event, $dispute, $payment) {
            $d = Dispute::where('provider_dispute_id', $dispute['id'])->lockForUpdate()->first();
            if (! $d) {
                $d = Dispute::create(['payment_id' => $payment->id, 'user_id' => $payment->user_id, 'provider_dispute_id' => $dispute['id'],
                    'amount_minor' => min((int) ($dispute['amount'] ?? $payment->amount_minor), $payment->amount_minor), 'status' => 'open',
                    'reason_code' => mb_substr((string) ($dispute['reason_code'] ?? ''), 0, 80) ?: null,
                    'respond_by' => isset($dispute['respond_by']) ? now()->setTimestamp((int) $dispute['respond_by']) : null]);
                $cashCode = Ledger::customer('customer_cash', $d->user_id);
                $acc = $this->ledger->lock([$cashCode]);
                $hold = min($d->amount_minor, (int) $acc[$cashCode]->balance_minor);
                if ($hold > 0) {
                    $this->ledger->post('dispute_hold', "dispute:{$d->id}:hold", [
                        [$cashCode, 'debit', $hold], [Ledger::customer('customer_held', $d->user_id), 'credit', $hold],
                    ], 'Held because of a payment dispute', $d);
                }
                $d->update(['held_minor' => $hold]);
                Findings::raise('dispute.opened', $hold < $d->amount_minor ? 'critical' : 'warning', $d, ['amount' => $d->amount_minor, 'held' => $hold]);
                $this->audit->record('dispute.opened', $d, ['amount_minor' => $d->amount_minor, 'held_minor' => $hold], null, 'system');
            }
            match (true) {
                in_array($event, ['payment.dispute.won'], true) || ($dispute['status'] ?? '') === 'won' => $this->release($d, 'won'),
                in_array($event, ['payment.dispute.lost'], true) || ($dispute['status'] ?? '') === 'lost' => $this->lose($d),
                $event === 'payment.dispute.closed' || ($dispute['status'] ?? '') === 'closed' => $this->release($d, 'closed'),
                $event === 'payment.dispute.under_review' => $d->status === 'open' ? $d->update(['status' => 'under_review']) : null,
                default => null,
            };

            return $d->refresh();
        });
    }

    private function release(Dispute $d, string $status): void
    {
        if (in_array($d->status, ['won', 'lost', 'closed'], true)) {
            return;
        }
        if ($d->held_minor > 0) {
            $this->ledger->post('dispute_release', "dispute:{$d->id}:release", [
                [Ledger::customer('customer_held', $d->user_id), 'debit', $d->held_minor],
                [Ledger::customer('customer_cash', $d->user_id), 'credit', $d->held_minor],
            ], 'Dispute resolved; hold released', $d);
        }
        $d->update(['status' => $status]);
        $this->audit->record('dispute.'.$status, $d, [], null, 'system');
    }

    private function lose(Dispute $d): void
    {
        if (in_array($d->status, ['won', 'lost', 'closed'], true)) {
            return;
        }
        $this->ledger->post('dispute_lost', "dispute:{$d->id}:lost", [
            [Ledger::customer('customer_held', $d->user_id), 'debit', $d->held_minor],
            ['chargeback_loss', 'debit', $d->amount_minor - $d->held_minor],
            ['gateway_clearing', 'credit', $d->amount_minor],
        ], 'Dispute lost; amount returned to the card holder', $d);
        $d->update(['status' => 'lost']);
        $this->audit->record('dispute.lost', $d, ['loss_minor' => $d->amount_minor - $d->held_minor], null, 'system');
    }
}
