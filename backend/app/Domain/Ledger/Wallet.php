<?php

namespace App\Domain\Ledger;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Customer wallet on top of the ledger.
 *
 * - Cash = money the customer paid (refundable while unspent).
 * - Bonus = promotional credit from recharge offers (spendable, never refunded as money).
 * - Spending draws bonus first, then cash. Reservations hold money for a booking/order and are
 *   later consumed (revenue) or released (back to the wallet) with the exact original split.
 */
class Wallet
{
    public function __construct(private Ledger $ledger) {}

    /** @return array{cash: int, bonus: int, available: int, reserved: int, held: int} */
    public function balances(User|int $user): array
    {
        $id = $user instanceof User ? $user->id : $user;
        $rows = DB::table('ledger_accounts')->where('user_id', $id)->pluck('balance_minor', 'kind');
        $cash = (int) ($rows['customer_cash'] ?? 0);
        $bonus = (int) ($rows['customer_bonus'] ?? 0);

        return ['cash' => $cash, 'bonus' => $bonus, 'available' => $cash + $bonus,
            'reserved' => (int) ($rows['customer_reserved'] ?? 0), 'held' => (int) ($rows['customer_held'] ?? 0)];
    }

    /** Credits a verified, captured payment (and any package bonus). Idempotent per payment. */
    public function creditPayment(int $userId, int $paymentId, int $amount, int $bonus, ?object $subject = null): object
    {
        $lines = [
            ['gateway_clearing', 'debit', $amount],
            [Ledger::customer('customer_cash', $userId), 'credit', $amount],
        ];
        if ($bonus > 0) {
            $lines[] = ['promo_expense', 'debit', $bonus];
            $lines[] = [Ledger::customer('customer_bonus', $userId), 'credit', $bonus];
        }

        return $this->ledger->post('recharge', "payment:{$paymentId}:capture", $lines, 'Wallet recharge', $subject);
    }

    /**
     * Holds money for a subject (booking, order, session). Throws InsufficientFunds.
     * Idempotent per (subjectType, subjectRef, key).
     */
    public function reserve(int $userId, int $amount, string $subjectType, string $subjectRef, string $key = 'main'): Reservation
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Reservation amount must be positive.');
        }
        $idem = "reserve:{$subjectType}:{$subjectRef}:{$key}";

        return DB::transaction(function () use ($userId, $amount, $subjectType, $subjectRef, $idem) {
            $cashC = Ledger::customer('customer_cash', $userId);
            $bonusC = Ledger::customer('customer_bonus', $userId);
            $resC = Ledger::customer('customer_reserved', $userId);
            $acc = $this->ledger->lock([$cashC, $bonusC, $resC]);
            if ($tx = DB::table('ledger_transactions')->where('idempotency_key', $idem)->first()) {
                return Reservation::where('ref', $tx->subject_ref)->firstOrFail();
            }
            $bonusAvail = (int) $acc[$bonusC]->balance_minor;
            $cashAvail = (int) $acc[$cashC]->balance_minor;
            if ($bonusAvail + $cashAvail < $amount) {
                throw new InsufficientFunds('Not enough wallet balance.');
            }
            $fromBonus = min($bonusAvail, $amount);
            $fromCash = $amount - $fromBonus;
            $r = Reservation::create(['user_id' => $userId, 'cash_minor' => $fromCash, 'bonus_minor' => $fromBonus,
                'status' => 'held', 'subject_type' => $subjectType, 'subject_ref' => $subjectRef]);
            $this->ledger->post('reserve', $idem, [
                [$bonusC, 'debit', $fromBonus], [$cashC, 'debit', $fromCash], [$resC, 'credit', $amount],
            ], "Hold for {$subjectType}", $r);

            return $r;
        }, attempts: 3);
    }

    /** Moves part (or all) of a hold to revenue. $key makes repeated calls (e.g. billing ticks) idempotent. */
    public function consume(Reservation $r, int $amount, string $key): void
    {
        DB::transaction(function () use ($r, $amount, $key) {
            $r = Reservation::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $idem = "consume:{$r->id}:{$key}";
            if (DB::table('ledger_transactions')->where('idempotency_key', $idem)->exists()) {
                return;
            }
            if (! in_array($r->status, ['held', 'partial'], true) || $amount <= 0 || $amount > $r->heldMinor()) {
                throw new RuntimeException('Cannot consume more than the remaining hold.');
            }
            // Bonus portion is consumed first.
            $bonusLeft = max(0, $r->bonus_minor - $r->consumed_minor);
            $fromBonus = min($bonusLeft, $amount);
            $fromCash = $amount - $fromBonus;
            $this->ledger->post('consume', $idem, [
                [Ledger::customer('customer_reserved', $r->user_id), 'debit', $amount],
                ['revenue', 'credit', $fromCash], ['bonus_redeemed', 'credit', $fromBonus],
            ], "Charge for {$r->subject_type}", $r);
            $r->consumed_minor += $amount;
            $r->status = $r->heldMinor() === 0 ? 'consumed' : 'partial';
            $r->save();
        });
    }

    /** Returns whatever is still held to the wallet with the original cash/bonus split. Idempotent. */
    public function release(Reservation $r): int
    {
        return DB::transaction(function () use ($r) {
            $r = Reservation::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $left = $r->heldMinor();
            if ($left === 0) {
                if ($r->status === 'partial' || $r->status === 'held') {
                    $r->update(['status' => 'consumed']);
                }

                return 0;
            }
            $bonusLeft = max(0, $r->bonus_minor - $r->consumed_minor);
            $cashLeft = $left - $bonusLeft;
            $this->ledger->post('release', "release:{$r->id}", [
                [Ledger::customer('customer_reserved', $r->user_id), 'debit', $left],
                [Ledger::customer('customer_bonus', $r->user_id), 'credit', $bonusLeft],
                [Ledger::customer('customer_cash', $r->user_id), 'credit', $cashLeft],
            ], "Release hold for {$r->subject_type}", $r);
            $r->status = $r->consumed_minor > 0 ? 'consumed' : 'released';
            $r->save();

            return $left;
        });
    }

    /**
     * Customer statement: entries on the customer's wallet accounts, newest first.
     *
     * @return Collection<int, object>
     */
    public function statement(int $userId, int $limit = 50, ?int $beforeId = null)
    {
        return DB::table('ledger_entries as e')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.account_id')
            ->join('ledger_transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('a.user_id', $userId)->whereIn('a.kind', ['customer_cash', 'customer_bonus', 'customer_held'])
            ->when($beforeId, fn ($q) => $q->where('e.id', '<', $beforeId))
            ->orderByDesc('e.id')->limit($limit)
            ->get(['e.id', 'e.direction', 'e.amount_minor', 'e.balance_after_minor', 'e.created_at', 'a.kind', 't.kind as tx_kind', 't.memo', 't.ref as tx_ref']);
    }
}
