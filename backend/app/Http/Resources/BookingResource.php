<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'workshop_session' => new WorkshopSessionResource($this->whenLoaded('workshopSession')),
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'details' => $this->details,
            'created_at' => $this->created_at,
        ];
    }
}
