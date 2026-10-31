<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\Order;
use App\Services\Bookings\TicketPdf;
use App\Services\Delivery\DeliveryEstimates;
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
        $shipment = $order->shipment;
        $tracking = $shipment?->carrier_reference ?? $order->delivery_reference;
        $trackingUrl = $shipment?->tracking_url;
        $international = ($order->shipping_address['country'] ?? 'GH') !== 'GH';

        return new NotificationMessage(
            key: 'order_shipped',
            subject: "Your order {$order->reference} is on its way",
            view: 'mail.order-shipped',
            data: [
                'order' => $order,
                'tracking' => $tracking,
                'trackingUrl' => $trackingUrl,
                'courier' => self::courierName($shipment?->carrier ?? $order->delivery_provider, $shipment?->courier_name),
                'international' => $international,
                'deliveryEstimate' => DeliveryEstimates::for($order->shipping_address['country'] ?? null),
            ],
            // "where available" is doing real work: a courier booked by hand
            // may give no tracking at all, so the message has to read
            // correctly without one rather than trailing an empty label.
            sms: "Gold Coast Tokota: order {$order->reference} has been dispatched."
                .($trackingUrl ? " Track it: {$trackingUrl}" : ($tracking ? " Tracking: {$tracking}" : '')),
        );
    }

    /**
     * The card itself, to whoever it is for: the code, the balance, and the
     * buyer's message. Email only. A code is a bearer value, so it isn't
     * sent by SMS to a number nobody has confirmed belongs to the recipient.
     */
    public static function giftCard(GiftCard $card): NotificationMessage
    {
        $storeCredit = $card->source === GiftCard::SOURCE_STORE_CREDIT;

        return new NotificationMessage(
            key: 'gift_card_issued',
            subject: $storeCredit
                ? 'Your Gold Coast Tokota store credit'
                : 'You have a Gold Coast Tokota gift card',
            view: 'mail.gift-card',
            data: [
                'card' => $card,
                'storeCredit' => $storeCredit,
                'balance' => self::money($card->balance, 'GHS'),
                'shopUrl' => rtrim((string) config('services.paystack.callback_base_url'), '/').'/shop',
            ],
        );
    }

    /** The buyer's receipt for a gift card order, in place of the sandal order confirmation. */
    public static function giftCardReceipt(Order $order): NotificationMessage
    {
        $line = $order->items->first()?->gift_card ?? [];
        $total = self::money($order->total, $order->currency);

        return new NotificationMessage(
            key: 'gift_card_purchased',
            subject: "Your Gold Coast Tokota gift card order {$order->reference}",
            view: 'mail.gift-card-receipt',
            data: [
                'order' => $order,
                'total' => $total,
                'amount' => self::money((int) ($line['amount_ghs'] ?? 0), 'GHS'),
                'recipientName' => $line['recipient_name'] ?? null,
                'recipientEmail' => $line['recipient_email'] ?? null,
            ],
            sms: "Gold Coast Tokota: payment received for gift card order {$order->reference} ({$total}). "
                .'The card has been emailed.',
        );
    }

    /** The courier as a customer knows it, not as the code names it. */
    private static function courierName(?string $carrier, ?string $manualCourier): string
    {
        return match ($carrier) {
            'dhl' => 'DHL Express',
            'yango' => 'Yango',
            'swoove' => 'Swoove',
            default => $manualCourier ?: 'our courier partner',
        };
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

    /**
     * A booking confirmed: by staff, or by paying for a place at a paid
     * event. A workshop booking carries its ticket (a PDF with the QR code
     * staff check in against) and a link to the same ticket, in case a mail
     * client strips the attachment.
     */
    public static function bookingConfirmed(Booking $booking): NotificationMessage
    {
        $when = $booking->workshopSession?->scheduled_date ?? $booking->scheduled_date;
        $isWorkshop = $booking->type === 'workshop';
        $tickets = app(TicketPdf::class);

        return new NotificationMessage(
            key: 'booking_confirmed',
            subject: $isWorkshop ? 'Your ticket — Gold Coast Tokota' : 'Your booking is confirmed — Gold Coast Tokota',
            view: 'mail.booking-confirmed',
            data: [
                'booking' => $booking,
                'when' => $when,
                'isDiy' => ! $isWorkshop,
                'event' => $booking->workshopSession?->eventName(),
                'slot' => $booking->workshopSession?->scheduled_slot,
                'people' => $booking->attendees(),
                'ticketUrl' => $isWorkshop ? $tickets->downloadUrl($booking) : null,
                'paid' => $booking->order?->status === 'paid' || $booking->order?->status === 'refunded',
            ],
            sms: 'Gold Coast Tokota: your booking is confirmed'
                .($when ? ' for '.$when->format('j M Y') : '').'. '
                .($isWorkshop ? 'Your ticket is in your email. ' : '').'We look forward to seeing you.',
            attachments: $isWorkshop ? [[
                'name' => $tickets->filename($booking),
                'mime' => 'application/pdf',
                'ticket_booking_id' => $booking->id,
            ]] : [],
        );
    }

    /**
     * A place opened up and a waitlisted booking was promoted into it — the
     * fourth trigger README Feature 8 names.
     */
    public static function waitlistPromoted(Booking $booking, ?string $payUrl = null): NotificationMessage
    {
        $when = $booking->workshopSession?->scheduled_date;

        // At a paid event the place is offered, not given: it's theirs once
        // they pay, within the hold, and the ticket follows the payment.
        return new NotificationMessage(
            key: 'waitlist_promoted',
            subject: 'A place has opened up — Gold Coast Tokota',
            view: 'mail.waitlist-promoted',
            data: [
                'booking' => $booking,
                'when' => $when,
                'event' => $booking->workshopSession?->eventName(),
                'payUrl' => $payUrl,
                'payBy' => $payUrl ? $booking->hold_expires_at : null,
            ],
            sms: $payUrl
                ? 'Gold Coast Tokota: a place has opened up'.($when ? ' for '.$when->format('j M Y') : '').'. Pay by '
                    .$booking->hold_expires_at?->format('j M, g:ia').' to keep it: check your email for the link.'
                : 'Gold Coast Tokota: good news, a place has opened up and you are off the waitlist'
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
    /** A new team member's invitation to set their password and sign in. */
    public static function adminInvite(string $url, string $invitedBy, string $roleLabel, int $expiresInDays): NotificationMessage
    {
        return new NotificationMessage(
            key: 'admin_invite',
            subject: 'You’re invited to the Gold Coast Tokota dashboard',
            view: 'mail.admin-invite',
            data: [
                'url' => $url,
                'invitedBy' => $invitedBy,
                'roleLabel' => $roleLabel,
                'expiresInDays' => $expiresInDays,
            ],
            sms: null,
        );
    }

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
