<?php

// Standalone worker used by AuditConcurrencyTest: boots the app against the test database and
// appends N audit records. Args: <worker-id> <count>
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$_, $w, $n] = $argv;
$audit = $app->make(App\Support\Audit\AuditLogger::class);
for ($i = 0; $i < (int) $n; $i++) {
    $audit->record('concurrency.test', null, ['w' => (int) $w, 'i' => $i], actorLabel: "worker$w");
}
echo "ok\n";
