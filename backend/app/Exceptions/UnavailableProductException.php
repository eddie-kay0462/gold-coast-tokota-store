<?php

namespace App\Exceptions;

use RuntimeException;

/** A cart line points at a product that has since been deactivated or deleted. */
class UnavailableProductException extends RuntimeException
{
    public function __construct(public readonly int $inventoryItemId)
    {
        parent::__construct("Inventory item #{$inventoryItemId} is no longer purchasable.");
    }
}
