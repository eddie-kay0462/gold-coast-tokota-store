<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZoneRate;
use App\Models\SiteSetting;
use App\Models\WhatsAppConversation;
use App\Models\WorkshopSession;
use App\Models\WorkshopType;
use App\Services\Currency\FxRateService;
use App\Support\MediaUrl;
use App\Support\PhoneNumber;
use App\Support\PublishedPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * What the person answering a WhatsApp chat needs without leaving it: who the
 * customer is, what is in stock, what is coming up, and the published rules.
 * The inbox's context panel reads these four.
 *
 * Read-only, and each gated by the capability its data would need anywhere
 * else in the admin: stock by `inventory.view`, events by `bookings.view`,
 * the customer by `customers.view`. A panel is not a way round a permission.
 *
 * Stock is *sellable* (on the shelf minus held for orders awaiting payment),
 * because "is it available?" is the question a customer is asking. It is a
 * snapshot: the hold taken when an order is built is what actually decides.
 */
class InboxLookupController extends Controller
{
    /** The customer behind a thread: their record, recent orders and bookings. */
    public function context(WhatsAppConversation $thread): JsonResponse
    {
        $contact = $thread->contact()->with('customer')->firstOrFail();
        $customer = $contact->customer;

        $orders = $this->ordersFor($contact->wa_id, $customer?->id);
        $bookings = $this->bookingsFor($contact->wa_id, $customer?->id);

        return response()->json(['data' => [
            'contact' => [
                'name' => $contact->displayName(),
                'phone' => $contact->phone_e164,
                'profile_name' => $contact->profile_name,
                'opted_out' => (bool) $contact->opted_out_at,
                'first_contact_at' => $contact->created_at,
            ],
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'preferred_currency' => $customer->preferred_currency,
                'since' => $customer->created_at,
            ] : null,
            'orders' => $orders->map(fn (Order $order) => [
                'reference' => $order->reference,
                'status' => $order->status,
                'channel' => $order->channel ?? 'web',
                'total' => $this->money($order->total, $order->currency),
                'items' => $order->items->map(fn ($i) => trim("{$i->quantity} × {$i->product_name} ".($i->variant_label ? "({$i->variant_label})" : '')))->all(),
                'shipment_status' => $order->shipment?->status,
                'tracking_reference' => $order->shipment?->carrier_reference ?? $order->delivery_reference,
                'tracking_url' => $order->shipment?->tracking_url,
                'placed_at' => $order->created_at,
                'is_linked' => $order->id === $thread->linked_order_id,
            ])->values(),
            'bookings' => $bookings->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'type' => $booking->type,
                'status' => $booking->status,
                'experience' => $booking->workshopSession?->workshopType?->name,
                'date' => $booking->workshopSession?->scheduled_date ?? $booking->scheduled_date,
                'slot' => $booking->workshopSession?->scheduled_slot,
                'attendees' => $booking->details['attendee_count'] ?? null,
                'created_at' => $booking->created_at,
            ])->values(),
        ]]);
    }

    /**
     * Products matching a name or SKU, each with its variants' sellable stock
     * and prices in both currencies.
     */
    public function stock(Request $request, FxRateService $fx): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = mb_strtolower(trim((string) $request->query('q', '')));
        $rate = $fx->getUsableRate()?->rate;

        $products = Product::query()
            ->with(['inventoryItems' => fn ($q) => $q->orderBy('id')])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$term}%"])))
            // Active first: what the customer can actually buy. Inactive ones
            // still show, flagged, so "we don't sell that any more" is an
            // answer the panel can give.
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit($term === '' ? 12 : 20)
            ->get();

        return response()->json(['data' => $products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            // To paste into the chat.
            'url' => rtrim((string) config('app.storefront_url'), '/')."/shop/{$product->slug}",
            'sku' => $product->sku,
            'is_active' => (bool) $product->is_active,
            'image' => MediaUrl::absolute(($product->images ?? [])[0] ?? null),
            'price' => $this->money($product->base_price_ghs, 'GHS'),
            'price_usd' => $rate ? $this->money((int) round($product->base_price_ghs * (float) $rate), 'USD') : null,
            'compare_at' => $product->compare_at_ghs ? $this->money($product->compare_at_ghs, 'GHS') : null,
            'sellable_total' => $product->inventoryItems->sum('sellable_quantity'),
            'variants' => $product->inventoryItems->map(fn (InventoryItem $item) => [
                'inventory_item_id' => $item->id,
                'size' => $item->variant_attributes['size'] ?? null,
                'colour' => $item->variant_attributes['colour'] ?? null,
                'label' => $this->variantLabel($item),
                'sellable' => $item->sellable_quantity,
                'on_shelf' => $item->quantity_available,
                'held' => $item->quantity_reserved,
                'low' => $item->quantity_available <= $item->low_stock_threshold,
            ])->values(),
        ])->values()]);
    }

    /**
     * Upcoming workshop sessions with seats left, the experiences that run by
     * appointment, and the latest News & Events posts.
     */
    public function events(): JsonResponse
    {
        $sessions = WorkshopSession::query()
            ->upcoming()
            ->where('scheduled_date', '<=', now()->addDays(60)->toDateString())
            ->with('workshopType:id,name,duration_label')
            ->withCount([
                'bookings as occupied' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'completed']),
                'bookings as waitlisted' => fn ($q) => $q->where('status', 'waitlisted'),
            ])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_slot')
            ->limit(30)
            ->get();

        return response()->json(['data' => [
            'sessions' => $sessions->map(fn (WorkshopSession $s) => [
                'id' => $s->id,
                'experience' => $s->workshopType?->name,
                'duration' => $s->workshopType?->duration_label,
                'date' => $s->scheduled_date?->toDateString(),
                'slot' => $s->scheduled_slot,
                'capacity' => $s->capacity,
                'remaining' => max(0, $s->capacity - (int) $s->occupied),
                'waitlisted' => (int) $s->waitlisted,
                'location_notes' => $s->location_notes,
            ])->values(),
            'by_appointment' => WorkshopType::query()->active()->where('requires_appointment', true)
                ->orderBy('sort_order')->get()
                ->map(fn (WorkshopType $t) => [
                    'name' => $t->name,
                    'days' => $t->days_label,
                    'duration' => $t->duration_label,
                    'capacity' => $t->capacity,
                ])->values(),
            'posts' => BlogPost::query()->where('is_published', true)->where('published_at', '<=', now())
                ->orderByDesc('published_at')->limit(5)->get(['title', 'slug', 'published_at'])
                ->map(fn (BlogPost $p) => [
                    'title' => $p->title,
                    'url' => rtrim((string) config('app.storefront_url'), '/')."/blog/{$p->slug}",
                    'published_at' => $p->published_at,
                ])->values(),
        ]]);
    }

    /** The published rules, delivery rates, turnaround times and hours. */
    public function info(): JsonResponse
    {
        $settings = SiteSetting::current();

        return response()->json(['data' => [
            'business_hours' => $settings->business_hours,
            'delivery' => PublishedPolicy::delivery(),
            'returns' => PublishedPolicy::returns(),
            'payment_methods' => PublishedPolicy::PAYMENT_METHODS,
            'turnaround' => collect($settings->diy_turnaround_tiers ?? [])
                ->sortBy('sort_order')
                ->map(fn (array $tier) => ['label' => $tier['label'] ?? '', 'estimate' => $tier['estimate'] ?? ''])
                ->values(),
            'rates' => [
                'accra' => $this->money((int) config('shipping.fallback_rates.accra'), 'GHS'),
                'regions' => ShippingZoneRate::query()->active()->orderBy('region')->get(['region', 'amount_ghs'])
                    ->map(fn (ShippingZoneRate $r) => ['region' => $r->region, 'rate' => $this->money($r->amount_ghs, 'GHS')])
                    ->values(),
                'international_note' => 'International delivery is quoted live by DHL Express at checkout, by destination and weight.',
            ],
        ]]);
    }

    /** @return Collection<int, Order> */
    private function ordersFor(string $waId, ?int $customerId): Collection
    {
        // Guests have no customer record, so their orders are found by the
        // phone on the shipping address, matched the same forgiving way.
        $tail = substr($waId, -4);

        return Order::query()
            ->with(['items', 'shipment'])
            ->where(function ($q) use ($customerId, $tail) {
                $q->whereRaw("COALESCE(CAST(shipping_address AS TEXT), '') LIKE ?", ["%{$tail}%"]);
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
            })
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn (Order $o) => ($customerId && $o->customer_id === $customerId)
                || PhoneNumber::same($o->shipping_address['phone'] ?? null, $waId))
            ->take(10)
            ->values();
    }

    /** @return Collection<int, Booking> */
    private function bookingsFor(string $waId, ?int $customerId): Collection
    {
        $tail = substr($waId, -4);

        return Booking::query()
            ->with('workshopSession.workshopType:id,name')
            ->where(function ($q) use ($customerId, $tail) {
                $q->whereRaw("COALESCE(CAST(details AS TEXT), '') LIKE ?", ["%{$tail}%"]);
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
            })
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn (Booking $b) => ($customerId && $b->customer_id === $customerId)
                || PhoneNumber::same($b->details['phone'] ?? null, $waId))
            ->take(10)
            ->values();
    }

    private function variantLabel(InventoryItem $item): string
    {
        $a = $item->variant_attributes ?? [];

        return implode(' · ', array_filter([
            isset($a['size']) ? "Size {$a['size']}" : null,
            $a['colour'] ?? null,
        ])) ?: 'One size';
    }

    /** @return array{amount: int, currency: string} the same money shape as every admin resource */
    private function money(int $amount, string $currency): array
    {
        return ['amount' => $amount, 'currency' => $currency];
    }
}
