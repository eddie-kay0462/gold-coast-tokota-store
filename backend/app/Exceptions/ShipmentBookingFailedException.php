<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A courier booking that must not be retried automatically.
 *
 * DHL has no idempotency key: a request that timed out may still have created
 * a shipment, and sending it again could create a second, with a second label
 * and a second bill. So a DHL booking failure goes straight to a person, who
 * checks MyDHL before trying again.
 */
class ShipmentBookingFailedException extends RuntimeException {}
