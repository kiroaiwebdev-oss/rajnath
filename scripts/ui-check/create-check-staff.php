<?php

// Creates/refreshes a throwaway owner account for local UI checks only. Refuses outside APP_ENV=local.
// Prints "email secret password" for the screenshot script. Never run on production.
require __DIR__.'/../../backend/vendor/autoload.php';
$app = require __DIR__.'/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! app()->environment('local')) {
    fwrite(STDERR, "Refusing: APP_ENV must be local\n");
    exit(1);
}
Artisan::call('arj:rbac-sync');
$email = 'ui-check@localhost.test';
$password = 'Ui-Check-'.bin2hex(random_bytes(6)).'!A1';
$secret = (new PragmaRX\Google2FA\Google2FA)->generateSecretKey(32);
$u = App\Models\User::where('email', $email)->first() ?? new App\Models\User(['email' => $email, 'name' => 'UI Check Owner']);
$u->kind = 'staff';
$u->status = 'active';
$u->password = $password;
$u->mfa_secret = $secret;
$u->mfa_confirmed_at = now();
$u->save();
$u->syncRoles(['owner']);
Cache::forget('mfa:last:u'.$u->id); // new secret → previous replay marker no longer applies
echo "$email $secret $password\n";
