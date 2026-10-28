<?php

namespace Tests\Feature\Messaging;

use App\Support\Net\SafeHost;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class ProvidersAdminTest extends TestCase
{
    /** Fake, well-formed Twilio-style ID built at runtime (not a real credential). */
    private static function fakeSid(string $prefix): string
    {
        return $prefix.str_repeat('0f', 16);
    }

    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncRbac();
    }

    private function mailPayload(array $o = []): array
    {
        return array_merge(['mail_host' => '93.184.215.14', 'mail_port' => 587, 'mail_encryption' => 'tls', 'mail_username' => 'otp@astrorajnathjha.com',
            'mail_password' => 'Smtp-Secret-778', 'mail_from_address' => 'otp@astrorajnathjha.com', 'mail_from_name' => 'Astro Rajnath Jha'], $o);
    }

    private function smsPayload(array $o = []): array
    {
        return array_merge(['sms_driver' => 'fast2sms', 'sms_otp_text' => '{otp} is your code', 'sms_international_driver' => 'none',
            'sms_fast2sms_api_key' => 'F2S-KEY-0001', 'sms_fast2sms_route' => 'dlt', 'sms_fast2sms_sender_id' => 'ARJOTP',
            'sms_fast2sms_template_id' => '178945', 'sms_fast2sms_variables' => '{otp}'], $o);
    }

    public function test_only_owner_level_staff_can_open_provider_settings(): void
    {
        foreach (['administrator', 'finance', 'support', 'content_publisher', 'fulfilment'] as $role) {
            $this->asStaff($this->makeStaff([$role]))->get('/admin/providers')->assertForbidden();
        }
        $this->asStaff($this->makeStaff(['owner']))->get('/admin/providers')->assertOk()
            ->assertSee('Email (SMTP)')->assertSee('Twilio')->assertSee('Fast2SMS');
    }

    public function test_saving_requires_step_up(): void
    {
        $this->asStaff($this->makeStaff(['owner']), freshStepUp: false)->put('/admin/providers/mail', $this->mailPayload())->assertRedirect(route('staff.step-up'));
        $this->assertNull(app(SettingsRepository::class)->get('mail.host'));
    }

    public function test_mail_settings_saved_encrypted_and_password_kept_when_blank(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->put('/admin/providers/mail', $this->mailPayload())->assertSessionHasNoErrors();
        $raw = DB::table('settings')->where('key', 'mail.password')->value('value');
        $this->assertStringNotContainsString('Smtp-Secret-778', $raw);

        // Blank password = keep existing.
        $this->put('/admin/providers/mail', $this->mailPayload(['mail_password' => '', 'mail_from_name' => 'ARJ']))->assertSessionHasNoErrors();
        $s = app(SettingsRepository::class);
        $this->assertSame('Smtp-Secret-778', $s->get('mail.password'));
        $this->assertSame('ARJ', $s->get('mail.from_name'));

        // Page shows only a masked hint, never the password.
        $this->get('/admin/providers')->assertDontSee('Smtp-Secret-778')->assertSee('••••••-778');

        // Runtime mailer uses the saved settings.
        app(\App\Domain\Messaging\MailConfigurator::class)->apply();
        $this->assertSame('93.184.215.14', config('mail.mailers.smtp.host'));
        $this->assertTrue(config('mail.mailers.smtp.require_tls'));
        $this->assertSame('otp@astrorajnathjha.com', config('mail.from.address'));

        // Audit has the change but not the value.
        $this->assertStringNotContainsString('Smtp-Secret-778', DB::table('audit_logs')->pluck('context')->implode(' '));
    }

    public function test_mail_validation_and_ssrf_guard(): void
    {
        $owner = $this->makeStaff(['owner']);
        foreach (['127.0.0.1', '10.0.0.5', '169.254.169.254', '192.168.1.1', 'localhost', 'not a host'] as $bad) {
            $this->asStaff($owner)->put('/admin/providers/mail', $this->mailPayload(['mail_host' => $bad]))->assertSessionHasErrors('mail_host');
        }
        $this->put('/admin/providers/mail', $this->mailPayload(['mail_port' => 8080]))->assertSessionHasErrors('mail_port');
        $this->put('/admin/providers/mail', $this->mailPayload(['mail_encryption' => 'ssl', 'mail_port' => 587]))->assertSessionHasErrors('mail_port');
        $this->assertNull(app(SettingsRepository::class)->get('mail.host'));
    }

    public function test_safe_host_rules(): void
    {
        $this->assertNull(SafeHost::check('smtp.zoho.in', fn () => ['204.141.33.44']));
        $this->assertNotNull(SafeHost::check('evil.example.com', fn () => ['10.1.2.3']));
        $this->assertNotNull(SafeHost::check('rebind.example.com', fn () => ['8.8.8.8', '127.0.0.1']));
        $this->assertNotNull(SafeHost::check('cgnat.example.com', fn () => ['100.64.1.1']));
        $this->assertNotNull(SafeHost::check('v6.example.com', fn () => ['fd00::1']));
        $this->assertNotNull(SafeHost::check('nowhere.example.com', fn () => []));
        config(['services.providers.allow_private_hosts' => true]);
        $this->assertNull(SafeHost::check('relay.internal.example', fn () => ['10.0.0.2']));
    }

    public function test_sms_settings_switching_keeps_both_providers(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->put('/admin/providers/sms', $this->smsPayload())->assertSessionHasNoErrors();
        $this->put('/admin/providers/sms', $this->smsPayload(['sms_driver' => 'twilio', 'sms_fast2sms_api_key' => '',
            'sms_twilio_account_sid' => self::fakeSid('AC'), 'sms_twilio_auth_token' => 'TW-TOKEN-9', 'sms_twilio_from' => '+15005550006']))
            ->assertSessionHasNoErrors();
        $s = app(SettingsRepository::class);
        $this->assertSame('twilio', $s->get('sms.driver'));
        $this->assertSame('F2S-KEY-0001', $s->get('sms.fast2sms.api_key'), 'Fast2SMS key kept while Twilio is active');
        $this->assertSame('TW-TOKEN-9', $s->get('sms.twilio.auth_token'));

        $this->put('/admin/providers/sms', $this->smsPayload(['sms_driver' => 'twilio', 'sms_fast2sms_api_key' => '', 'clear_sms_fast2sms_api_key' => '1']));
        $this->assertNull(app(SettingsRepository::class)->get('sms.fast2sms.api_key'));
    }

    public function test_sms_validation(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->put('/admin/providers/sms', $this->smsPayload(['sms_driver' => 'msg91']))->assertSessionHasErrors('sms_driver');
        $this->put('/admin/providers/sms', $this->smsPayload(['sms_fast2sms_sender_id' => 'AB12']))->assertSessionHasErrors('sms_fast2sms_sender_id');
        $this->put('/admin/providers/sms', $this->smsPayload(['sms_otp_text' => 'no code here']))->assertSessionHasErrors('sms_otp_text');
        $this->put('/admin/providers/sms', $this->smsPayload(['sms_twilio_account_sid' => 'XX123']))->assertSessionHasErrors('sms_twilio_account_sid');
    }

    public function test_send_test_sms_through_admin(): void
    {
        Http::fake(['www.fast2sms.com/*' => Http::response(['return' => true, 'request_id' => 'r1'])]);
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->put('/admin/providers/sms', $this->smsPayload());
        $this->post('/admin/providers/sms/test', ['test_phone' => '98352 58203'])->assertSessionHasNoErrors()->assertSessionHas('status');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'numbers=9835258203'));
        $this->get('/admin/providers')->assertSee('Accepted')->assertDontSee('9835258203');

        $this->post('/admin/providers/sms/test', ['test_phone' => '12'])->assertSessionHasErrors('test_phone');
    }

    public function test_send_test_email_reports_failure_clearly(): void
    {
        $owner = $this->makeStaff(['owner']);
        // Nothing listens on this local port → connection refused → clear error, no exception page.
        app(SettingsRepository::class)->set('mail.host', '127.0.0.1');
        app(SettingsRepository::class)->set('mail.port', 1);
        app(SettingsRepository::class)->set('mail.from_address', 'otp@astrorajnathjha.com');
        $this->asStaff($owner)->post('/admin/providers/mail/test', ['test_email' => 'owner@example.com'])->assertSessionHasErrors('test_email');
        $this->assertSame('failed', DB::table('provider_deliveries')->value('status'));
    }
}
