<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Ghana address outside the Accra zone, in a region with no active flat
 * rate. Refused rather than guessed: an unpriced region is one nobody has
 * decided to deliver to yet.
 */
class RegionNotServedException extends RuntimeException
{
    public function __construct(public readonly ?string $region)
    {
        parent::__construct($region
            ? "No delivery rate is set for {$region}."
            : 'No region was given for a Ghana delivery.');
    }
}
