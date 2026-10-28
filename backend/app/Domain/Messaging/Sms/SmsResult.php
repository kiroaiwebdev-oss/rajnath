<?php

namespace App\Domain\Messaging\Sms;

final class SmsResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly string $provider,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}

    public static function failed(string $provider, string $error): self
    {
        return new self(false, $provider, null, mb_substr($error, 0, 500));
    }
}
