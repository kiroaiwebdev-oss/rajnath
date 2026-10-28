<?php

namespace App\Domain\Messaging\Sms;

use App\Domain\Messaging\ProviderConfig;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Twilio Programmable Messaging: POST /2010-04-01/Accounts/{sid}/Messages.json */
class TwilioDriver implements SmsDriver
{
    public function __construct(private ProviderConfig $cfg) {}

    public function name(): string
    {
        return 'twilio';
    }

    public function configurationError(): ?string
    {
        $sid = (string) $this->cfg->get('sms.twilio.account_sid');
        if (! preg_match('/^AC[0-9a-fA-F]{32}$/', $sid)) {
            return 'Twilio Account SID is missing or invalid.';
        }
        if (! $this->cfg->get('sms.twilio.auth_token')) {
            return 'Twilio auth token is missing.';
        }
        if (! $this->cfg->get('sms.twilio.from') && ! $this->cfg->get('sms.twilio.messaging_service_sid')) {
            return 'Set a Twilio From number or a Messaging Service SID.';
        }

        return null;
    }

    public function sendOtp(string $e164, string $otp, int $minutes): SmsResult
    {
        if ($err = $this->configurationError()) {
            return SmsResult::failed('twilio', $err);
        }
        $sid = $this->cfg->get('sms.twilio.account_sid');
        $body = strtr((string) $this->cfg->get('sms.otp_text'), [
            '{otp}' => $otp, '{minutes}' => (string) $minutes, '{brand}' => config('brand')['brand.name'],
        ]);
        $form = ['To' => $e164, 'Body' => $body];
        if ($ms = $this->cfg->get('sms.twilio.messaging_service_sid')) {
            $form['MessagingServiceSid'] = $ms;
        } else {
            $form['From'] = $this->cfg->get('sms.twilio.from');
        }

        try {
            $res = Http::asForm()->timeout(10)->connectTimeout(5)
                ->withBasicAuth($sid, $this->cfg->get('sms.twilio.auth_token'))
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $form);
        } catch (Throwable $e) {
            return SmsResult::failed('twilio', 'Could not reach Twilio: '.class_basename($e));
        }
        $json = $res->json() ?? [];
        if ($res->successful() && ! empty($json['sid'])) {
            return new SmsResult(true, 'twilio', $json['sid']);
        }

        return SmsResult::failed('twilio', 'Twilio error '.($json['code'] ?? $res->status()).': '.($json['message'] ?? 'unexpected response'));
    }
}
