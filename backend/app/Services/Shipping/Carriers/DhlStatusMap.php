<?php

namespace App\Services\Shipping\Carriers;

use App\Enums\ShipmentStatus;

/**
 * DHL Express tracking checkpoints onto our statuses, from the latest event's
 * `typeCode`.
 *
 * **Not verified against DHL's own code list**, which is published in the
 * MyDHL reference-data guide rather than the API spec. Only the codes whose
 * meaning decides something are mapped. Every other checkpoint (arrived at
 * a facility, processed, customs status) means the parcel is moving and is
 * kept in transit, and its description still reaches the shipment's history
 * so a person can read it.
 */
final class DhlStatusMap
{
    private const MAP = [
        'PU' => ShipmentStatus::InTransit,       // shipment picked up
        'WC' => ShipmentStatus::OutForDelivery,  // with delivery courier
        'OK' => ShipmentStatus::Delivered,       // delivered
        'RT' => ShipmentStatus::Returned,        // returned to shipper
    ];

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{0: ShipmentStatus, 1: string|null, 2: string|null}
     */
    public static function fromEvents(array $events, ShipmentStatus $current): array
    {
        $latest = self::latest($events);

        if ($latest === null) {
            return [$current, null, null];
        }

        $code = (string) ($latest['typeCode'] ?? '');
        $status = self::MAP[$code] ?? ($current === ShipmentStatus::AwaitingPickup || $current === ShipmentStatus::InTransit
            ? ShipmentStatus::InTransit
            : $current);

        return [$status, $code, (string) ($latest['description'] ?? '')];
    }

    /** @param  list<array<string, mixed>>  $events */
    private static function latest(array $events): ?array
    {
        usort($events, fn ($a, $b) => strcmp(
            ($a['date'] ?? '').' '.($a['time'] ?? ''),
            ($b['date'] ?? '').' '.($b['time'] ?? ''),
        ));

        return $events === [] ? null : end($events);
    }
}
