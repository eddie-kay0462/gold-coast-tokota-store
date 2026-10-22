<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Units of one variant held for one order while it waits on payment.
 *
 * Only `InventoryReservationService` writes these, always under the
 * inventory item's row lock, so `inventory_items.quantity_reserved` stays
 * equal to the sum of the active holds.
 */
class StockHold extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'order_id',
        'quantity',
        'channel',
        'expires_at',
        'released_at',
        'release_reason',
        'finalized_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Neither released nor sold. An expired hold is still active until it is swept. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at')->whereNull('finalized_at');
    }
}
