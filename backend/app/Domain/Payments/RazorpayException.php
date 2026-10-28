<?php

namespace App\Domain\Payments;

use RuntimeException;

class RazorpayException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $transient = false, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
