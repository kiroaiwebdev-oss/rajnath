<?php

namespace App\Domain\Messaging\Sms;

interface SmsDriver
{
    public function name(): string;

    /** Returns a reason when the driver cannot be used with current settings, else null. */
    public function configurationError(): ?string;

    /** Sends a one-time code. $e164 is a validated E.164 number. */
    public function sendOtp(string $e164, string $otp, int $minutes): SmsResult;
}
