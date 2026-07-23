<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\WorkshopSession;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request): BookingResource
    {
        $data = $request->validated();

        // DIY orders are unlimited/queue-based (Feature 7) — always accepted,
        // no capacity check, no lock needed.
        if ($data['type'] === 'diy_order') {
            $booking = Booking::create([...$data, 'status' => 'pending']);

            return new BookingResource($booking);
        }

        // Workshop bookings are capacity-limited — lock the session row so
        // concurrent submissions for the last spot serialize instead of both
        // reading the same "1 seat left" count and both landing as pending.
        $booking = DB::transaction(function () use ($data) {
            $session = WorkshopSession::query()->lockForUpdate()->findOrFail($data['workshop_session_id']);
            $status = $session->remaining_capacity > 0 ? 'pending' : 'waitlisted';

            return Booking::create([...$data, 'status' => $status]);
        });

        return new BookingResource($booking->load('workshopSession'));
    }
}
