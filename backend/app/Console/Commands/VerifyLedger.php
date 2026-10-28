<?php

namespace App\Console\Commands;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Findings;
use Illuminate\Console\Command;

/** Daily full ledger integrity check; problems become critical reconciliation findings. */
class VerifyLedger extends Command
{
    protected $signature = 'arj:ledger-verify';

    protected $description = 'Verify that every ledger transaction balances and cached balances match entries';

    public function handle(Ledger $ledger): int
    {
        $r = $ledger->verify();
        if ($r['ok']) {
            $this->info("OK: {$r['transactions']} transactions, {$r['accounts']} accounts.");

            return self::SUCCESS;
        }
        foreach ($r['problems'] as $p) {
            $this->error($p);
        }
        Findings::raise('ledger.integrity', 'critical', null, ['problems' => array_slice($r['problems'], 0, 50)], 'ledger.integrity:'.now()->format('Y-m-d'));

        return self::FAILURE;
    }
}
