<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Accepting a Yango claim sends a real courier and costs real money, and Yango
 * documents no sandbox. So acceptance happens in production and nowhere else,
 * whatever else is configured: a staging server with a real token creates and
 * prices claims, and they expire unaccepted at no cost.
 */
class YangoAcceptBlockedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Yango claims are only accepted in production; this claim was created and priced but not confirmed.');
    }
}
