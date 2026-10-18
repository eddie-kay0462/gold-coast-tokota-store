<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A carrier could not price a delivery: no courier available, an address it
 * cannot reach, a response in a currency we do not charge in. At checkout
 * this is caught and the configured fallback rate is charged instead, so a
 * courier outage never stops someone paying.
 */
class ShippingQuoteUnavailableException extends RuntimeException {}
