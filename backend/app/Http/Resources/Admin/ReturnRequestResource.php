<?php

namespace App\Http\Resources\Admin;

use App\Services\Returns\ReturnPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $address = $this->order?->shipping_address ?? [];

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_reference' => $this->order?->reference,
            'customer_name' => $this->order?->customer?->name ?? ($address['full_name'] ?? null),
            'reason' => $this->reason,
            'status' => $this->status,
            // One line per order line, which is what the Returns table shows —
            // the full item list belongs on the order, not duplicated here.
            'items_summary' => $this->order?->items
                ->map(fn ($item) => "{$item->quantity}× {$item->product_name}")
                ->implode(', '),
            'is_eligible' => $this->is_eligible,
            'ineligible_reason' => $this->ineligible_reason,
            'refund_amount' => $this->refund_amount === null ? null : [
                'amount' => $this->refund_amount,
                'currency' => $this->order?->currency ?? 'GHS',
            ],
            'window_closes_at' => $this->window_closes_at,
            'requested_at' => $this->requested_at,
            'resolved_at' => $this->resolved_at,
            'resolved_by' => $this->resolvedByAdmin?->name,
            // §9: "Approved refunds are processed within 7-14 business days."
            // Sent so every screen quotes the published figure rather than
            // each one hard-coding its own.
            'refund_processing_days' => ReturnPolicy::REFUND_BUSINESS_DAYS,
            'notes' => $this->notes,
        ];
    }
}
