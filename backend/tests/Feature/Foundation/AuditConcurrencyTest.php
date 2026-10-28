<?php

namespace Tests\Feature\Foundation;

use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real MariaDB concurrency: several OS processes append to the audit chain at the same time.
 * Without the row lock the chain would fork (two rows sharing a prev_hash).
 */
class AuditConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_parallel_writers_produce_one_unbroken_chain(): void
    {
        $workers = 6;
        $each = 25;
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => config('database.connections.mariadb.database'), 'CACHE_STORE' => 'array'];
        $procs = [];
        for ($w = 0; $w < $workers; $w++) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/audit-writer.php'), (string) $w, (string) $each], base_path(), $env, null, 120);
            $p->start();
            $procs[] = $p;
        }
        foreach ($procs as $p) {
            $p->wait();
            $this->assertTrue($p->isSuccessful(), $p->getErrorOutput().$p->getOutput());
        }

        $this->assertSame($workers * $each, DB::table('audit_logs')->where('action', 'concurrency.test')->count());
        $this->assertSame(0, DB::table('audit_logs')->select('prev_hash')->groupBy('prev_hash')->havingRaw('count(*) > 1')->get()->count());
        $this->assertTrue(app(AuditLogger::class)->verify()['ok']);
    }
}
