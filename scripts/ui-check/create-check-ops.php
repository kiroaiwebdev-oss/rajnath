<?php

// Local-only fixtures for the Phase 8 UI check: working hours, a customer with a birth profile and
// wallet-paid puja/report/consultation orders, a support ticket and a published Rashifal entry.
// Prints JSON with refs for check-ops.mjs. Refuses outside APP_ENV=local. Never run on production.
require __DIR__.'/../../backend/vendor/autoload.php';
$app = require __DIR__.'/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Booking\Slots;
use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Models\BirthProfile;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! app()->environment('local')) {
    fwrite(STDERR, "Refusing: APP_ENV must be local\n");
    exit(1);
}
$email = 'ops-check@localhost.test';
$u = User::where('email', $email)->first() ?? tap(new User(['email' => $email, 'name' => 'Ops Check']))->save();
$u = User::where('email', $email)->first();
if (! BirthProfile::where('user_id', $u->id)->exists()) {
    $p = new BirthProfile(['label' => 'self', 'name' => 'Ops Check', 'gender' => 'male', 'birth_date' => '1990-05-15', 'birth_time' => '10:30', 'time_accuracy' => 'exact',
        'place_label' => 'Patna, Bihar, India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata', 'is_primary' => true]);
    $p->forceFill(['user_id' => $u->id, 'birth_utc' => '1990-05-15 05:00:00'])->save();
}
if (! DB::table('availability_rules')->exists()) {
    foreach ([1, 2, 3, 4, 5, 6] as $wd) {
        DB::table('availability_rules')->insert(['weekday' => $wd, 'start_local' => '10:00', 'end_local' => '13:00', 'slot_minutes' => 30, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
$ledger = app(Ledger::class);
$ledger->post('bonus_grant', 'ui-check-fund:'.Str::ulid(), [[$ledger->account('bonus_expense', 'expense'), 'debit', 5000000], [$ledger->wallet($u->id), 'credit', 5000000]], 'UI check funds');

$buy = function (string $slug, int $price) use ($u) {
    $o = Offering::where('slug', $slug)->first();
    $o->forceFill(['status' => 'active'])->save();
    $v = $o->variants()->first();
    $v->forceFill(['price_minor' => $price, 'active' => true])->save();

    return app(Checkout::class)->startServiceOrder($u, $v->fresh(), true, 'ui-'.$slug.'-'.Str::ulid())['order'];
};
$puja = $buy('puja-rituals', 510000);
$report = $buy('premium-personalized-kundli', 310000);

$cv = Offering::where('slug', 'janma-kundali-reading')->first()->variants()->where('mode', 'video')->first();
$cv->forceFill(['price_minor' => 210000, 'active' => true])->save();
$first = collect(app(Slots::class)->available(30, 14))->flatten(1)->first();
[$booking] = app(Slots::class)->hold($u, $cv->fresh(), $first['start'], null, 'Career guidance', 'ui-book-'.Str::ulid(), true); // wallet-paid

$t = app(App\Domain\Support\Tickets::class)->open($u, ['category' => 'booking', 'subject' => 'Need to change language', 'body' => 'Please do the consultation in Hindi.']);
$staff = User::where('email', 'ui-check@localhost.test')->first();
if ($staff) {
    app(App\Domain\Support\Tickets::class)->reply($t, $staff, 'Of course — Acharya Ji will speak in Hindi.');
}
$today = now('Asia/Kolkata')->toDateString();
foreach (range(0, 11) as $sign) {
    DB::table('horoscopes')->updateOrInsert(['period' => 'daily', 'sign' => $sign, 'starts_on' => $today, 'locale' => 'hi'],
        ['ends_on' => $today, 'body' => 'आज का दिन सामान्य रहेगा। धैर्य से काम लें और परिवार के साथ समय बिताएँ। (UI check sample text)', 'status' => 'published', 'author_id' => $u->id, 'reviewer_id' => $staff?->id ?? $u->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
}

echo json_encode([
    'email' => $email,
    'variant' => $cv->ref,
    'booking' => $booking->ref,
    'puja' => DB::table('puja_bookings')->where('order_id', $puja->id)->value('ref'),
    'report' => DB::table('report_jobs')->where('order_id', $report->id)->value('ref'),
    'ticket' => $t->ref,
]), "\n";
