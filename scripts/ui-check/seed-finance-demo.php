<?php

// LOCAL UI CHECKS ONLY. Gives an existing local customer realistic wallet data (packages,
// captured/failed/created payments, a refund, a finding) so the wallet and finance screens can be
// screenshotted without a real Razorpay account. Refuses outside APP_ENV=local.
// Usage: php seed-finance-demo.php <customer-email>
require __DIR__.'/../../backend/vendor/autoload.php';
$app = require __DIR__.'/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Ledger\Wallet;
use App\Domain\Payments\Findings;
use App\Models\Payment;
use App\Models\RechargePackage;
use App\Models\Refund;
use App\Models\User;
use App\Support\Settings\SettingsRepository;

if (! app()->environment('local')) {
    fwrite(STDERR, "Refusing: APP_ENV must be local\n");
    exit(1);
}
$u = User::where('email', $argv[1] ?? '')->firstOrFail();
$s = app(SettingsRepository::class);
$s->set('payments.razorpay.key_id', 'rzp_test_UICHECKONLY01');
$s->set('payments.razorpay.key_secret', 'ui-check-not-a-real-secret', secret: true);
$s->set('payments.razorpay.webhook_secret', 'ui-check-webhook-secret', secret: true);
$s->set('payments.enabled', true);
foreach ([[50000, 0, 'Starter'], [110000, 10000, 'Popular'], [250000, 30000, 'Family']] as $i => [$a, $b, $n]) {
    RechargePackage::firstOrCreate(['name' => $n], ['amount_minor' => $a, 'bonus_minor' => $b, 'active' => true, 'sort' => $i]);
}
$pk = RechargePackage::where('name', 'Popular')->first();
$mk = fn (string $status, array $extra = []) => Payment::create($extra + [
    'user_id' => $u->id, 'purpose' => 'wallet_recharge', 'recharge_package_id' => $pk->id,
    'snapshot' => ['package_ref' => $pk->ref, 'name' => $pk->name, 'amount_minor' => $pk->amount_minor, 'bonus_minor' => $pk->bonus_minor],
    'amount_minor' => $pk->amount_minor, 'bonus_minor' => $pk->bonus_minor, 'provider_mode' => 'test', 'status' => $status,
    'provider_order_id' => 'order_UI'.strtoupper(bin2hex(random_bytes(5))),
]);
$cap = $mk('captured', ['provider_payment_id' => 'pay_UI'.strtoupper(bin2hex(random_bytes(5))), 'method' => 'upi', 'captured_at' => now()]);
$tx = app(Wallet::class)->creditPayment($u->id, $cap->id, $cap->amount_minor, $cap->bonus_minor, $cap);
$cap->update(['credit_transaction_id' => $tx->id]);
$mk('failed', ['status_reason' => 'Payment declined by bank']);
$created = $mk('created');
$staff = User::where('kind', 'staff')->first();
if ($staff) {
    Refund::create(['payment_id' => $cap->id, 'user_id' => $u->id, 'amount_minor' => 20000, 'reason' => 'UI check: partial refund request', 'status' => 'requested', 'requested_by' => $staff->id]);
}
Findings::raise('payment.mismatch', 'critical', $cap, ['why' => 'UI check sample finding']);
echo $created->ref."\n";
