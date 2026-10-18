<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line in a shipment's history: a status change seen by the poller, an
 * action taken in admin, a carrier response. Append-only, because a history
 * that can be edited is not evidence of anything when a customer asks where
 * their parcel went.
 */
class ShipmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'shipment_id',
        'status',
        'carrier_status',
        'source',
        'message',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Shipment events are append-only.'));
        static::deleting(fn () => throw new LogicException('Shipment events are append-only.'));
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
