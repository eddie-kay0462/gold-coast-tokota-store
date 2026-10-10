<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Order;
use App\Services\Returns\ReturnPolicy;

/**
 * The copy for every transactional message, in one place.
 *
 * One class rather than five, because the messages share their voice and their
 * facts, and splitting them across files is how a promise ends up stated two
 * different ways. **Every timeframe below is quoted from
 * `GOLD_COAST_TOKOTA.md`, never invented** — §22.3 is explicit that shipping
 * timelines, return windows, payment methods and workshop schedules are not to
 * be changed without instruction, and an email is exactly where an invented
 * one would become a promise to a customer.
 *
 * Facts used here, with their sources:
 *   - 48-hour processing after payment confirmation — §"Domestic Shipping"
 *   - 1–2 business days standard domestic delivery — same section
 *   - a 7-day return window from receipt — §9, via ReturnPolicy::WINDOW_DAYS
 */
final class TransactionalMessages
{
    /**
     * README Feature 8: triggered post-webhook, on money actually arriving —
     * never on a payment session being opened.
     */
    public static function orderConfirmation(Order $order): NotificationMessage
    {
        $total = self::money($order->total, $order->currency);

        return new NotificationMessage(
            key: 'order_placed',
            subject: "Your Gold Coast Tokota order {$order->reference}",
            view: 'mail.order-confirmation',
            data: [
                'order' => $order,
                'total' => $total,
                'returnWindowDays' => ReturnPolicy::WINDOW_DAYS,
            ],
            // Deliberately short. This is one SMS segment including the
            // reference, which is the part a customer actually needs to quote
            // back to anyone.
            sms: "Gold Coast Tokota: payment received for order {$order->reference} ({$total}). "
                .'We process orders within 48 hours and will text you when it ships.',
        );
    }

    /**
     * §"Domestic Shipping": "Customers receive a confirmation message and
     * tracking information where available once their order has been
     * dispatched." The README does not list this trigger; the brand document
     * states it outright, and it wins.
     */
    public static function orderShipped(Order $order): NotificationMessage
    {
        $tracking = $order->delivery_reference;

        return new NotificationMessage(
            key: 'order_shipped',
            subject: "Your order {$order->reference} is on its way",
            view: 'mail.order-shipped',
            data: [
                'order' => $order,
                'tracking' => $tracking,
                'courier' => $order->delivery_provider === 'dhl' ? 'DHL' : 'our courier partner',
            ],
            // "where available" is doing real work: most orders will have no
            // tracking reference until courier credentials exist (Feature 5 is
            // still a static rate table), so the message has to read correctly
            // without one rather than trailing an empty label.
            sms: "Gold Coast Tokota: order {$order->reference} has been dispatched."
                .($tracking ? " Tracking: {$tracking}" : ''),
        );
    }

    public static function bookingSubmitted(Booking $booking): NotificationMessage
    {
        $isWaitlisted = $booking->status === 'waitlisted';

        return new NotificationMessage(
            key: 'booking_submitted',
            subject: $isWaitlisted
                ? 'You are on the waitlist — Gold Coast Tokota'
                : 'We have your booking request — Gold Coast Tokota',
            view: 'mail.booking-submitted',
            data: [
                'booking' => $booking,
                'isWaitlisted' => $isWaitlisted,
                'isDiy' => $booking->type === 'diy_order',
            ],
            sms: $isWaitlisted
                ? 'Gold Coast Tokota: that session is full, so you are on the waitlist. '
                    .'We will contact you if a place opens up.'
                : 'Gold Coast Tokota: we have your booking request and will confirm it shortly.',
        );
    }

    public static function bookingConfirmed(Booking $booking): NotificationMessage
    {
        $when = $booking->workshopSession?->scheduled_date ?? $booking->scheduled_date;

        return new NotificationMessage(
            key: 'booking_confirmed',
            subject: 'Your booking is confirmed — Gold Coast Tokota',
            view: 'mail.booking-confirmed',
            data: [
                'booking' => $booking,
                'when' => $when,
                'isDiy' => $booking->type === 'diy_order',
            ],
            sms: 'Gold Coast Tokota: your booking is confirmed'
                .($when ? ' for '.$when->format('j M Y') : '').'. We look forward to seeing you.',
        );
    }

    /**
     * A place opened up and a waitlisted booking was promoted into it — the
     * fourth trigger README Feature 8 names.
     */
    public static function waitlistPromoted(Booking $booking): NotificationMessage
    {
        $when = $booking->workshopSession?->scheduled_date;

        return new NotificationMessage(
            key: 'waitlist_promoted',
            subject: 'A place has opened up — Gold Coast Tokota',
            view: 'mail.waitlist-promoted',
            data: [
                'booking' => $booking,
                'when' => $when,
            ],
            sms: 'Gold Coast Tokota: good news, a place has opened up and you are off the waitlist'
                .($when ? ' for '.$when->format('j M Y') : '').'.',
        );
    }

    /**
     * A password reset link.
     *
     * **Email only — `sms` is deliberately null.** A reset link is a
     * credential: texting one puts account access on a channel that is
     * forwarded, screenshotted and read off lock screens, and SMS has no room
     * for a signed URL anyway. The SMS channel skips a message with no body,
     * so this needs no special handling anywhere else.
     */
    public static function passwordReset(string $url, int $expiresInMinutes): NotificationMessage
    {
        return new NotificationMessage(
            key: 'password_reset',
            subject: 'Reset your Gold Coast Tokota password',
            view: 'mail.password-reset',
            data: [
                'url' => $url,
                'expiresInMinutes' => $expiresInMinutes,
            ],
            sms: null,
        );
    }

    /**
     * Minor units to something a person reads. Orders store an integer and the
     * currency they were placed in; USD orders were converted at the rate
     * locked at checkout, so this never re-derives anything.
     */
    private static function money(int $minorUnits, string $currency): string
    {
        $symbol = $currency === 'USD' ? '$' : 'GH₵';

        return $symbol.number_format($minorUnits / 100, 2);
    }
}
