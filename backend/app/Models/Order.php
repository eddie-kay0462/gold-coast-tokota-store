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

    protected $fillable = [
        'reference',
        'customer_id',
        'currency',
        'fx_rate_applied',
        'subtotal',
        'shipping_cost',
        'tax',
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
    ];

    protected $casts = [
        'fx_rate_applied' => 'decimal:6',
        'subtotal' => 'integer',
        'shipping_cost' => 'integer',
        'tax' => 'integer',
        'total' => 'integer',
        'shipping_address' => 'array',
        'shipping_zone' => ShippingZone::class,
        'shipping_option' => 'array',
        'delivered_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
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
