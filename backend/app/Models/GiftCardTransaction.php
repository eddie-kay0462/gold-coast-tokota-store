<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a gift card's balance. Append-only.
 *
 *   issue    the card is created with its balance
 *   redeem   checkout took some off an order (negative)
 *   release  that order lapsed or was cancelled unpaid; it went back
 *   reclaim  a payment landed after the release; it was taken again
 *   adjust   an Admin changed it by hand
 */
class GiftCardTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'gift_card_id',
        'type',
        'amount',
        'balance_after',
        'order_id',
        'admin_user_id',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
