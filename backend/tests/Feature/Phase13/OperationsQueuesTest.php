<?php

namespace Tests\Feature\Phase13;

use App\Domain\Astro\Engine;
use App\Domain\Astro\PanchangService;
use App\Domain\Ledger\Ledger;
use App\Domain\Privacy\PrivacyService;
use App\Http\Controllers\Admin\HealthController;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Staff privacy queue, operational health, astrologer applications, Choghadiya and Hora. */
class OperationsQueuesTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
        Mail::fake();
    }

    private function customer(?string $email = null): User
    {
        $u = new User(['name' => 'Asha', 'email' => $email ?? uniqid().'@example.com', 'phone_e164' => '+9191'.random_int(10000000, 99999999)]);
        $u->save();

        return $u;
    }

    public function test_blocked_deletion_opens_a_case_staff_can_resolve(): void
    {
        $u = $this->customer();
        $ledger = app(Ledger::class);
        $ledger->post('test_credit', 't1', [[$ledger->account('bonus_expense', 'expense'), 'debit', 5000], [$ledger->wallet($u->id), 'credit', 5000]], 'test', 'user', $u->id);

        $r = app(PrivacyService::class)->delete($u);
        $this->assertSame(['blocked', 'open'], [$r->status, $r->case_status]);
        $again = app(PrivacyService::class)->delete($u);
        $this->assertSame($r->id, $again->id, 'a repeat request updates the open case');
        $this->assertSame(1, DB::table('privacy_requests')->where('type', 'delete')->count());

        $support = $this->makeStaff(['support']);
        $this->asStaff($this->makeStaff(['content_author']))->get(route('admin.privacy.index'))->assertForbidden();
        $this->asStaff($support)->get(route('admin.privacy.index'))->assertOk()->assertSee('Asha')->assertSee('wallet');
        $this->asStaff($support)->get(route('admin.privacy.show', $r->ref))->assertOk()->assertSee('Your wallet still has money')->assertDontSee('Run deletion');
        $this->asStaff($support)->post(route('admin.privacy.update', $r->ref), ['action' => 'assign'])->assertSessionHasNoErrors();
        $this->asStaff($support)->post(route('admin.privacy.update', $r->ref), ['action' => 'note', 'note' => 'Customer asked us to refund the wallet first.'])->assertSessionHasNoErrors();
        $c = DB::table('privacy_requests')->find($r->id);
        $this->assertSame(['in_progress', $support->id], [$c->case_status, (int) $c->assigned_to]);
        $this->assertStringContainsString('refund the wallet', $c->notes);
        $this->asStaff($support)->get(route('admin.privacy.export', $r->ref))->assertOk()->assertJsonPath('account.email', $u->email);

        // privacy.approve + step-up runs the deletion once nothing blocks it
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->post(route('admin.privacy.delete', $r->ref), ['confirm' => 'DELETE'])->assertSessionHasErrors('confirm');
        $ledger->post('test_debit', 't2', [[$ledger->wallet($u->id), 'debit', 5000], [$ledger->account('bonus_expense', 'expense'), 'credit', 5000]], 'test', 'user', $u->id);
        $this->asStaff($support)->post(route('admin.privacy.delete', $r->ref), ['confirm' => 'DELETE'])->assertForbidden();
        $this->asStaff($owner, freshStepUp: false)->post(route('admin.privacy.delete', $r->ref), ['confirm' => 'DELETE'])->assertRedirect(route('staff.step-up'));
        $this->asStaff($owner)->post(route('admin.privacy.delete', $r->ref), ['confirm' => 'DELETE'])->assertRedirect(route('admin.privacy.index'));
        $this->assertSame('anonymized', $u->fresh()->status);
        $c = DB::table('privacy_requests')->find($r->id);
        $this->assertSame(['closed', null], [$c->case_status, $c->user_id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.deleted', 'actor_id' => $owner->id]);
    }

    public function test_legal_hold_blocks_self_service_deletion(): void
    {
        $u = $this->customer();
        $r = app(PrivacyService::class)->delete($this->customer()); // unrelated completed deletion
        $this->assertSame('completed', $r->status);
        DB::table('privacy_requests')->insert(['ref' => $ref = (string) Str::ulid(), 'user_id' => $u->id, 'type' => 'export', 'status' => 'completed', 'case_status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->post(route('admin.privacy.hold', $ref), ['reason' => 'Open chargeback on order X'])->assertSessionHasNoErrors();
        $this->assertSame(['Your account is on hold for a legal or accounting reason. Our privacy team will contact you.'], app(PrivacyService::class)->blockers($u->fresh()));
        $this->asStaff($owner)->post(route('admin.privacy.hold', $ref), ['reason' => ''])->assertSessionHasNoErrors();
        $this->assertSame([], app(PrivacyService::class)->blockers($u->fresh()));
    }

    public function test_operational_health_page(): void
    {
        $this->asStaff($this->makeStaff(['support']))->get(route('admin.health'))->assertForbidden();
        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->get(route('admin.health'))->assertOk()->assertSee('No heartbeat yet')->assertSee('Database')->assertSee('RazorpayX payouts')->assertSee('Not set up');
        Cache::forever(HealthController::HEARTBEAT, now()->toIso8601String());
        DB::table('failed_jobs')->insert(['uuid' => 'u1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => "RuntimeException: SMTP refused\n#0 trace", 'failed_at' => now()]);
        $this->asStaff($admin)->get(route('admin.health'))->assertOk()->assertSee('Last run')->assertSee('SMTP refused')->assertSee('1 failed');
        $this->artisan('schedule:list')->assertSuccessful()->expectsOutputToContain('scheduler-heartbeat');
    }

    public function test_astrologer_application_from_app_to_invitation(): void
    {
        $u = $this->customer('asha@example.com');
        $token = $u->createToken('t', ['customer'])->plainTextToken;
        $form = ['name' => 'Pt. Asha Devi', 'email' => 'asha@example.com', 'phone' => '9123456789', 'experience_years' => 12, 'languages' => 'Hindi, Maithili',
            'specialties' => 'Kundli, Vastu', 'about' => str_repeat('I have practised Parashari jyotish for twelve years in Darbhanga. ', 2), 'consent' => true];
        $this->withToken($token)->postJson('/api/v1/practitioner-applications', $form)->assertJsonValidationErrors('email');
        $this->withToken($token)->postJson('/api/v1/practitioner-applications', ['about' => 'short'] + $form)->assertJsonValidationErrors('about');
        $res = $this->withToken($token)->postJson('/api/v1/practitioner-applications', ['email' => 'pandit.asha@example.com'] + $form)->assertCreated()->assertJsonPath('data.status', 'submitted');
        $this->withToken($token)->postJson('/api/v1/practitioner-applications', ['email' => 'other@example.com'] + $form)->assertJsonValidationErrors('name');
        $this->withToken($token)->getJson('/api/v1/practitioner-applications')->assertOk()->assertJsonCount(1, 'data');
        $ref = $res->json('data.ref');

        $viewer = $this->makeStaff(['support']);
        $this->asStaff($viewer)->get(route('admin.applications.index'))->assertForbidden();
        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->get(route('admin.applications.index'))->assertOk()->assertSee('Pt. Asha Devi')->assertSee('pandit.asha@example.com');
        $this->asStaff($admin)->post(route('admin.applications.decide', $ref), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->asStaff($admin, freshStepUp: false)->post(route('admin.applications.decide', $ref), ['decision' => 'accept'])->assertRedirect(route('staff.step-up'));
        $this->asStaff($admin)->post(route('admin.applications.decide', $ref), ['decision' => 'accept'])->assertSessionHasNoErrors()->assertSessionHas('invite_link');
        $inv = DB::table('staff_invitations')->where('email', 'pandit.asha@example.com')->first();
        $this->assertSame(['practitioner'], json_decode($inv->roles, true));
        $this->assertSame('accepted', DB::table('practitioner_applications')->where('ref', $ref)->value('status'));
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'title' => 'Application accepted']);
        $this->asStaff($admin)->post(route('admin.applications.decide', $ref), ['decision' => 'accept'])->assertSessionHasErrors('application');

        // website form and withdraw
        $w = $this->customer();
        $this->actingAs($w)->get(route('account.apply'))->assertOk()->assertSee('Apply to join');
        $this->actingAs($w)->post(route('account.apply'), ['email' => 'w.astro@example.com'] + $form)->assertSessionHasNoErrors();
        $wa = DB::table('practitioner_applications')->where('user_id', $w->id)->first();
        $this->actingAs($w)->get(route('account.apply'))->assertSee('Your application is with our team');
        $this->actingAs($w)->post(route('account.apply.withdraw', $wa->ref))->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', DB::table('practitioner_applications')->find($wa->id)->status);
    }

    public function test_choghadiya_and_hora_follow_the_weekday_tables(): void
    {
        if (! app(Engine::class)->available()) {
            $this->markTestSkipped('Astronomy engine not built');
        }
        // Sunday 1 Nov 2026, Patna
        $p = app(PanchangService::class)->day('2026-11-01', 25.59408, 85.13563, 'Asia/Kolkata');
        $this->assertCount(16, $p['choghadiya']);
        $this->assertSame(['Udveg', 'Char', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg'], array_column(array_slice($p['choghadiya'], 0, 8), 'name'));
        $this->assertSame(['Shubh', 'Amrit', 'Char', 'Rog', 'Kaal', 'Labh', 'Udveg', 'Shubh'], array_column(array_slice($p['choghadiya'], 8), 'name'));
        $this->assertSame($p['sunrise'], $p['choghadiya'][0]['start']);
        $this->assertSame($p['sunset'], $p['choghadiya'][7]['end']);
        $this->assertSame($p['choghadiya'][7]['end'], $p['choghadiya'][8]['start']);
        $this->assertCount(24, $p['hora']);
        $this->assertSame(['sun', 'venus', 'mercury', 'moon', 'saturn', 'jupiter', 'mars'], array_column(array_slice($p['hora'], 0, 7), 'lord'));
        // the next day's first hora is Monday's lord (24 hours later in the Chaldean order)
        $this->assertSame('moon', app(PanchangService::class)->day('2026-11-02', 25.59408, 85.13563, 'Asia/Kolkata')['hora'][0]['lord']);
        $this->assertSame('Amrit', app(PanchangService::class)->day('2026-11-02', 25.59408, 85.13563, 'Asia/Kolkata')['choghadiya'][0]['name']);
        $this->get('/panchang?date=2026-11-01')->assertOk()->assertSee('Choghadiya')->assertSee('Hora (planetary hours)')->assertSee('Udveg');
        DB::table('places')->insert(['id' => 1260086, 'name' => 'Patna', 'ascii_name' => 'Patna', 'admin1' => 'Bihar', 'country_code' => 'IN', 'country' => 'India',
            'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata', 'population' => 1599920]);
        $this->getJson('/api/v1/panchang?date=2026-11-01&place_id=1260086')->assertOk()->assertJsonCount(16, 'data.choghadiya')->assertJsonPath('data.hora.0.lord', 'sun');
    }
}
