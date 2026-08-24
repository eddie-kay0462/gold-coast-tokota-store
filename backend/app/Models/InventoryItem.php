<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_attributes',
        'quantity_available',
        'quantity_reserved',
        'reservation_expires_at',
        'low_stock_threshold',
    ];

    protected $casts = [
        'variant_attributes' => 'array',
        'quantity_available' => 'integer',
        'quantity_reserved' => 'integer',
        'reservation_expires_at' => 'datetime',
        'low_stock_threshold' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** What's actually purchasable right now — physical stock minus units already held by pending checkouts. */
    public function getSellableQuantityAttribute(): int
    {
        return max(0, $this->quantity_available - $this->quantity_reserved);
    }
}
