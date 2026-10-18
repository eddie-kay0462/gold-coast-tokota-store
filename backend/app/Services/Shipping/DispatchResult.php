<?php

namespace App\Services\Shipping;

use App\Enums\ShipmentStatus;

/**
 * What a carrier said when asked to book a courier.
 *
 * `documents` are files the carrier produced — DHL's label and commercial
 * invoice — as raw bytes keyed by kind, for the caller to store.
 */
readonly class DispatchResult
{
    /**
     * @param  array<string, string>  $documents
     * @param  array<string, mixed>|null  $payload  The raw carrier response, for the event log.
     */
    public function __construct(
        public ShipmentStatus $status,
        public ?string $carrierReference = null,
        public ?string $carrierStatus = null,
        public ?int $carrierVersion = null,
        public ?string $trackingUrl = null,
        public ?string $pickupReference = null,
        public array $documents = [],
        public ?array $payload = null,
    ) {}
}
