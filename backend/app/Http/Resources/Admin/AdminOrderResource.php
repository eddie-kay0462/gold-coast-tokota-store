<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Orders as the admin table needs them — deliberately not the customer-facing
 * OrderResource.
 *
 * Two real differences, not stylistic ones:
 *   1. Money is `{ amount, currency }` here. The admin app's `Money` type makes
 *      the pair inseparable so a bare number can never be mistaken for a price;
 *      the storefront reads bare minor units alongside a currency it already
 *      knows.
 *   2. `payment_reference` is present. It is withheld from customers because it
 *      is the gateway's internal id, but it is exactly what an admin needs to
 *      reconcile against a Paystack or Stripe dashboard.
 */
class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $address = $this->shipping_address ?? [];

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'customer_id' => $this->customer_id,
            // Falls back to the shipping address for guests, who have no
            // Customer row at all — the table still has to name someone.
            'customer_name' => $this->customer?->name ?? ($address['full_name'] ?? null),
            'customer_email' => $this->customer?->email ?? ($address['email'] ?? null),
            'is_guest' => $this->customer_id === null,

            'currency' => $this->currency,
            'fx_rate_applied' => $this->fx_rate_applied ? (float) $this->fx_rate_applied : null,
            'subtotal' => $this->money($this->subtotal),
            'shipping_cost' => $this->money($this->shipping_cost),
            'tax' => $this->money($this->tax),
            'total' => $this->money($this->total),

            'status' => $this->status,
            'payment_gateway' => $this->payment_gateway,
            'payment_reference' => $this->payment_reference,
            'delivery_provider' => $this->delivery_provider,
            'delivery_reference' => $this->delivery_reference,
            'shipping_address' => (object) $address,

            'items' => AdminOrderItemResource::collection($this->whenLoaded('items')),
            'placed_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array{amount: int, currency: string} */
    private function money(int $amount): array
    {
        return ['amount' => $amount, 'currency' => $this->currency];
    }
}
