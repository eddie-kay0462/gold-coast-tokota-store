<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminWorkshopSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_date' => $this->scheduled_date,
            'scheduled_slot' => $this->scheduled_slot,
            'capacity' => $this->capacity,
            'location_notes' => $this->location_notes,
            // Computed from bookings, never stored — the same accessors the
            // storefront's session list uses, so admin and storefront can never
            // disagree about whether a session is full.
            'occupied_seats' => $this->occupied_seats,
            'remaining_capacity' => $this->remaining_capacity,
            'waitlist_count' => $this->bookings()->where('status', 'waitlisted')->count(),
            'created_at' => $this->created_at,
        ];
    }
}
