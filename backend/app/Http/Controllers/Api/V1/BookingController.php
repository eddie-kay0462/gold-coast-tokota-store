<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NoFxRateException;
use App\Exceptions\PaymentUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\WorkshopSession;
use App\Services\Bookings\BookingCheckout;
use App\Services\Bookings\TicketPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, BookingCheckout $checkout): BookingResource|JsonResponse
    {
        $data = $request->validated();
        $currency = $data['currency'] ?? 'GHS';
        unset($data['currency']);
        // Kept with a workshop booking, so a waitlist place offered later is
        // charged in the currency the customer was shopping in.
        if ($data['type'] === 'workshop') {
            $data['details']['currency'] = $currency;
        }

        // DIY orders are unlimited/queue-based (Feature 7) — always accepted,
        // no capacity check, no lock needed.
        if ($data['type'] === 'diy_order') {
            $booking = Booking::create([...$data, 'status' => 'pending']);
            SendBookingNotification::dispatch($booking, 'submitted');

            return new BookingResource($booking);
        }

        // Workshop bookings are capacity-limited — lock the session row so
        // concurrent submissions for the last spot serialize instead of both
        // reading the same "1 seat left" count and both landing as pending.
        //
        // Seats are counted in people. A party that doesn't fit goes on the
        // waitlist if the event keeps one, or is turned away with how many
        // places are left. A paid session's booking holds its seats while
        // the customer pays (BookingCheckout).
        $outcome = DB::transaction(function () use ($data) {
            $session = WorkshopSession::query()->lockForUpdate()->with('workshopType')->findOrFail($data['workshop_session_id']);
            $people = max(1, (int) ($data['details']['attendee_count'] ?? 1));
            $left = $session->remaining_capacity;

            if ($left < $people) {
                if (! $session->workshopType?->allow_waitlist) {
                    return ['full' => $left];
                }

                return ['booking' => Booking::create([...$data, 'status' => 'waitlisted']), 'paid' => false];
            }

            $paid = $session->priceGhs() !== null;

            return [
                'booking' => Booking::create([
                    ...$data,
                    'status' => $paid ? 'awaiting_payment' : 'pending',
                    'hold_expires_at' => $paid ? now()->addMinutes(BookingCheckout::HOLD_MINUTES) : null,
                ]),
                'paid' => $paid,
            ];
        });

        if (isset($outcome['full'])) {
            return response()->json([
                'message' => $outcome['full'] > 0
                    ? "Only {$outcome['full']} ".($outcome['full'] === 1 ? 'place is' : 'places are').' left in this session.'
                    : 'This session is sold out.',
                'remaining_capacity' => $outcome['full'],
            ], 409);
        }

        $booking = $outcome['booking'];

        if (! $outcome['paid']) {
            // Dispatched outside the transaction, not inside it: a queued job
            // committed alongside the booking could be picked up by a worker
            // before the transaction lands, and a confirmation for a booking
            // the worker cannot yet see is a hard failure. The message reads
            // the status, so a waitlisted booking is told it is waitlisted.
            SendBookingNotification::dispatch($booking, 'submitted');

            return new BookingResource($booking->load('workshopSession'));
        }

        // Paid: the customer is told once they've paid, with their ticket, so
        // nothing is sent yet. If payment can't open, the seats go back.
        try {
            ['order' => $order, 'session' => $payment] = $checkout->start($booking, $currency, $booking->hold_expires_at);
        } catch (PaymentUnavailableException) {
            $booking->delete();

            return response()->json(['message' => 'Card and MoMo payment is briefly unavailable. Please try again shortly.'], 503);
        } catch (NoFxRateException) {
            $booking->delete();

            return response()->json(['message' => 'Dollar payment is briefly unavailable. Please try again shortly, or switch to cedis.'], 503);
        }

        return response()->json([
            'data' => (new BookingResource($booking->fresh('workshopSession')))->resolve(),
            'order_reference' => $order->reference,
            'payment' => $payment->toArray(),
        ], 201);
    }

    /** The ticket for a confirmed workshop booking. The signature is the permission. */
    public function ticket(Booking $booking, TicketPdf $tickets): Response
    {
        abort_unless($booking->type === 'workshop' && in_array($booking->status, ['confirmed', 'completed'], true), 404);

        return response($tickets->render($booking), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$tickets->filename($booking).'"',
        ]);
    }
}
