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
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'scheduled_slot' => $this->scheduled_slot,
            'capacity' => $this->capacity,
            'remaining_capacity' => $this->remaining_capacity,
            'location_notes' => $this->location_notes,
        ];
    }
}
