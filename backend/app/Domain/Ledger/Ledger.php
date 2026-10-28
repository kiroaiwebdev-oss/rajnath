<?php

namespace App\Domain\Ledger;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Double-entry ledger. Every financial event is one transaction whose debits equal its credits,
 * written atomically with row locks on the affected accounts (locked in id order to avoid
 * deadlocks). Each transaction has a unique idempotency key: posting the same key twice returns
 * the first transaction and changes nothing.
 *
 * Balances are kept in each account's normal direction: an entry in the normal direction
 * increases the balance, the opposite direction decreases it.
 */
class Ledger
{
    /** System accounts: code => [kind, normal side, may go negative]. */
    public const SYSTEM = [
        'gateway_clearing' => ['gateway_clearing', 'debit', true],   // money held by / owed from Razorpay
        'revenue' => ['revenue', 'credit', false],                   // consumed paid credit
        'promo_expense' => ['promo_expense', 'debit', false],        // bonus credit issued
        'bonus_redeemed' => ['bonus_redeemed', 'credit', false],     // bonus credit consumed
        'refunds_in_flight' => ['refunds_in_flight', 'credit', false],
        'chargeback_loss' => ['chargeback_loss', 'debit', false],
    ];

    /** Per-customer accounts: kind => normal side. None may go negative. */
    public const CUSTOMER = [
        'customer_cash' => 'credit',      // paid, refundable wallet balance
        'customer_bonus' => 'credit',     // promotional, non-refundable balance
        'customer_reserved' => 'credit',  // held for a booking/order until consumed or released
        'customer_held' => 'credit',      // frozen because of a payment dispute
    ];

    public function account(string $code): object
    {
        $row = DB::table('ledger_accounts')->where('code', $code)->first();
        if ($row) {
            return $row;
        }
        if (isset(self::SYSTEM[$code])) {
            [$kind, $normal, $neg] = self::SYSTEM[$code];
            $userId = null;
        } elseif (preg_match('/^(customer_[a-z]+):(\d+)$/', $code, $m) && isset(self::CUSTOMER[$m[1]])) {
            [$kind, $normal, $neg, $userId] = [$m[1], self::CUSTOMER[$m[1]], false, (int) $m[2]];
        } else {
            throw new InvalidArgumentException("Unknown ledger account {$code}");
        }
        DB::table('ledger_accounts')->insertOrIgnore([
            'code' => $code, 'kind' => $kind, 'normal' => $normal, 'user_id' => $userId,
            'currency' => 'INR', 'balance_minor' => 0, 'allow_negative' => $neg,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('ledger_accounts')->where('code', $code)->first();
    }

    public static function customer(string $kind, int $userId): string
    {
        return $kind.':'.$userId;
    }

    /**
     * Locks accounts FOR UPDATE (must be called inside a transaction) and returns them keyed by code.
     *
     * @param  string[]  $codes
     * @return array<string, object>
     */
    public function lock(array $codes): array
    {
        $ids = [];
        foreach (array_unique($codes) as $c) {
            $ids[$c] = $this->account($c)->id;
        }
        asort($ids);
        $rows = DB::table('ledger_accounts')->whereIn('id', array_values($ids))->orderBy('id')->lockForUpdate()->get()->keyBy('code');

        return $rows->all();
    }

    /**
     * @param  array<int, array{0: string, 1: 'debit'|'credit', 2: int}>  $lines  [account code, direction, amount]
     * @return object the ledger transaction row (existing one if the key was already used)
     */
    public function post(string $kind, string $idempotencyKey, array $lines, ?string $memo = null, ?object $subject = null, ?int $actorId = null): object
    {
        $lines = array_values(array_filter($lines, fn ($l) => $l[2] !== 0));
        $debits = $credits = 0;
        foreach ($lines as [$code, $dir, $amt]) {
            if (! is_int($amt) || $amt < 0) {
                throw new InvalidArgumentException('Ledger amounts must be positive integers (paise).');
            }
            match ($dir) {
                'debit' => $debits += $amt,
                'credit' => $credits += $amt,
                default => throw new InvalidArgumentException("Bad direction {$dir}"),
            };
        }
        if ($debits !== $credits || $debits === 0) {
            throw new InvalidArgumentException("Unbalanced ledger transaction ({$debits} debit vs {$credits} credit).");
        }

        try {
            return DB::transaction(function () use ($kind, $idempotencyKey, $lines, $memo, $subject, $actorId) {
                if ($existing = DB::table('ledger_transactions')->where('idempotency_key', $idempotencyKey)->first()) {
                    return $existing;
                }
                $accounts = $this->lock(array_column($lines, 0));
                $now = now('UTC')->format('Y-m-d H:i:s.u');
                $txId = DB::table('ledger_transactions')->insertGetId([
                    'ref' => (string) Str::ulid(), 'kind' => $kind, 'idempotency_key' => $idempotencyKey,
                    'memo' => $memo ? mb_substr($memo, 0, 255) : null,
                    'subject_type' => $subject ? class_basename($subject) : null,
                    'subject_ref' => $subject ? (string) ($subject->ref ?? $subject->id) : null,
                    'created_by' => $actorId, 'occurred_at' => $now,
                ]);
                $balances = array_map(fn ($a) => (int) $a->balance_minor, $accounts);
                foreach ($lines as [$code, $dir, $amt]) {
                    $a = $accounts[$code];
                    $balances[$code] += $dir === $a->normal ? $amt : -$amt;
                    if ($balances[$code] < 0 && ! $a->allow_negative) {
                        throw new InsufficientFunds("Account {$code} would go negative.");
                    }
                    DB::table('ledger_entries')->insert([
                        'transaction_id' => $txId, 'account_id' => $a->id, 'direction' => $dir,
                        'amount_minor' => $amt, 'balance_after_minor' => $balances[$code], 'created_at' => $now,
                    ]);
                }
                foreach ($balances as $code => $bal) {
                    DB::table('ledger_accounts')->where('id', $accounts[$code]->id)->update(['balance_minor' => $bal, 'updated_at' => now()]);
                }

                return DB::table('ledger_transactions')->where('id', $txId)->first();
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request posted the same key first.
            return DB::table('ledger_transactions')->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    public function balance(string $code): int
    {
        return (int) (DB::table('ledger_accounts')->where('code', $code)->value('balance_minor') ?? 0);
    }

    /**
     * Full integrity check: every transaction balances, cached balances equal the entry sums,
     * running balances are consistent and no protected account is negative.
     *
     * @return array{ok: bool, transactions: int, accounts: int, problems: string[]}
     */
    public function verify(): array
    {
        $problems = [];
        $unbalanced = DB::table('ledger_entries')->select('transaction_id')
            ->selectRaw("SUM(CASE WHEN direction='debit' THEN amount_minor ELSE 0 END) d, SUM(CASE WHEN direction='credit' THEN amount_minor ELSE 0 END) c")
            ->groupBy('transaction_id')->havingRaw('d <> c')->get();
        foreach ($unbalanced as $u) {
            $problems[] = "Transaction {$u->transaction_id} is unbalanced ({$u->d} / {$u->c}).";
        }
        $empty = DB::table('ledger_transactions as t')->leftJoin('ledger_entries as e', 'e.transaction_id', '=', 't.id')->whereNull('e.id')->count();
        if ($empty) {
            $problems[] = "{$empty} transactions have no entries.";
        }
        $sums = DB::table('ledger_entries as e')->join('ledger_accounts as a', 'a.id', '=', 'e.account_id')
            ->select('a.id', 'a.code', 'a.balance_minor', 'a.allow_negative')
            ->selectRaw('SUM(CASE WHEN e.direction = a.normal THEN e.amount_minor ELSE -CAST(e.amount_minor AS SIGNED) END) computed')
            ->groupBy('a.id', 'a.code', 'a.balance_minor', 'a.allow_negative')->get()->keyBy('id');
        $accounts = DB::table('ledger_accounts')->get();
        foreach ($accounts as $a) {
            $computed = (int) ($sums[$a->id]->computed ?? 0);
            if ($computed !== (int) $a->balance_minor) {
                $problems[] = "Account {$a->code}: cached {$a->balance_minor} but entries sum to {$computed}.";
            }
            if ($computed < 0 && ! $a->allow_negative) {
                $problems[] = "Account {$a->code} is negative ({$computed}).";
            }
            $last = DB::table('ledger_entries')->where('account_id', $a->id)->orderByDesc('id')->value('balance_after_minor');
            if ($last !== null && (int) $last !== $computed) {
                $problems[] = "Account {$a->code}: last running balance {$last} differs from {$computed}.";
            }
        }

        return ['ok' => $problems === [], 'transactions' => DB::table('ledger_transactions')->count(), 'accounts' => $accounts->count(), 'problems' => $problems];
    }
}
