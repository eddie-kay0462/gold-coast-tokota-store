<?php

namespace App\Exceptions;

use App\Models\InventoryItem;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly InventoryItem $inventoryItem, public readonly int $requestedQuantity)
    {
        parent::__construct("Insufficient stock for inventory item #{$inventoryItem->id}: requested {$requestedQuantity}, {$inventoryItem->sellable_quantity} sellable.");
    }
}
