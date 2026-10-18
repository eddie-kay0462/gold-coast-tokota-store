<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One carrier booking for one order.
 *
 * A retry, or the hand-over from Yango to Swoove when no courier turns up,
 * creates a new row that supersedes this one, so a row never changes carrier
 * and `carrier_reference` always means one thing.
 */
class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_id',
        'zone',
        'carrier',
        'service_code',
        'attempt',
        'supersedes_id',
        'carrier_reference',
        'carrier_version',
        'status',
        'carrier_status',
        'quoted_cost',
        'quoted_currency',
        'actual_cost',
        'actual_currency',
        'tracking_url',
        'label_path',
        'invoice_path',
        'pickup_reference',
        'courier_name',
        'notes',
        'failure_reason',
        'packed_by',
        'dispatched_by',
        'packed_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'last_polled_at',
        'next_poll_at',
    ];

    protected $casts = [
        'zone' => ShippingZone::class,
        'status' => ShipmentStatus::class,
        'attempt' => 'integer',
        'carrier_version' => 'integer',
        'quoted_cost' => 'integer',
        'actual_cost' => 'integer',
        'packed_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_polled_at' => 'datetime',
        'next_poll_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $shipment) {
            $shipment->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', array_map(
            fn (ShipmentStatus $status) => $status->value,
            array_filter(ShipmentStatus::cases(), fn (ShipmentStatus $status) => $status->isActive()),
        ));
    }

    /**
     * The key sent to a carrier so a retried create can never book twice.
     * Per attempt, because attempt 2 is deliberately a new booking.
     */
    public function idempotencyKey(): string
    {
        return "{$this->uuid}-a{$this->attempt}";
    }

    /**
     * Appends to the shipment's history. Status and carrier status default to
     * the shipment's current ones, so a caller that has just updated the row
     * records what it changed to.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function record(string $source, ?string $message = null, ?array $payload = null): ShipmentEvent
    {
        return $this->events()->create([
            'status' => $this->status?->value,
            'carrier_status' => $this->carrier_status,
            'source' => $source,
            'message' => $message,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
