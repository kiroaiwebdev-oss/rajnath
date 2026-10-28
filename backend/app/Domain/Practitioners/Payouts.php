<?php

namespace App\Domain\Practitioners;

use App\Domain\Ledger\Ledger;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Practitioner earnings and payouts (double-entry):
 *   completed booking → earning (pending for the refund window) → available
 *     ledger: practitioner_expense DR / practitioner_payable:{id} CR
 *   finance proposes a payout of all available earnings → a DIFFERENT person approves →
 *   finance transfers the money from the bank (outside the system) and records the UTR → paid
 *     ledger: practitioner_payable:{id} DR / payout_bank CR
 * Refunds on a booking adjust a not-yet-paid earning; after payout they raise a finance finding.
 * RazorpayX / automatic bank transfer is not integrated: payouts are bank/UPI transfers recorded here.
 */
class Payouts
{
    public function __construct(private Ledger $ledger, private AuditLogger $audit) {}

    private function payable(int $practitionerId): object
    {
        return $this->ledger->account('practitioner_payable:'.$practitionerId, 'liability');
    }

    public function accrue(object $booking): ?object
    {
        if (! $booking->practitioner_id || $booking->status !== 'completed' || DB::table('practitioner_earnings')->where('booking_id', $booking->id)->exists()) {
            return null;
        }
        $order = DB::table('orders')->find($booking->order_id);
        $share = Practitioners::currentShareBps($booking->practitioner_id);
        $gross = $order ? (int) $order->subtotal_minor - (int) $order->refunded_minor : 0;
        if ($share === null || $gross <= 0) {
            DB::table('reconciliation_findings')->insert(['check' => 'earning_not_accrued', 'severity' => 'warning', 'subject' => 'booking:'.$booking->ref,
                'detail' => $share === null ? 'No approved commission in effect.' : 'Nothing left to share after refunds.', 'created_at' => now(), 'updated_at' => now()]);

            return null;
        }
        $amount = intdiv($gross * $share, 10000);
        $id = DB::table('practitioner_earnings')->insertGetId(['practitioner_id' => $booking->practitioner_id, 'booking_id' => $booking->id, 'order_id' => $order->id,
            'gross_minor' => $gross, 'share_bps' => $share, 'amount_minor' => $amount, 'status' => 'pending',
            'available_at' => now()->addDays((int) config('booking.earning_hold_days', 7)), 'created_at' => now(), 'updated_at' => now()]);
        if ($amount > 0) {
            $this->ledger->post('practitioner_earning', 'earning:'.$id, [[$this->ledger->account('practitioner_expense', 'expense'), 'debit', $amount], [$this->payable($booking->practitioner_id), 'credit', $amount]],
                'Practitioner share for booking '.$booking->ref, 'booking', $booking->id);
        }

        return DB::table('practitioner_earnings')->find($id);
    }

    /** Scheduled daily: pending earnings past the refund window become available for payout. */
    public function release(): int
    {
        return DB::table('practitioner_earnings')->where('status', 'pending')->where('available_at', '<=', now())->update(['status' => 'available', 'updated_at' => now()]);
    }

    /** Called after any refund on an order: shrink the unpaid earning to the new net amount. */
    public function onRefund(object $order): void
    {
        $order = DB::table('orders')->find($order->id);
        foreach (DB::table('practitioner_earnings')->where('order_id', $order->id)->get() as $e) {
            $gross = max(0, (int) $order->subtotal_minor - (int) $order->refunded_minor);
            $new = intdiv($gross * $e->share_bps, 10000);
            $delta = (int) $e->amount_minor - $new;
            if ($delta <= 0) {
                continue;
            }
            if (! in_array($e->status, ['pending', 'available'], true)) {
                DB::table('reconciliation_findings')->insert(['check' => 'refund_after_payout', 'severity' => 'warning', 'subject' => 'earning:'.$e->id,
                    'detail' => "Order refunded after the practitioner share was paid out; recover ₹".number_format($delta / 100, 2).' manually.', 'created_at' => now(), 'updated_at' => now()]);

                continue;
            }
            $this->ledger->post('practitioner_earning_reversal', 'earning-rev:'.$e->id.':'.$order->refunded_minor,
                [[$this->payable($e->practitioner_id), 'debit', $delta], [$this->ledger->account('practitioner_expense', 'expense'), 'credit', $delta]], 'Refund adjustment', 'booking', $e->booking_id);
            DB::table('practitioner_earnings')->where('id', $e->id)->update(['gross_minor' => $gross, 'amount_minor' => $new, 'status' => $new === 0 ? 'reversed' : $e->status, 'updated_at' => now()]);
        }
    }

    public function propose(object $p, User $by): object
    {
        $details = Practitioners::payoutDetails($p);
        if (! $details) {
            throw ValidationException::withMessages(['payout' => 'The practitioner has not added payout (UPI/bank) details yet.']);
        }

        return DB::transaction(function () use ($p, $by) {
            $earnings = DB::table('practitioner_earnings')->where('practitioner_id', $p->id)->where('status', 'available')->lockForUpdate()->get();
            $sum = (int) $earnings->sum('amount_minor');
            if ($sum <= 0) {
                throw ValidationException::withMessages(['payout' => 'Nothing is available to pay yet.']);
            }
            $id = DB::table('payouts')->insertGetId(['ref' => (string) Str::ulid(), 'practitioner_id' => $p->id, 'amount_minor' => $sum, 'status' => 'proposed',
                'method_snapshot' => $p->payout_details, 'proposed_by' => $by->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('practitioner_earnings')->whereIn('id', $earnings->pluck('id'))->update(['status' => 'in_payout', 'payout_id' => $id, 'updated_at' => now()]);
            $payout = DB::table('payouts')->find($id);
            $this->audit->record('payout.proposed', null, ['payout' => $payout->ref, 'amount' => $sum], $by);

            return $payout;
        });
    }

    public function approve(object $payout, User $by): void
    {
        if ((int) $payout->proposed_by === $by->id || DB::table('practitioners')->where('id', $payout->practitioner_id)->value('user_id') === $by->id) {
            throw ValidationException::withMessages(['payout' => 'A different person must approve a payout.']);
        }
        if (! DB::table('payouts')->where('id', $payout->id)->where('status', 'proposed')->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now(), 'updated_at' => now()])) {
            throw ValidationException::withMessages(['payout' => 'This payout is not awaiting approval.']);
        }
        $this->audit->record('payout.approved', null, ['payout' => $payout->ref], $by);
    }

    public function markPaid(object $payout, string $utr, User $by): void
    {
        DB::transaction(function () use ($payout, $utr, $by) {
            if (! DB::table('payouts')->where('id', $payout->id)->where('status', 'approved')->update(['status' => 'paid', 'utr' => $utr, 'paid_by' => $by->id, 'paid_at' => now(), 'updated_at' => now()])) {
                throw ValidationException::withMessages(['utr' => 'Only approved payouts can be marked paid.']);
            }
            $this->ledger->post('practitioner_payout', 'payout:'.$payout->ref, [[$this->payable($payout->practitioner_id), 'debit', (int) $payout->amount_minor], [$this->ledger->account('payout_bank', 'asset'), 'credit', (int) $payout->amount_minor]],
                'Payout '.$payout->ref.' UTR '.$utr, 'payout', $payout->id);
            DB::table('practitioner_earnings')->where('payout_id', $payout->id)->update(['status' => 'paid', 'updated_at' => now()]);
        });
        $this->audit->record('payout.paid', null, ['payout' => $payout->ref, 'utr' => $utr], $by);
        $user = User::find(DB::table('practitioners')->where('id', $payout->practitioner_id)->value('user_id'));
        if ($user?->email) {
            try {
                Mail::raw('A payout of ₹'.number_format($payout->amount_minor / 100, 2)." was sent to you. Bank reference (UTR): $utr.", fn ($m) => $m->to($user->email)->subject('Payout sent'));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function cancel(object $payout, User $by): void
    {
        DB::transaction(function () use ($payout) {
            if (! DB::table('payouts')->where('id', $payout->id)->whereIn('status', ['proposed', 'approved'])->update(['status' => 'cancelled', 'updated_at' => now()])) {
                throw ValidationException::withMessages(['payout' => 'This payout can no longer be cancelled.']);
            }
            DB::table('practitioner_earnings')->where('payout_id', $payout->id)->update(['status' => 'available', 'payout_id' => null, 'updated_at' => now()]);
        });
        $this->audit->record('payout.cancelled', null, ['payout' => $payout->ref], $by);
    }

    public static function method(object $payout): ?array
    {
        return $payout->method_snapshot ? json_decode(Crypt::decryptString($payout->method_snapshot), true) : null;
    }

    public function balances(int $practitionerId): array
    {
        $q = fn ($s) => (int) DB::table('practitioner_earnings')->where('practitioner_id', $practitionerId)->whereIn('status', (array) $s)->sum('amount_minor');

        return ['pending' => $q('pending'), 'available' => $q('available'), 'in_payout' => $q('in_payout'), 'paid' => $q('paid')];
    }
}
