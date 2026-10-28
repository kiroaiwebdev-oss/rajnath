<?php

namespace App\Domain\Identity\Otp;

use RuntimeException;

class OtpException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
