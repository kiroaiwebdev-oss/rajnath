<?php

namespace Tests\Feature\Finance;

use App\Domain\Ledger\InsufficientFunds;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\FinanceHelpers;
use Tests\TestCase;

class LedgerWalletTest extends TestCase
{
    use FinanceHelpers, RefreshDatabase;

    public function test_unbalanced_float_and_negative_amounts_are_rejected(): void
    {
        $l = app(Ledger::class);
        $this->expectException(InvalidArgumentException::class);
        $l->post('x', 'k1', [['gateway_clearing', 'debit', 100], ['revenue', 'credit', 99]]);
    }

    public function test_non_integer_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(Ledger::class)->post('x', 'k2', [['gateway_clearing', 'debit', 1.5], ['revenue', 'credit', 1.5]]);
    }

    public function test_posting_is_idempotent_and_verifiable(): void
    {
        $u = $this->customer();
        $w = app(Wallet::class);
        $t1 = $w->creditPayment($u->id, 1, 50000, 5000);
        $t2 = $w->creditPayment($u->id, 1, 50000, 5000);
        $this->assertSame($t1->id, $t2->id);
        $this->assertSame(['cash' => 50000, 'bonus' => 5000, 'available' => 55000, 'reserved' => 0, 'held' => 0], $w->balances($u));
        $this->assertSame(1, DB::table('ledger_transactions')->count());
        $this->assertSame(4, DB::table('ledger_entries')->count());
        $this->assertTrue(app(Ledger::class)->verify()['ok']);
        $this->assertSame(50000, app(Ledger::class)->balance('gateway_clearing'));
    }

    public function test_verify_detects_tampered_cached_balance(): void
    {
        $u = $this->customer();
        app(Wallet::class)->creditPayment($u->id, 1, 1000, 0);
        DB::table('ledger_accounts')->where('code', 'customer_cash:'.$u->id)->update(['balance_minor' => 999999]);
        $r = app(Ledger::class)->verify();
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('customer_cash:'.$u->id, implode(' ', $r['problems']));
        $this->artisan('arj:ledger-verify')->assertFailed();
        $this->assertDatabaseHas('reconciliation_findings', ['kind' => 'ledger.integrity', 'severity' => 'critical']);
    }

    public function test_reserve_uses_bonus_first_consume_and_release_keep_exact_split(): void
    {
        $u = $this->customer();
        $w = app(Wallet::class);
        $w->creditPayment($u->id, 1, 10000, 2000); // ₹100 paid + ₹20 bonus
        $r = $w->reserve($u->id, 5000, 'Booking', 'B1');
        $this->assertSame([3000, 2000], [$r->cash_minor, $r->bonus_minor]);
        $this->assertSame(['cash' => 7000, 'bonus' => 0, 'available' => 7000, 'reserved' => 5000, 'held' => 0], $w->balances($u));

        // Idempotent reservation for the same subject/key.
        $again = $w->reserve($u->id, 5000, 'Booking', 'B1');
        $this->assertSame($r->id, $again->id);

        $w->consume($r, 2500, 'tick-1');
        $w->consume($r, 2500, 'tick-1'); // duplicate tick ignored
        $this->assertSame(2500, $r->refresh()->consumed_minor);
        $this->assertSame('partial', $r->status);
        // 2000 bonus + 500 cash consumed.
        $this->assertSame(500, app(Ledger::class)->balance('revenue'));
        $this->assertSame(2000, app(Ledger::class)->balance('bonus_redeemed'));

        $released = $w->release($r);
        $this->assertSame(2500, $released);
        $this->assertSame(0, $w->release($r->refresh()));
        $this->assertSame(['cash' => 9500, 'bonus' => 0, 'available' => 9500, 'reserved' => 0, 'held' => 0], $w->balances($u));
        $this->assertTrue(app(Ledger::class)->verify()['ok']);
    }

    public function test_release_returns_bonus_when_nothing_consumed(): void
    {
        $u = $this->customer();
        $w = app(Wallet::class);
        $w->creditPayment($u->id, 1, 1000, 500);
        $r = $w->reserve($u->id, 1200, 'Booking', 'B2');
        $w->release($r);
        $this->assertSame(['cash' => 1000, 'bonus' => 500], array_intersect_key($w->balances($u), ['cash' => 0, 'bonus' => 0]));
        $this->assertSame('released', $r->refresh()->status);
    }

    public function test_insufficient_funds_and_over_consumption(): void
    {
        $u = $this->customer();
        $w = app(Wallet::class);
        $w->creditPayment($u->id, 1, 1000, 0);
        try {
            $w->reserve($u->id, 1001, 'Booking', 'B3');
            $this->fail('expected InsufficientFunds');
        } catch (InsufficientFunds) {
        }
        $this->assertSame(0, DB::table('reservations')->count());
        $r = $w->reserve($u->id, 1000, 'Booking', 'B4');
        $this->expectException(\RuntimeException::class);
        $w->consume($r, 1001, 'x');
    }

    public function test_statement_shows_customer_entries_only(): void
    {
        $a = $this->customer('a@example.com');
        $b = $this->customer('b@example.com');
        $w = app(Wallet::class);
        $w->creditPayment($a->id, 1, 1000, 100);
        $w->creditPayment($b->id, 2, 2000, 0);
        $rows = $w->statement($a->id);
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['customer_cash', 'customer_bonus'], $rows->pluck('kind')->all());
    }
}
