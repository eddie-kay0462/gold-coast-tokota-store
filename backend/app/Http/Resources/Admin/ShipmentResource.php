<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipment as the admin order screen shows it: where it is, who has it, what
 * it was quoted and what it cost. Money as `{ amount, currency }`, like the
 * rest of the admin API.
 */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'zone' => $this->zone?->value,
            'carrier' => $this->carrier,
            'service_code' => $this->service_code,
            'attempt' => $this->attempt,
            'status' => $this->status?->value,
            'carrier_status' => $this->carrier_status,
            'carrier_reference' => $this->carrier_reference,
            'tracking_url' => $this->tracking_url,
            'pickup_reference' => $this->pickup_reference,
            'courier_name' => $this->courier_name,
            'notes' => $this->notes,
            'failure_reason' => $this->failure_reason,
            'quoted_cost' => $this->money($this->quoted_cost, $this->quoted_currency),
            'actual_cost' => $this->money($this->actual_cost, $this->actual_currency),
            'has_label' => $this->label_path !== null,
            'has_invoice' => $this->invoice_path !== null,
            'packed_at' => $this->packed_at,
            'dispatched_at' => $this->dispatched_at,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'events' => ShipmentEventResource::collection($this->whenLoaded('events')),
        ];
    }

    /** @return array{amount: int, currency: string}|null */
    private function money(?int $amount, ?string $currency): ?array
    {
        return $amount === null || $currency === null ? null : ['amount' => $amount, 'currency' => $currency];
    }
}
