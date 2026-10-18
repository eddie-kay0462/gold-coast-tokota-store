<?php

namespace App\Services\Shipping\Carriers;

use App\Enums\ShipmentStatus;

/**
 * Yango's 26 claim statuses, as `claims/info` documents them, onto ours.
 *
 * Two are not statuses so much as instructions: `ready_for_approval` means
 * "accept me now, the price is ready", and `performer_not_found` /
 * `estimating_failed` mean "no courier" — the poller acts on those rather
 * than just recording them.
 */
final class YangoStatusMap
{
    private const MAP = [
        'new' => ShipmentStatus::Booking,
        'estimating' => ShipmentStatus::Booking,
        'estimating_failed' => ShipmentStatus::Booking,
        'ready_for_approval' => ShipmentStatus::Booking,
        'accepted' => ShipmentStatus::Booking,
        'performer_lookup' => ShipmentStatus::Booking,
        'performer_draft' => ShipmentStatus::Booking,
        'performer_not_found' => ShipmentStatus::Booking,
        'performer_found' => ShipmentStatus::AwaitingPickup,
        'pickup_arrived' => ShipmentStatus::AwaitingPickup,
        'ready_for_pickup_confirmation' => ShipmentStatus::AwaitingPickup,
        'pickuped' => ShipmentStatus::InTransit,
        'delivery_arrived' => ShipmentStatus::OutForDelivery,
        'ready_for_delivery_confirmation' => ShipmentStatus::OutForDelivery,
        'delivered' => ShipmentStatus::Delivered,
        'delivered_finish' => ShipmentStatus::Delivered,
        // On its way back to us: still moving, not yet returned.
        'returning' => ShipmentStatus::InTransit,
        'return_arrived' => ShipmentStatus::InTransit,
        'ready_for_return_confirmation' => ShipmentStatus::InTransit,
        'returned' => ShipmentStatus::Returned,
        'returned_finish' => ShipmentStatus::Returned,
        'failed' => ShipmentStatus::Failed,
        // The courier cancelled holding the parcel: a person must find it.
        'cancelled_with_items_on_hands' => ShipmentStatus::Failed,
        'cancelled' => ShipmentStatus::Cancelled,
        'cancelled_with_payment' => ShipmentStatus::Cancelled,
        'cancelled_by_taxi' => ShipmentStatus::Cancelled,
    ];

    /** Unknown statuses keep the shipment where it was rather than guessing. */
    public static function canonical(string $status, ShipmentStatus $current): ShipmentStatus
    {
        return self::MAP[$status] ?? $current;
    }

    public static function needsConfirmation(string $status): bool
    {
        return $status === 'ready_for_approval';
    }

    public static function noCourier(string $status): bool
    {
        return in_array($status, ['performer_not_found', 'estimating_failed'], true);
    }

    /** @return list<string> */
    public static function known(): array
    {
        return array_keys(self::MAP);
    }
}
