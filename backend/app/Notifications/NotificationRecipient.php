<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Order;

/**
 * Who a transactional message is going to.
 *
 * Exists because the answer is not "the customer": guest checkout and guest
 * booking are both supported by design, so roughly half of all orders have no
 * Customer row at all and the contact details live on the order's shipping
 * address or the booking's details blob. Every caller resolving that itself
 * would be the same fallback written five times.
 */
final class NotificationRecipient
{
    public function __construct(
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $name,
    ) {}

    /**
     * A registered customer's own details win over the address typed at
     * checkout: the account is the record they maintain, and a one-off
     * delivery address may be a gift going to someone else.
     */
    public static function forOrder(Order $order): self
    {
        $address = $order->shipping_address ?? [];

        return new self(
            email: $order->customer?->email ?? ($address['email'] ?? null),
            phone: $order->customer?->phone ?? ($address['phone'] ?? null),
            name: $order->customer?->name ?? ($address['full_name'] ?? 'there'),
        );
    }

    /**
     * Booking details are always collected from the form — StoreBookingRequest
     * requires name, email and phone on every booking, guest or not — so unlike
     * an order there is no case where contact details are missing.
     */
    public static function forBooking(Booking $booking): self
    {
        $details = $booking->details ?? [];

        return new self(
            email: $details['email'] ?? $booking->customer?->email,
            phone: $details['phone'] ?? $booking->customer?->phone,
            name: $details['name'] ?? $booking->customer?->name ?? 'there',
        );
    }

    /** First name only — the messages address people by it, not by a full legal name. */
    public function firstName(): string
    {
        return trim(explode(' ', trim($this->name))[0] ?: 'there');
    }
}
