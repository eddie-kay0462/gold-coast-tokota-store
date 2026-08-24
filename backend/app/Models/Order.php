<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'currency',
        'fx_rate_applied',
        'subtotal',
        'shipping_cost',
        'tax',
        'total',
        'status',
        'payment_gateway',
        'payment_reference',
        'delivery_provider',
        'delivery_reference',
        'shipping_address',
    ];

    protected $casts = [
        'fx_rate_applied' => 'decimal:6',
        'subtotal' => 'integer',
        'shipping_cost' => 'integer',
        'tax' => 'integer',
        'total' => 'integer',
        'shipping_address' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
