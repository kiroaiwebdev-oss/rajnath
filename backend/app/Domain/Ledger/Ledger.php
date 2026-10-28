<?php

namespace App\Domain\Ledger;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Double-entry ledger in integer paise.
 *
 * Accounts (code → kind):
 *   gateway_clearing          asset      money held by Razorpay until settlement
 *   customer_wallet:{user}    liability  wallet balance owed to the customer
 *   revenue                   revenue    earned service revenue
 *   bonus_expense             expense    recharge bonus credit given
 *   refunds_payable           liability  approved refunds awaiting the gateway
 *
 * Balances: assets/expenses are debit-positive; liabilities/revenue are credit-positive.
 * post() is idempotent on its key and runs in one DB transaction with row locks on the touched
 * accounts (locked in id order to avoid deadlocks). A wallet may never go negative.
 */
class Ledger
{
    private const DEBIT_NORMAL = ['asset', 'expense'];

    public function account(string $code, string $kind, ?int $userId = null): object
    {
        DB::table('ledger_accounts')->insertOrIgnore(['code' => $code, 'kind' => $kind, 'user_id' => $userId, 'currency' => 'INR', 'balance_minor' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('ledger_accounts')->where('code', $code)->first();
    }

    public function wallet(int $userId): object
    {
        return $this->account("customer_wallet:$userId", 'liability', $userId);
    }

    public function walletBalance(int $userId): int
    {
        return (int) (DB::table('ledger_accounts')->where('code', "customer_wallet:$userId")->value('balance_minor') ?? 0);
    }

    /**
     * @param  array<array{0: object, 1: 'debit'|'credit', 2: int}>  $lines
     * @return array{transaction_id: int, created: bool}
     */
    public function post(string $kind, string $idempotencyKey, array $lines, ?string $memo = null, ?string $subjectType = null, ?int $subjectId = null): array
    {
        $debits = $credits = 0;
        foreach ($lines as [$acct, $dir, $amt]) {
            if (! is_int($amt) || $amt <= 0) {
                throw new InvalidArgumentException('Ledger amounts must be positive integers (paise).');
            }
            $dir === 'debit' ? $debits += $amt : $credits += $amt;
        }
        if ($debits !== $credits) {
            throw new InvalidArgumentException("Unbalanced ledger transaction ($debits ≠ $credits).");
        }

        return DB::transaction(function () use ($kind, $idempotencyKey, $lines, $memo, $subjectType, $subjectId) {
            $existing = DB::table('ledger_transactions')->where('idempotency_key', $idempotencyKey)->value('id');
            if ($existing) {
                return ['transaction_id' => $existing, 'created' => false];
            }
            $ids = array_values(array_unique(array_map(fn ($l) => $l[0]->id, $lines)));
            sort($ids);
            $accounts = DB::table('ledger_accounts')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $txId = DB::table('ledger_transactions')->insertGetId([
                'ref' => (string) Str::ulid(), 'kind' => $kind, 'idempotency_key' => $idempotencyKey, 'memo' => $memo,
                'subject_type' => $subjectType, 'subject_id' => $subjectId, 'occurred_at' => now(), 'created_at' => now(),
            ]);
            $delta = [];
            foreach ($lines as [$acct, $dir, $amt]) {
                DB::table('ledger_entries')->insert(['transaction_id' => $txId, 'account_id' => $acct->id, 'direction' => $dir, 'amount_minor' => $amt]);
                $sign = in_array($accounts[$acct->id]->kind, self::DEBIT_NORMAL, true) ? ($dir === 'debit' ? 1 : -1) : ($dir === 'credit' ? 1 : -1);
                $delta[$acct->id] = ($delta[$acct->id] ?? 0) + $sign * $amt;
            }
            foreach ($delta as $id => $d) {
                $new = $accounts[$id]->balance_minor + $d;
                if (str_starts_with($accounts[$id]->code, 'customer_wallet:') && $new < 0) {
                    throw new InsufficientFunds('Wallet balance is not enough.');
                }
                DB::table('ledger_accounts')->where('id', $id)->update(['balance_minor' => $new, 'updated_at' => now()]);
            }

            return ['transaction_id' => $txId, 'created' => true];
        }, attempts: 3);
    }

    /** Recomputes every account from its entries and checks the global debit = credit invariant. */
    public function verify(): array
    {
        $problems = [];
        $unbalanced = DB::table('ledger_entries')->select('transaction_id')
            ->selectRaw("SUM(CASE WHEN direction='debit' THEN amount_minor ELSE 0 END) d, SUM(CASE WHEN direction='credit' THEN amount_minor ELSE 0 END) c")
            ->groupBy('transaction_id')->havingRaw('d <> c')->get();
        foreach ($unbalanced as $u) {
            $problems[] = "Transaction {$u->transaction_id} is unbalanced ({$u->d} vs {$u->c}).";
        }
        foreach (DB::table('ledger_accounts')->get() as $a) {
            $sum = DB::table('ledger_entries')->where('account_id', $a->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction='debit' THEN amount_minor ELSE -amount_minor END),0) net")->value('net');
            $expected = in_array($a->kind, self::DEBIT_NORMAL, true) ? (int) $sum : -(int) $sum;
            if ($expected !== (int) $a->balance_minor) {
                $problems[] = "Account {$a->code} cached balance {$a->balance_minor} ≠ entries {$expected}.";
            }
            if (str_starts_with($a->code, 'customer_wallet:') && $a->balance_minor < 0) {
                $problems[] = "Wallet {$a->code} is negative.";
            }
        }

        return $problems;
    }
}

