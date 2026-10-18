<?php

namespace App\Enums;

/**
 * The one status vocabulary every carrier is translated into.
 *
 * Yango has 26 claim statuses, DHL has its own event codes, and a manual
 * courier has whatever staff type in. None of that reaches the order screen,
 * the customer or the poller: each driver maps its own statuses onto these,
 * and the raw value is kept beside it on the shipment for anyone who needs it.
 *
 * The names follow the shipment states the admin Shipments screen was already
 * drawn with, so the UI needed no new vocabulary.
 */
enum ShipmentStatus: string
{
    /** Created on payment, waiting for the parcel to be packed. */
    case Pending = 'pending';
    /** Packed; a courier is being booked or searched for. */
    case Booking = 'booking';
    /** A courier is assigned and on the way to collect. */
    case AwaitingPickup = 'awaiting_pickup';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    /** Needs a person: no courier, a lost parcel, a booking that failed. */
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    /** Finished one way or another; nothing left to poll. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::Cancelled, self::Returned], true);
    }

    /** With a carrier and moving, so worth polling. */
    public function isActive(): bool
    {
        return in_array($this, [self::Booking, self::AwaitingPickup, self::InTransit, self::OutForDelivery], true);
    }

    /** The parcel has left the workshop, so the order counts as shipped. */
    public function hasShipped(): bool
    {
        return in_array($this, [self::AwaitingPickup, self::InTransit, self::OutForDelivery, self::Delivered], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
