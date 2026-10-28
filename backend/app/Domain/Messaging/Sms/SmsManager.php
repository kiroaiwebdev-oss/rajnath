<?php

namespace App\Domain\Messaging\Sms;

use App\Domain\Messaging\DeliveryLog;
use App\Domain\Messaging\ProviderConfig;

/**
 * Chooses the SMS provider selected in admin. Indian numbers use the primary driver; other
 * countries use the "international" driver (Twilio) when configured, because Fast2SMS is
 * India-only. Every attempt is written to the delivery log.
 */
class SmsManager
{
    public function __construct(private ProviderConfig $cfg, private DeliveryLog $log) {}

    public function driverFor(string $e164): ?SmsDriver
    {
        $primary = $this->cfg->smsDriver();
        $name = $primary;
        if ($primary === 'fast2sms' && ! str_starts_with($e164, '+91')) {
            $name = $this->cfg->get('sms.international_driver') === 'twilio' ? 'twilio' : 'none';
        }

        return match ($name) {
            'twilio' => app(TwilioDriver::class),
            'fast2sms' => app(Fast2SmsDriver::class),
            default => null,
        };
    }

    public function sendOtp(string $e164, string $otp, int $minutes, string $purpose): SmsResult
    {
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $e164)) {
            return SmsResult::failed('none', 'Invalid phone number.');
        }
        $driver = $this->driverFor($e164);
        $started = hrtime(true);
        $result = $driver
            ? $driver->sendOtp($e164, $otp, $minutes)
            : SmsResult::failed('none', $this->cfg->smsDriver() === 'none' ? 'No SMS provider is configured.' : 'No SMS provider is configured for this country.');
        $this->log->record('sms', $result->provider, $purpose, $e164, $result->accepted, $result->messageId, $result->error, $started);

        return $result;
    }
}
