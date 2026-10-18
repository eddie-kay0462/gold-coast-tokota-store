<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An international order containing a product with no weight, packed size or
 * HS code. DHL cannot rate it and customs cannot clear it, so checkout is
 * refused — clearly, naming the products — rather than charged a fallback
 * rate for a parcel nobody can actually send.
 */
class ShippingDataMissingException extends RuntimeException
{
    /** @param  list<string>  $products */
    public function __construct(public readonly array $products)
    {
        parent::__construct('Missing shipping data for: '.implode(', ', $products));
    }
}
