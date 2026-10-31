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
            'workshop_type_id' => $this->workshop_type_id,
            'workshop_type_name' => $this->whenLoaded('workshopType', fn () => $this->workshopType->name),
            'title' => $this->title,
            'description' => $this->description,
            'event_name' => $this->title ?: $this->workshopType?->name,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'scheduled_slot' => $this->scheduled_slot,
            'capacity' => $this->capacity,
            // `price_ghs` is this date's own, or null; `effective_price_ghs`
            // is what's actually charged (its own, else the event's).
            'price_ghs' => $this->price_ghs,
            'effective_price_ghs' => $this->priceGhs(),
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
