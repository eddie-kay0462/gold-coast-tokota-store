<?php

namespace App\Models;

use App\Enums\ShippingZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    /** Statuses that represent money actually taken. */
    public const REVENUE_STATUSES = ['paid', 'processing', 'shipped', 'delivered'];

    protected $fillable = [
        'reference',
        'kind',
        'customer_id',
        'currency',
        'fx_rate_applied',
        'subtotal',
        'shipping_cost',
        'tax',
        'gift_card_id',
        'gift_card_amount',
        'gift_card_amount_ghs',
        'gift_card_released_at',
        'total',
        'status',
        'delivered_at',
        'payment_gateway',
        'payment_reference',
        'delivery_provider',
        'delivery_reference',
        'shipping_zone',
        'shipping_option',
        'shipping_address',
        'reservation_expires_at',
        'channel',
        'whatsapp_conversation_id',
        'created_by_admin_id',
        'refunded_amount',
        'refunded_at',
        'gift_card_refunded_at',
    ];

    protected $casts = [
        'fx_rate_applied' => 'decimal:6',
        'subtotal' => 'integer',
        'shipping_cost' => 'integer',
        'tax' => 'integer',
        'total' => 'integer',
        'gift_card_amount' => 'integer',
        'gift_card_amount_ghs' => 'integer',
        'gift_card_released_at' => 'datetime',
        'refunded_amount' => 'integer',
        'refunded_at' => 'datetime',
        'gift_card_refunded_at' => 'datetime',
        'shipping_address' => 'array',
        'shipping_zone' => ShippingZone::class,
        'shipping_option' => 'array',
        'delivered_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
    ];

    /** A gift card bought online: no stock, no shipment. */
    public function isGiftCardPurchase(): bool
    {
        return $this->kind === 'gift_card';
    }

    /** The card spent on this order, if any. */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Every stock hold this order has taken, active or not. */
    public function stockHolds(): HasMany
    {
        return $this->hasMany(StockHold::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /** Every booking attempt, oldest first — retries and fallbacks included. */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('id');
    }

    /**
     * The booking that counts now. A retry or a carrier hand-over is a newer
     * row, so the latest one is always the live one.
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }
}
