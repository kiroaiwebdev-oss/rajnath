<?php

namespace Tests\Feature\Accounts;

use App\Domain\Identity\Otp\OtpException;
use App\Domain\Identity\Otp\OtpService;
use App\Mail\OtpMail;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerOtpTest extends TestCase
{
    use RefreshDatabase;

    private function sentEmailCode(): string
    {
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $code;
    }

    public function test_new_customer_registers_with_email_otp_on_website(): void
    {
        Mail::fake();
        $this->post('/account/sign-in', ['identifier' => 'Asha@Example.com'])->assertRedirect('/account/verify');
        $this->get('/account/verify')->assertOk()->assertSee('as••@example.com', false)->assertSee('Create account');
        $code = $this->sentEmailCode();

        // Terms must be accepted, and a missing tick does not burn the code.
        $this->post('/account/verify', ['code' => $code, 'name' => 'Asha'])->assertSessionHasErrors('terms');
        $this->post('/account/verify', ['code' => $code, 'name' => 'Asha', 'terms' => '1'])->assertRedirect(route('account.dashboard'));

        $u = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertSame('customer', $u->kind);
        $this->assertNotNull($u->email_verified_at);
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('consents', ['user_id' => $u->id, 'type' => 'terms_privacy']);
        $this->get('/account')->assertOk()->assertSee('Namaste, Asha');
        // Code stored only as a hash.
        $this->assertSame(0, DB::table('otp_challenges')->where('code_hash', $code)->count());
    }

    public function test_code_is_single_use_and_wrong_codes_lock_after_five(): void
    {
        Mail::fake();
        $otp = app(OtpService::class);
        $ref = $otp->issue('email', 'x@example.com', 'login', '1.1.1.1');
        $code = $this->sentEmailCode();
        for ($i = 0; $i < 5; $i++) {
            try {
                $otp->verify($ref, '000000' === $code ? '111111' : '000000', 'login');
                $this->fail('wrong code accepted');
            } catch (OtpException $e) {
            }
        }
        $this->expectExceptionMessage('Too many wrong attempts');
        $otp->verify($ref, $code, 'login');
    }

    public function test_correct_code_works_once_and_expires(): void
    {
        Mail::fake();
        $otp = app(OtpService::class);
        $ref = $otp->issue('email', 'y@example.com', 'login', null);
        $code = $this->sentEmailCode();
        $this->assertSame('y@example.com', $otp->verify($ref, $code, 'login')['destination']);
        try {
            $otp->verify($ref, $code, 'login');
            $this->fail('reused');
        } catch (OtpException $e) {
            $this->assertSame('expired', $e->reason);
        }
        $ref2 = $otp->issue('email', 'y@example.com', 'login', null);
        $this->travel(11)->minutes();
        $this->expectException(OtpException::class);
        $otp->verify($ref2, '123456', 'login');
    }

    public function test_purpose_mismatch_is_rejected(): void
    {
        Mail::fake();
        $otp = app(OtpService::class);
        $ref = $otp->issue('email', 'z@example.com', 'change_contact', null, 1);
        $this->expectException(OtpException::class);
        $otp->verify($ref, $this->sentEmailCode(), 'login');
    }

    public function test_per_destination_hourly_limit_and_resend_cooldown(): void
    {
        Mail::fake();
        $otp = app(OtpService::class);
        $ref = null;
        for ($i = 0; $i < OtpService::PER_DESTINATION_HOUR; $i++) {
            $ref = $otp->issue('email', 'busy@example.com', 'login', '2.2.2.'.$i);
        }
        try {
            $otp->issue('email', 'busy@example.com', 'login', '9.9.9.9');
            $this->fail('limit not applied');
        } catch (OtpException $e) {
            $this->assertSame('rate_limited', $e->reason);
        }
        try {
            $otp->resend($ref);
            $this->fail('cooldown not applied');
        } catch (OtpException $e) {
            $this->assertSame('too_soon', $e->reason);
        }
        $this->travel(31)->seconds();
        $otp->resend($ref);
        Mail::assertSent(OtpMail::class, OtpService::PER_DESTINATION_HOUR + 1);
    }

    public function test_mobile_otp_via_configured_sms_provider(): void
    {
        $s = app(SettingsRepository::class);
        $s->set('sms.driver', 'fast2sms');
        $s->set('sms.fast2sms.route', 'otp');
        $s->set('sms.fast2sms.api_key', 'k', secret: true);
        $code = null;
        Http::fake(function ($req) use (&$code) {
            parse_str(parse_url($req->url(), PHP_URL_QUERY), $q);
            $code = $q['variables_values'];

            return Http::response(['return' => true, 'request_id' => 'r']);
        });
        $this->post('/account/sign-in', ['identifier' => '098352 58203'])->assertRedirect('/account/verify');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->post('/account/verify', ['code' => $code, 'terms' => '1'])->assertRedirect();
        $this->assertNotNull(User::where('phone_e164', '+919835258203')->value('phone_verified_at'));
    }

    public function test_sms_failure_gives_clear_message(): void
    {
        $this->post('/account/sign-in', ['identifier' => '9835258203'])->assertSessionHasErrors(['identifier' => 'We could not send the SMS right now. Please try email instead, or try again shortly.']);
    }

    public function test_identifier_parsing(): void
    {
        foreach (['9835258203', '+91 98352-58203', '09835258203', '919835258203'] as $in) {
            $this->assertSame('+919835258203', \App\Domain\Identity\CustomerAuth::parseIdentifier($in)['destination'], $in);
        }
        $this->assertSame('+447700900123', \App\Domain\Identity\CustomerAuth::parseIdentifier('+44 7700 900123')['destination']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Domain\Identity\CustomerAuth::parseIdentifier('12345');
    }

    public function test_existing_customer_signs_in_without_terms_and_staff_cannot_use_customer_login(): void
    {
        Mail::fake();
        $c = new User(['name' => 'Old', 'email' => 'old@example.com']);
        $c->save();
        $this->post('/account/sign-in', ['identifier' => 'old@example.com']);
        $this->get('/account/verify')->assertSee('Sign in')->assertDontSee('Create account');
        $this->post('/account/verify', ['code' => $this->sentEmailCode()])->assertRedirect();
        $this->assertAuthenticatedAs($c);
        $this->assertSame(1, User::count());

        auth()->logout();
        session()->flush();
        Mail::fake();
        $staff = new User(['name' => 'S', 'email' => 'staff@example.com']);
        $staff->kind = 'staff';
        $staff->save();
        $this->post('/account/sign-in', ['identifier' => 'staff@example.com']);
        $this->post('/account/verify', ['code' => $this->sentEmailCode()])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_suspended_customer_cannot_sign_in(): void
    {
        Mail::fake();
        $c = new User(['name' => 'Sus', 'email' => 'sus@example.com']);
        $c->status = 'suspended';
        $c->save();
        $this->post('/account/sign-in', ['identifier' => 'sus@example.com']);
        $this->post('/account/verify', ['code' => $this->sentEmailCode()])->assertSessionHasErrors('code');
        $this->assertGuest();
    }
}
