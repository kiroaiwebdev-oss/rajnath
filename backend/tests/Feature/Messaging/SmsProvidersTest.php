<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Sms\SmsManager;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsProvidersTest extends TestCase
{
    /** Fake, well-formed Twilio-style ID built at runtime (not a real credential). */
    private static function fakeSid(string $prefix): string
    {
        return $prefix.str_repeat('0f', 16);
    }

    use RefreshDatabase;


    private function set(array $kv): void
    {
        $s = app(SettingsRepository::class);
        foreach ($kv as $k => $v) {
            $s->set($k, $v, secret: in_array($k, ['sms.twilio.auth_token', 'sms.fast2sms.api_key'], true));
        }
    }

    public function test_twilio_request_format_and_success(): void
    {
        $this->set(['sms.driver' => 'twilio', 'sms.twilio.account_sid' => self::fakeSid('AC'), 'sms.twilio.auth_token' => 'tok_secret', 'sms.twilio.from' => '+15005550006']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201)]);

        $r = app(SmsManager::class)->sendOtp('+447700900123', '482913', 10, 'login_otp');

        $this->assertTrue($r->accepted);
        $this->assertSame('SM123', $r->messageId);
        Http::assertSent(function (Request $req) {
            return $req->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.self::fakeSid('AC').'/Messages.json'
                && $req->method() === 'POST'
                && $req['To'] === '+447700900123' && $req['From'] === '+15005550006'
                && str_contains($req['Body'], '482913') && str_contains($req['Body'], '10 minutes')
                && $req->header('Authorization')[0] === 'Basic '.base64_encode(self::fakeSid('AC').':tok_secret');
        });
        $row = DB::table('provider_deliveries')->first();
        $this->assertSame(['sms', 'twilio', 'accepted'], [$row->channel, $row->provider, $row->status]);
        $this->assertStringNotContainsString('7700900123', $row->to_masked);
    }

    public function test_twilio_messaging_service_takes_precedence_and_errors_are_reported(): void
    {
        $this->set(['sms.driver' => 'twilio', 'sms.twilio.account_sid' => self::fakeSid('AC'), 'sms.twilio.auth_token' => 't', 'sms.twilio.messaging_service_sid' => self::fakeSid('MG')]);
        Http::fake(['api.twilio.com/*' => Http::response(['code' => 21211, 'message' => "The 'To' number is not valid."], 400)]);

        $r = app(SmsManager::class)->sendOtp('+919835000000', '111111', 10, 'test');

        $this->assertFalse($r->accepted);
        $this->assertStringContainsString('21211', $r->error);
        Http::assertSent(fn (Request $req) => $req['MessagingServiceSid'] === self::fakeSid('MG') && ! isset($req['From']));
        $this->assertSame('failed', DB::table('provider_deliveries')->value('status'));
    }

    public function test_fast2sms_dlt_request_format(): void
    {
        $this->set(['sms.driver' => 'fast2sms', 'sms.fast2sms.api_key' => 'f2s_key_123', 'sms.fast2sms.route' => 'dlt',
            'sms.fast2sms.sender_id' => 'ARJOTP', 'sms.fast2sms.template_id' => '178945', 'sms.fast2sms.variables' => '{otp}|{minutes}']);
        Http::fake(['www.fast2sms.com/*' => Http::response(['return' => true, 'request_id' => 'req_9', 'message' => ['SMS sent successfully.']])]);

        $r = app(SmsManager::class)->sendOtp('+919835258203', '654321', 5, 'login_otp');

        $this->assertTrue($r->accepted);
        Http::assertSent(function (Request $req) {
            parse_str(parse_url($req->url(), PHP_URL_QUERY), $q);

            return str_starts_with($req->url(), 'https://www.fast2sms.com/dev/bulkV2?')
                && $q['route'] === 'dlt' && $q['numbers'] === '9835258203' && $q['sender_id'] === 'ARJOTP'
                && $q['message'] === '178945' && $q['variables_values'] === '654321|5'
                && $req->header('authorization')[0] === 'f2s_key_123'
                && ! str_contains($req->url(), 'f2s_key_123'); // key never in URL
        });
    }

    public function test_fast2sms_otp_route_and_error_message(): void
    {
        $this->set(['sms.driver' => 'fast2sms', 'sms.fast2sms.api_key' => 'k', 'sms.fast2sms.route' => 'otp']);
        Http::fake(['www.fast2sms.com/*' => Http::response(['return' => false, 'status_code' => 412, 'message' => 'Invalid Authentication, Check Authorization Key'], 401)]);

        $r = app(SmsManager::class)->sendOtp('+919835258203', '222333', 10, 'test');

        $this->assertFalse($r->accepted);
        $this->assertStringContainsString('Invalid Authentication', $r->error);
        Http::assertSent(function (Request $req) {
            parse_str(parse_url($req->url(), PHP_URL_QUERY), $q);

            return $q['route'] === 'otp' && $q['variables_values'] === '222333';
        });
    }

    public function test_fast2sms_refuses_foreign_numbers_unless_twilio_fallback_configured(): void
    {
        Http::fake(['*' => Http::response(['sid' => 'SM9'], 201)]);
        $this->set(['sms.driver' => 'fast2sms', 'sms.fast2sms.api_key' => 'k', 'sms.fast2sms.route' => 'otp']);
        $r = app(SmsManager::class)->sendOtp('+447700900123', '1', 10, 'test');
        $this->assertFalse($r->accepted);
        Http::assertNothingSent();

        $this->set(['sms.international_driver' => 'twilio', 'sms.twilio.account_sid' => self::fakeSid('AC'), 'sms.twilio.auth_token' => 't', 'sms.twilio.from' => '+15005550006']);
        $r = app(SmsManager::class)->sendOtp('+447700900123', '1', 10, 'test');
        $this->assertTrue($r->accepted);
        $this->assertSame('twilio', $r->provider);
    }

    public function test_incomplete_or_missing_configuration_fails_without_network(): void
    {
        Http::fake();
        $this->assertStringContainsString('No SMS provider', app(SmsManager::class)->sendOtp('+919835258203', '1', 10, 'test')->error);
        $this->set(['sms.driver' => 'fast2sms', 'sms.fast2sms.api_key' => 'k', 'sms.fast2sms.route' => 'dlt']);
        $this->assertStringContainsString('sender ID', app(SmsManager::class)->sendOtp('+919835258203', '1', 10, 'test')->error);
        Http::assertNothingSent();
    }

    public function test_network_failure_is_reported_not_thrown(): void
    {
        $this->set(['sms.driver' => 'fast2sms', 'sms.fast2sms.api_key' => 'k', 'sms.fast2sms.route' => 'otp']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $r = app(SmsManager::class)->sendOtp('+919835258203', '1', 10, 'test');
        $this->assertFalse($r->accepted);
        $this->assertStringContainsString('Could not reach Fast2SMS', $r->error);
    }
}
