<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkshopTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'days_label' => $this->days_label,
            'slot_label' => $this->slot_label,
            'duration_label' => $this->duration_label,
            'capacity' => $this->capacity,
            // The three §15 runs "By Appointment" have no standing schedule,
            // so a client offering seats for them would be offering seats that
            // do not exist. This is the flag to branch on, not the days label.
            'requires_appointment' => $this->requires_appointment,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'upcoming_sessions_count' => $this->whenCounted('sessions'),
        ];
    }
}
