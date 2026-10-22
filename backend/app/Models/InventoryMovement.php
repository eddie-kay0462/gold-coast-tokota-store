<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a variant's stock history. Append-only: a correction is a new
 * movement, never an edit.
 */
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    public const HOLD = 'hold';

    public const HOLD_RELEASED = 'hold_released';

    public const HOLD_EXPIRED = 'hold_expired';

    public const SALE = 'sale';

    public const ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'inventory_item_id',
        'delta_available',
        'delta_reserved',
        'quantity_available_after',
        'quantity_reserved_after',
        'reason',
        'channel',
        'order_id',
        'admin_user_id',
        'note',
    ];

    protected $casts = [
        'delta_available' => 'integer',
        'delta_reserved' => 'integer',
        'quantity_available_after' => 'integer',
        'quantity_reserved_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
