<?php

namespace App\Services\WhatsApp;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\WhatsAppConversation;
use App\Models\WorkshopSession;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Returns\ReturnPolicy;
use App\Support\BusinessHours;
use Illuminate\Support\Str;

/**
 * Fills a quick reply's {slots} from the chat it is used in.
 *
 * Every value comes from the database or from the published policy (the
 * same constants the website quotes), so a saved reply can't drift from the
 * real figure. A slot with nothing to fill it, such as {order.reference} in
 * a chat with no linked order, becomes a visible placeholder ("[order
 * number]") and is reported back, so the person sending sees the gap rather
 * than a sentence with a hole in it.
 */
class QuickReplyRenderer
{
    /** slot => [label for the placeholder and the help text, description] */
    public const SLOTS = [
        'customer.first_name' => ['first name', 'The customer’s first name'],
        'order.reference' => ['order number', 'The order linked to this chat'],
        'order.status' => ['order status', 'Its status, in words'],
        'order.total' => ['order total', 'Its total'],
        'order.tracking' => ['tracking link', 'Its tracking link or number, once dispatched'],
        'delivery.region' => ['destination', 'Where the linked order is going (Ghana if none)'],
        'delivery.estimate' => ['delivery time', 'The published delivery time there (§21)'],
        'returns.window' => ['return window', 'The return window (§21: 7 days)'],
        'session.next' => ['next workshop', 'The next workshop session with places left'],
        'hours' => ['business hours', 'Business hours (§14)'],
    ];

    /** @return array{body: string, missing: list<string>} */
    public function render(string $body, WhatsAppConversation $conversation): array
    {
        $conversation->loadMissing(['contact.customer', 'linkedOrder.shipment']);
        $order = $conversation->linkedOrder;
        $missing = [];

        $rendered = preg_replace_callback('/\{([a-z_.]+)\}/', function (array $m) use ($conversation, $order, &$missing) {
            $slot = $m[1];

            if (! isset(self::SLOTS[$slot])) {
                return $m[0]; // not a slot: leave the braces alone
            }

            $value = $this->value($slot, $conversation, $order);

            if ($value === null || $value === '') {
                $missing[] = $slot;

                return '['.self::SLOTS[$slot][0].']';
            }

            return $value;
        }, $body) ?? $body;

        return ['body' => $rendered, 'missing' => array_values(array_unique($missing))];
    }

    private function value(string $slot, WhatsAppConversation $conversation, ?Order $order): ?string
    {
        $country = $order?->shipping_address['country'] ?? 'GH';

        return match ($slot) {
            'customer.first_name' => $this->firstName($conversation, $order),
            'order.reference' => $order?->reference,
            'order.status' => $order ? Str::lower(Str::headline($order->status)) : null,
            'order.total' => $order ? $this->money($order->total, $order->currency) : null,
            'order.tracking' => $order?->shipment?->tracking_url ?? $order?->shipment?->carrier_reference ?? $order?->delivery_reference,
            'delivery.region' => $country === 'GH' ? 'Ghana' : ($order?->shipping_address['city'] ?? $country),
            'delivery.estimate' => DeliveryEstimates::for($country),
            'returns.window' => ReturnPolicy::WINDOW_DAYS.' days',
            'session.next' => $this->nextSession(),
            'hours' => BusinessHours::from(SiteSetting::current()->whatsapp_hours)->describe(),
            default => null,
        };
    }

    private function firstName(WhatsAppConversation $conversation, ?Order $order): ?string
    {
        $name = $conversation->contact?->customer?->name
            ?? $order?->shipping_address['full_name']
            ?? $conversation->contact?->profile_name;

        $first = trim(explode(' ', trim((string) $name))[0] ?? '');

        return $first !== '' ? $first : null;
    }

    private function nextSession(): ?string
    {
        $session = WorkshopSession::query()
            ->upcoming()
            ->with('workshopType:id,name')
            ->withCount(['bookings as occupied' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'completed'])])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_slot')
            ->get()
            ->first(fn (WorkshopSession $s) => $s->capacity > $s->occupied);

        if (! $session) {
            return null;
        }

        $left = $session->capacity - $session->occupied;

        return sprintf(
            '%s on %s, %s (%d %s left)',
            $session->workshopType?->name ?? 'a workshop',
            $session->scheduled_date->format('l j F'),
            $session->scheduled_slot,
            $left,
            $left === 1 ? 'place' : 'places',
        );
    }

    private function money(int $minor, string $currency): string
    {
        return ($currency === 'USD' ? '$' : 'GH₵').number_format($minor / 100, 2);
    }
}
