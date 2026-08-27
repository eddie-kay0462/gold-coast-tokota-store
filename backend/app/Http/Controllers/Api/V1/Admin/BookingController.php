<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;
use App\Http\Resources\Admin\AdminBookingResource;
use App\Models\Booking;
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
            ->with(['customer', 'workshopSession'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when(
                $request->filled('workshop_session_id'),
                fn ($q) => $q->where('workshop_session_id', $request->integer('workshop_session_id')),
            )
            ->latest()
            ->paginate(25)
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
    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): JsonResponse|AdminBookingResource
    {
        $target = $request->validated('status');
        $isPromotion = $booking->status === 'waitlisted'
            && in_array($target, ['pending', 'confirmed', 'completed'], true);

        if (! $isPromotion) {
            $booking->update(['status' => $target]);

            return new AdminBookingResource($booking->load(['customer', 'workshopSession']));
        }

        $result = DB::transaction(function () use ($booking, $target) {
            $session = $booking->workshopSession()->lockForUpdate()->first();

            if ($session && $session->remaining_capacity < 1) {
                return null;
            }

            $booking->update(['status' => $target]);

            return $booking;
        });

        if ($result === null) {
            return response()->json([
                'message' => 'That session is full. Cancel or move an existing booking before promoting from the waitlist.',
            ], 409);
        }

        return new AdminBookingResource($result->load(['customer', 'workshopSession']));
    }
}
