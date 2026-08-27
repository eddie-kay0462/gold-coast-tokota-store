<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A USD checkout was attempted with no cached FX rate to lock.
 *
 * Blocking is the right behaviour, not falling back to a stale or default
 * rate: USD is always derived (README Feature 2), and an order priced on a
 * guessed rate is a real financial loss on every unit sold.
 */
class NoFxRateException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No FX rate is available to lock for a USD order.');
    }
}
