<?php

// Worker for WalletConcurrencyTest: tries to spend ₹100 from a wallet. Args: <userId> <n>
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$_, $uid, $n] = $argv;
$l = $app->make(App\Domain\Ledger\Ledger::class);
usleep(random_int(0, 20000));
try {
    $l->post('spend', "spend-$uid-$n", [[$l->wallet((int) $uid), 'debit', 10000], [$l->account('revenue', 'revenue'), 'credit', 10000]]);
    echo "ok\n";
} catch (App\Domain\Ledger\InsufficientFunds) {
    echo "insufficient\n";
}
