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
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'scheduled_slot' => $this->scheduled_slot,
            'capacity' => $this->capacity,
            'remaining_capacity' => $this->remaining_capacity,
            'location_notes' => $this->location_notes,
        ];
    }
}
