<?php

// Standalone worker used by WalletConcurrencyTest: tries N reservations against one wallet.
// Args: <user-id> <worker-id> <count> <amount>. Prints "ok <successes> <insufficient>".
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$_, $user, $w, $n, $amount] = $argv;
$wallet = $app->make(App\Domain\Ledger\Wallet::class);
$ok = $no = 0;
for ($i = 0; $i < (int) $n; $i++) {
    try {
        $wallet->reserve((int) $user, (int) $amount, 'Concurrency', "w{$w}-{$i}");
        $ok++;
    } catch (App\Domain\Ledger\InsufficientFunds) {
        $no++;
    }
}
echo "ok {$ok} {$no}\n";
