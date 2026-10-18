<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'region' => $this->region,
            'amount' => ['amount' => $this->amount_ghs, 'currency' => 'GHS'],
            'is_active' => $this->is_active,
            'updated_by' => $this->updatedBy?->name,
            'updated_at' => $this->updated_at,
        ];
    }
}
