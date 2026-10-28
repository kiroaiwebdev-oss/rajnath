<?php

namespace Tests\Feature\Finance;

use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Wallet;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\FinanceHelpers;
use Tests\TestCase;

/**
 * Real MariaDB concurrency: 8 OS processes race to spend one wallet. Row locks must ensure the
 * wallet is never overspent and the ledger stays balanced.
 */
class WalletConcurrencyTest extends TestCase
{
    use DatabaseMigrations, FinanceHelpers;

    public function test_parallel_reservations_never_overspend(): void
    {
        $u = $this->customer();
        app(Wallet::class)->creditPayment($u->id, 1, 10000, 2000); // ₹120 available
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => config('database.connections.mariadb.database'), 'CACHE_STORE' => 'array'];
        $procs = [];
        for ($w = 0; $w < 8; $w++) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/wallet-worker.php'), (string) $u->id, (string) $w, '10', '300'], base_path(), $env, null, 120);
            $p->start();
            $procs[] = $p;
        }
        $ok = $no = 0;
        foreach ($procs as $p) {
            $p->wait();
            $this->assertTrue($p->isSuccessful(), $p->getErrorOutput().$p->getOutput());
            [, $a, $b] = explode(' ', trim($p->getOutput()));
            $ok += (int) $a;
            $no += (int) $b;
        }
        // 12000 / 300 = exactly 40 reservations can succeed out of 80 attempts.
        $this->assertSame(40, $ok);
        $this->assertSame(40, $no);
        $this->assertSame(40, DB::table('reservations')->count());
        $bal = app(Wallet::class)->balances($u);
        $this->assertSame(0, $bal['available']);
        $this->assertSame(12000, $bal['reserved']);
        $this->assertTrue(app(Ledger::class)->verify()['ok']);
    }
}
