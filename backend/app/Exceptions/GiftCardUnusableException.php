<?php

namespace App\Exceptions;

use RuntimeException;

/** A gift card code that can't be spent: unknown, disabled, expired or empty. */
class GiftCardUnusableException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'expired' => 'That gift card has expired.',
            'empty' => 'That gift card has no balance left.',
            default => 'That gift card code isn’t valid. Check it and try again.',
        });
    }
}
