<?php

namespace App\Exceptions;

use App\Services\Shipping\QuoteResult;
use RuntimeException;

/**
 * The delivery option the customer saw no longer exists at that price — the
 * quote expired and the re-quote came back different. The order is refused
 * and the fresh quote handed back, because the figure shown at checkout must
 * be the figure charged.
 */
class ShippingOptionChangedException extends RuntimeException
{
    public function __construct(public readonly QuoteResult $quote, public readonly string $quoteId)
    {
        parent::__construct('The delivery price changed since it was shown.');
    }
}
