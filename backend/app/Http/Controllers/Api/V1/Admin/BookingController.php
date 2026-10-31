<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\NoFxRateException;
use App\Exceptions\PaymentUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;
use App\Http\Resources\Admin\AdminBookingResource;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Services\Bookings\BookingCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Admin Bookings and the waitlist (README Features 7 and 9) — Admin and Staff.
 */
class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = Booking::query()
            ->with(['customer', 'workshopSession.workshopType', 'order', 'checkedInBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when(
                $request->filled('workshop_session_id'),
                fn ($q) => $q->where('workshop_session_id', $request->integer('workshop_session_id')),
            )
            ->latest()
            ->paginate(max(1, min($request->integer('per_page', 25), 200)))
            ->withQueryString();

        return AdminBookingResource::collection($bookings);
    }

    /**
     * Promoting a waitlisted booking is the one status change that can
     * oversell: every other transition moves a seat the booking already holds.
     *
     * Locked and re-checked inside a transaction for the same reason checkout
     * is — two staff members promoting from the same waitlist at once must not
     * both succeed into the last seat.
     */
    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking, BookingCheckout $checkout): JsonResponse|AdminBookingResource
    {
        $target = $request->validated('status');
        $isPromotion = $booking->status === 'waitlisted'
            && in_array($target, ['pending', 'confirmed', 'completed'], true);

        if (! $isPromotion) {
            $wasConfirmed = $booking->status === 'confirmed';
            $booking->update(['status' => $target]);

            // Only on the transition *into* confirmed. Re-saving a booking
            // that was already confirmed — correcting a note, say — must not
            // text the customer a second time (README Feature 8 names the
            // trigger as the status *change*, not the status).
            if ($target === 'confirmed' && ! $wasConfirmed) {
                SendBookingNotification::dispatch($booking, 'confirmed');
            }

            return new AdminBookingResource($booking->load(['customer', 'workshopSession.workshopType', 'order', 'checkedInBy']));
        }

        // Room for the whole party, counted in people. At a paid event the
        // seats are held for them while they pay (OFFER_HOURS), rather than
        // given: the booking waits for payment and they're sent a pay link.
        $result = DB::transaction(function () use ($booking, $target) {
            $session = $booking->workshopSession()->lockForUpdate()->with('workshopType')->first();

            if ($session && $session->remaining_capacity < $booking->attendees()) {
                return null;
            }

            if ($session?->priceGhs() !== null) {
                $booking->update(['status' => 'awaiting_payment', 'hold_expires_at' => now()->addHours(BookingCheckout::OFFER_HOURS)]);

                return $booking;
            }

            $booking->update(['status' => $target]);

            return $booking;
        });

        if ($result === null) {
            return response()->json([
                'message' => 'That session hasn’t room for this booking. Cancel or move an existing booking, or add seats, before promoting from the waitlist.',
            ], 409);
        }

        if ($result->status === 'awaiting_payment') {
            try {
                ['session' => $payment] = $checkout->start($result, $result->details['currency'] ?? 'GHS', $result->hold_expires_at);
            } catch (PaymentUnavailableException|NoFxRateException) {
                $result->update(['status' => 'waitlisted', 'hold_expires_at' => null]);

                return response()->json(['message' => 'Payment is briefly unavailable, so the seat wasn’t offered. Try again shortly.'], 503);
            }
            SendBookingNotification::dispatch($result, 'waitlist_promoted', $payment->authorizationUrl);

            return new AdminBookingResource($result->load(['customer', 'workshopSession.workshopType', 'order']));
        }

        // The waitlist-promotion trigger. Dispatched after the transaction
        // commits, so the worker cannot read the booking before it exists;
        // and only on a genuine promotion, which is what $isPromotion above
        // has already established.
        SendBookingNotification::dispatch($result, 'waitlist_promoted');

        return new AdminBookingResource($result->load(['customer', 'workshopSession.workshopType', 'order', 'checkedInBy']));
    }

    /**
     * The booking behind a ticket code, for the check-in screen. The code is
     * what the ticket's QR code opens, or what someone reads off it.
     */
    public function findByTicket(string $code): AdminBookingResource|JsonResponse
    {
        $booking = Booking::query()
            ->where('ticket_code', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''))
            ->with(['customer', 'workshopSession.workshopType', 'order', 'checkedInBy'])
            ->first();

        return $booking
            ? new AdminBookingResource($booking)
            : response()->json(['message' => 'No ticket has that code. Check it against the ticket, or search Bookings by name.'], 404);
    }

    /** At the door. Only a confirmed booking, and only once. */
    public function checkIn(Request $request, Booking $booking): AdminBookingResource|JsonResponse
    {
        $result = DB::transaction(function () use ($request, $booking) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($locked->checked_in_at) {
                return 'already';
            }
            if (! in_array($locked->status, ['confirmed', 'completed'], true)) {
                return 'not_confirmed';
            }
            $locked->update(['checked_in_at' => now(), 'checked_in_by_admin_id' => $request->user('admin')->id]);

            return $locked;
        });

        if ($result === 'already') {
            $booking->refresh()->load('checkedInBy');

            return response()->json([
                'message' => 'Already checked in '.$booking->checked_in_at?->format('g:ia, j M')
                    .($booking->checkedInBy ? ' by '.$booking->checkedInBy->name : '').'.',
            ], 409);
        }
        if ($result === 'not_confirmed') {
            return response()->json([
                'message' => $booking->status === 'awaiting_payment' || $booking->status === 'expired'
                    ? 'This booking hasn’t been paid for.'
                    : 'This booking is '.str_replace('_', ' ', $booking->status).', not confirmed.',
            ], 422);
        }

        return new AdminBookingResource($result->load(['customer', 'workshopSession.workshopType', 'order', 'checkedInBy']));
    }

    /** The ticket email again, for a confirmed workshop booking. */
    public function resendTicket(Booking $booking): JsonResponse
    {
        if ($booking->type !== 'workshop' || ! in_array($booking->status, ['confirmed', 'completed'], true)) {
            return response()->json(['message' => 'Only a confirmed workshop booking has a ticket to send.'], 422);
        }

        SendBookingNotification::dispatch($booking, 'confirmed');

        return response()->json(['message' => 'Ticket sent.']);
    }
}
