<?php

namespace App\Domain\Payments;

use RuntimeException;

/** A customer-safe message explaining why a payment cannot start. */
class PaymentUnavailable extends RuntimeException {}
