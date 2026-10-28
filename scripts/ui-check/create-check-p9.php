<?php

// Local-only fixtures for the Phase 9 UI check (run after create-check-staff.php and create-check-ops.php):
// a practitioner staff account (prints its TOTP secret/password), approved profile + commission + hours,
// the team consultation published, a completed booking with an earning, notices and an assistant note.
// Refuses outside APP_ENV=local. Never run on production.
require __DIR__.'/../../backend/vendor/autoload.php';
$app = require __DIR__.'/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Practitioners\Practitioners;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! app()->environment('local')) {
    fwrite(STDERR, "Refusing: APP_ENV must be local\n");
    exit(1);
}
$email = 'ui-practitioner@localhost.test';
$password = 'Ui-Pract-'.bin2hex(random_bytes(6)).'!A1';
$secret = (new PragmaRX\Google2FA\Google2FA)->generateSecretKey(32);
$u = User::where('email', $email)->first() ?? new User(['email' => $email, 'name' => 'UI Check Astrologer']);
$u->kind = 'staff';
$u->status = 'active';
$u->password = $password;
$u->mfa_secret = $secret;
$u->mfa_confirmed_at = now();
$u->save();
$u->syncRoles(['practitioner']);
Cache::forget('mfa:last:u'.$u->id);

$owner = User::where('email', 'ui-check@localhost.test')->firstOrFail();
$svc = app(Practitioners::class);
$p = $svc->ensureProfile($u);
$svc->saveProfile($p, $u, ['display_name' => 'UI Check Astrologer', 'bio' => 'Sample profile used only for the local interface check. It is not a real person and never appears on a live site.',
    'languages' => 'Hindi, English', 'specialties' => 'Career, Marriage', 'experience_years' => 10], null, true);
$svc->review(DB::table('practitioners')->find($p->id), 'approve', 'team', $owner);
if (! DB::table('commission_rules')->where('practitioner_id', $p->id)->exists()) {
    DB::table('commission_rules')->insert(['practitioner_id' => $p->id, 'share_bps' => 6000, 'effective_from' => now()->subDay()->toDateString(), 'status' => 'approved',
        'proposed_by' => $owner->id, 'decided_by' => $owner->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
}
if (! DB::table('availability_rules')->where('resource_key', $p->id)->exists()) {
    foreach ([1, 2, 3, 4, 5, 6] as $wd) {
        DB::table('availability_rules')->insert(['weekday' => $wd, 'start_local' => '17:00', 'end_local' => '19:00', 'slot_minutes' => 30, 'resource_key' => $p->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
$o = Offering::where('slug', 'team-astrologer-consultation')->first();
$o->forceFill(['status' => 'active'])->save();
$v = $o->variants()->first();
$v->forceFill(['price_minor' => 110000, 'active' => true, 'mode' => 'video', 'duration_min' => 30])->save();

$customer = User::where('email', 'ops-check@localhost.test')->firstOrFail();
$n = app(App\Domain\Notify\Notifier::class);
$n->send($customer, 'report', 'Your report is ready', 'Your report from Astro Rajnath Jha is ready to read and save as PDF.', null, url('/account/reports'));
$n->send($customer, 'booking', 'Consultation reminder', 'Your consultation is tomorrow at 10:00 AM IST.', null, url('/account/bookings'));
DB::table('assistant_knowledge')->updateOrInsert(['title' => 'Office timings (UI check sample)'], ['body' => 'Monday to Saturday, 10 am to 6 pm IST.', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
$s = app(App\Support\Settings\SettingsRepository::class);
$s->set('ai.enabled', true);
$s->set('ai.order', 'groq,gemini,cloudflare,openai');
$s->set('ai.groq.enabled', true);
$s->set('ai.groq.key', 'ui-check-placeholder', secret: true);

echo json_encode(['email' => $email, 'secret' => $secret, 'password' => $password, 'practitioner' => $p->ref, 'team_variant' => $v->ref]), "\n";
