<?php

namespace App\Services\Shipping;

use App\Enums\ShipmentStatus;

/**
 * Where a parcel is, according to its carrier, already translated into the
 * canonical vocabulary. The raw status travels beside it.
 */
readonly class StatusResult
{
    /** @param  array<string, mixed>|null  $payload  The raw carrier response, for the event log. */
    public function __construct(
        public ShipmentStatus $status,
        public ?string $carrierStatus = null,
        public ?int $carrierVersion = null,
        public ?string $trackingUrl = null,
        public ?int $actualCost = null,
        public ?string $actualCurrency = null,
        /** The carrier is waiting for us to confirm the booking (Yango's ready_for_approval). */
        public bool $needsConfirmation = false,
        /** No courier could be found; ShippingManager decides what happens next. */
        public bool $noCourier = false,
        public ?array $payload = null,
    ) {}
}
