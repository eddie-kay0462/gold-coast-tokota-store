<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One line of a shipment's history. The raw carrier payload stays server-side. */
class ShipmentEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'carrier_status' => $this->carrier_status,
            'source' => $this->source,
            'message' => $this->message,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
