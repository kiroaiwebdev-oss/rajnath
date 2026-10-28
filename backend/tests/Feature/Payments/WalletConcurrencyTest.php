<?php

namespace Tests\Feature\Payments;

use App\Domain\Ledger\Ledger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** 8 OS processes try to spend ₹100 each from a ₹500 wallet at the same moment: exactly 5 succeed. */
class WalletConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_parallel_spends_never_overdraw(): void
    {
        $l = app(Ledger::class);
        $l->post('seed', 'seed', [[$l->account('bonus_expense', 'expense'), 'debit', 50000], [$l->wallet(77), 'credit', 50000]]);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => config('database.connections.mariadb.database'), 'CACHE_STORE' => 'array'];
        $procs = [];
        for ($i = 0; $i < 8; $i++) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/wallet-spender.php'), '77', (string) $i], base_path(), $env, null, 60);
            $p->start();
            $procs[] = $p;
        }
        $ok = 0;
        foreach ($procs as $p) {
            $p->wait();
            $ok += trim($p->getOutput()) === 'ok' ? 1 : 0;
            $this->assertContains(trim($p->getOutput()), ['ok', 'insufficient'], $p->getErrorOutput());
        }
        $this->assertSame(5, $ok);
        $this->assertSame(0, $l->walletBalance(77));
        $this->assertSame([], $l->verify());
        $this->assertSame(6, DB::table('ledger_transactions')->count());
    }
}
