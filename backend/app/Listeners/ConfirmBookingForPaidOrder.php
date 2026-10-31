<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\WorkshopSession;
use App\Services\Bookings\TicketPdf;
use App\Services\Notifications\OpsAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A paid event booking, paid: the booking is confirmed, given its ticket code,
 * and the customer emailed with the ticket attached.
 *
 * Normally the booking is still `awaiting_payment`, holding its seats. If the
 * payment landed after the hold lapsed (`expired`), the booking is confirmed
 * all the same: the customer has paid. If the session has filled in the
 * meantime, it's flagged `needs_attention` for staff, who can add a seat or
 * refund, rather than taking the place away from someone who paid.
 *
 * A booking staff had cancelled stays cancelled, and staff are alerted to
 * refund the payment, as for a cancelled order paid through an old link.
 */
class ConfirmBookingForPaidOrder implements ShouldQueue
{
    public function __construct(private readonly TicketPdf $tickets) {}

    public function handle(OrderPaid $event): void
    {
        if (! $event->order->isBookingPayment()) {
            return;
        }

        $booking = DB::transaction(function () use ($event) {
            $booking = Booking::query()->where('order_id', $event->order->id)->lockForUpdate()->first();
            if ($booking?->status === 'cancelled') {
                app(OpsAlert::class)->paymentNeedsRefund($event->order, (string) $event->order->payment_reference, 'its booking had been cancelled');

                return null;
            }
            if (! $booking || ! in_array($booking->status, ['awaiting_payment', 'expired'], true)) {
                return null;
            }

            $session = WorkshopSession::query()->lockForUpdate()->find($booking->workshop_session_id);
            // Seats taken by everyone else; this booking's own hold doesn't count against it.
            $othersSeated = $session
                ? $session->bookings()->whereIn('status', Booking::SEAT_HOLDING)->whereKeyNot($booking->id)->get(['id', 'details'])
                    ->sum(fn (Booking $b) => $b->attendees())
                : 0;
            $overbooked = $session && $othersSeated + $booking->attendees() > $session->capacity;

            $booking->update([
                'status' => 'confirmed',
                'hold_expires_at' => null,
                'needs_attention' => $overbooked,
            ]);
            $this->tickets->codeFor($booking);

            if ($overbooked) {
                Log::warning('A paid booking was confirmed into a full session. Add a seat or refund it.', [
                    'booking' => $booking->reference(),
                    'order' => $event->order->reference,
                ]);
            }

            return $booking;
        });

        if ($booking) {
            SendBookingNotification::dispatch($booking, 'confirmed');
        }
    }
}
