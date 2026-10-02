<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Production has no Paystack key, so there is no real gateway to open a
 * session with. Refused up front (503) rather than handing the customer the
 * FakeGateway's URL, which leads nowhere outside local development.
 */
class PaymentUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No payment gateway is configured.');
    }
}
