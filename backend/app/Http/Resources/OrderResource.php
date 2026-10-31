<?php

namespace App\Http\Resources;

use App\Services\Bookings\TicketPdf;
use App\Services\Delivery\DeliveryEstimates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            // `goods`, or `gift_card` for a gift card bought online, which has
            // no delivery.
            'kind' => $this->kind ?? 'goods',
            'status' => $this->status,
            'currency' => $this->currency,
            // Null for GHS orders by definition — there is nothing to convert.
            'fx_rate_applied' => $this->fx_rate_applied ? (float) $this->fx_rate_applied : null,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'tax' => $this->tax,
            // Taken off by a gift card or store credit, in the order's currency.
            'gift_card_amount' => (int) $this->gift_card_amount,
            'gift_card_last_four' => $this->whenLoaded('giftCard', fn () => $this->giftCard?->lastFour()),
            'total' => $this->total,
            'payment_gateway' => $this->payment_gateway,
            'delivery_provider' => $this->delivery_provider,
            'delivery_reference' => $this->delivery_reference,
            // The live booking, as far as the customer needs it: where it is
            // and a link to follow it. Null until the order has shipped.
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment ? [
                'status' => $this->shipment->status->value,
                'courier' => $this->shipment->courier_name,
                'tracking_url' => $this->shipment->tracking_url,
                'dispatched_at' => $this->shipment->dispatched_at,
                'delivered_at' => $this->shipment->delivered_at,
            ] : null),
            'shipping_address' => (object) $this->shipping_address,
            // §8's published promises, resolved for this order's destination.
            // Sent rather than left to the storefront so the confirmation
            // page, the shipping policy page and the admin screen cannot
            // quote three different figures.
            'processing_hours' => DeliveryEstimates::PROCESSING_HOURS,
            'delivery_estimate' => DeliveryEstimates::for($this->shipping_address['country'] ?? null),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            // Tickets for a paid event: what they're for, and the ticket
            // itself once the payment has confirmed the booking.
            'booking' => $this->when(
                $this->kind === 'booking' && $this->relationLoaded('booking') && $this->booking !== null,
                fn () => [
                    'reference' => $this->booking->reference(),
                    'status' => $this->booking->status,
                    'event' => $this->booking->workshopSession?->eventName(),
                    'scheduled_date' => $this->booking->workshopSession?->scheduled_date?->toDateString(),
                    'scheduled_slot' => $this->booking->workshopSession?->scheduled_slot,
                    'location_notes' => $this->booking->workshopSession?->location_notes,
                    'attendee_count' => $this->booking->attendees(),
                    'ticket_url' => in_array($this->booking->status, ['confirmed', 'completed'], true)
                        ? app(TicketPdf::class)->downloadUrl($this->booking) : null,
                ],
            ),
            'created_at' => $this->created_at,
        ];
    }
}
