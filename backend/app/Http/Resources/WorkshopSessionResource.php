<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkshopSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Without these a session is an anonymous date and seat count —
            // the booking page had no way to say which of the six experiences
            // in §15 it was offering.
            'workshop_type_id' => $this->workshop_type_id,
            'workshop_type' => $this->whenLoaded('workshopType', fn () => [
                'id' => $this->workshopType->id,
                'name' => $this->workshopType->name,
                'slug' => $this->workshopType->slug,
                'duration_label' => $this->workshopType->duration_label,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            // What to call it: its own title, or the experience it belongs to.
            'event_name' => $this->title ?: $this->workshopType?->name,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'scheduled_slot' => $this->scheduled_slot,
            'capacity' => $this->capacity,
            // What a place costs on this date, per person, in pesewas: its own
            // price or its event's. Null is free. Show it in the visitor's
            // currency the way product prices are.
            'price_ghs' => $this->priceGhs(),
            // A full session with no waitlist is sold out.
            'allow_waitlist' => (bool) $this->workshopType?->allow_waitlist,
            'remaining_capacity' => $this->remaining_capacity,
            'location_notes' => $this->location_notes,
        ];
    }
}
