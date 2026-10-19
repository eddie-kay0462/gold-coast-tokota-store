<?php

namespace App\Services\WhatsApp;

use App\Exceptions\WhatsAppSendRefused;
use App\Models\AdminUser;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\ShippingZoneRate;
use App\Models\SiteSetting;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppOrderDraft;
use App\Models\WhatsAppTemplate;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Currency\FxRateService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Payment\PaymentSession;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Order on WhatsApp: the part of the channel that sells.
 *
 * A WhatsApp order is an ordinary order. The team member builds it in the
 * inbox, and it goes through CheckoutSessionService exactly as a web checkout
 * does: re-priced from the database, FX-locked, stock held, a Paystack
 * session opened. Only the channel and the length of the hold differ. The
 * customer pays from a link in the chat, and the Paystack webhook marks it
 * paid. From there `OrderPaid` runs as for any order (stock finalised,
 * shipment opened, confirmation sent), so stock, the movement ledger, the
 * orders list and the dashboard all see one kind of order.
 */
class WhatsAppOrders
{
    public function __construct(
        private readonly CheckoutSessionService $checkout,
        private readonly InventoryReservationService $reservations,
        private readonly WhatsAppOutbox $outbox,
        private readonly FxRateService $fx,
    ) {}

    /**
     * A storefront cart carried into a chat. Nothing is held: see the
     * migration for why a public endpoint must not hold stock.
     *
     * @param  array<int, array{inventory_item_id: int, quantity: int}>  $items
     */
    public function draft(array $items, string $currency): WhatsAppOrderDraft
    {
        return WhatsAppOrderDraft::create([
            'reference' => WhatsAppOrderDraft::newReference(),
            'items' => array_values($items),
            'currency' => $currency,
            'expires_at' => now()->addDays(WhatsAppOrderDraft::LIFETIME_DAYS),
        ]);
    }

    /**
     * Links a chat to the draft whose code the customer's message carries.
     * Returns the draft, or null when the message names none (or one that
     * has expired, been used, or belongs to another chat).
     */
    public function linkDraftFromMessage(WhatsAppMessage $message): ?WhatsAppOrderDraft
    {
        if (! preg_match(WhatsAppOrderDraft::PATTERN, (string) $message->body, $m)) {
            return null;
        }

        return DB::transaction(function () use ($m, $message) {
            $draft = WhatsAppOrderDraft::query()->open()
                ->where('reference', 'WA-'.strtoupper($m[1]))
                ->lockForUpdate()
                ->first();

            if (! $draft || ($draft->conversation_id && $draft->conversation_id !== $message->conversation_id)) {
                return null;
            }

            $draft->update(['conversation_id' => $message->conversation_id]);
            WhatsAppConversation::query()->whereKey($message->conversation_id)->update(['topic' => 'order']);

            return $draft;
        });
    }

    /**
     * What the order builder opens with: the chat's latest open draft priced
     * at today's figures with live stock, and the customer's details from
     * their account or their last order.
     *
     * @return array<string, mixed>
     */
    public function builderFor(WhatsAppConversation $conversation): array
    {
        $contact = $conversation->contact()->with('customer')->firstOrFail();
        $draft = WhatsAppOrderDraft::query()->open()->where('conversation_id', $conversation->id)->latest()->first();
        $lastOrder = $this->lastOrderFor($contact->wa_id, $contact->customer_id);
        $address = $lastOrder?->shipping_address ?? [];

        return [
            'draft' => $draft ? [
                'reference' => $draft->reference,
                'currency' => $draft->currency,
                'created_at' => $draft->created_at,
                'lines' => $this->priceLines($draft->items),
            ] : null,
            'prefill' => [
                'full_name' => $contact->customer?->name ?? ($address['full_name'] ?? $contact->profile_name),
                'email' => $contact->customer?->email ?? ($address['email'] ?? null),
                'phone' => $contact->phone_e164,
                'line1' => $address['line1'] ?? null,
                'city' => $address['city'] ?? null,
                'region' => $address['region'] ?? null,
                'postcode' => $address['postcode'] ?? null,
                'country' => $address['country'] ?? 'GH',
                'from_last_order' => $lastOrder?->reference,
            ],
            'currency' => $draft?->currency ?? ($contact->customer?->preferred_currency ?: 'GHS'),
            // For the address form: Ghana is priced by region off the Accra pin zone.
            'regions' => ShippingZoneRate::REGIONS,
            'hold_hours' => $this->holdHours(),
            'window_open' => $conversation->serviceWindowOpen(),
            'payment_template_ready' => $this->paymentTemplate() !== null,
            'open_orders' => Order::query()
                ->where('whatsapp_conversation_id', $conversation->id)
                ->where('status', 'pending')
                ->latest()
                ->get()
                ->map(fn (Order $o) => $this->summary($o))
                ->values(),
        ];
    }

    /**
     * Places the order and sends its payment link.
     *
     * Returns the order, the payment session, the chat message (null when
     * none could be sent) and, when the link could not go out by itself, a
     * plain explanation and the link to pass on by hand.
     *
     * @param  array<string, mixed>  $data  validated items, currency, shipping address and quote
     * @return array{order: Order, session: PaymentSession, message: ?WhatsAppMessage, warning: ?string}
     */
    public function place(WhatsAppConversation $conversation, array $data, AdminUser $by): array
    {
        $contact = $conversation->contact;

        ['order' => $order, 'session' => $session] = $this->checkout->create([
            ...$data,
            'customer_id' => $contact->customer_id,
        ], [
            'channel' => 'whatsapp',
            'hold_minutes' => $this->holdHours() * 60,
            'created_by_admin_id' => $by->id,
            'whatsapp_conversation_id' => $conversation->id,
        ]);

        DB::transaction(function () use ($conversation, $order, $data) {
            $conversation->update(['linked_order_id' => $order->id, 'topic' => 'order']);

            if (! empty($data['draft_reference'])) {
                WhatsAppOrderDraft::query()
                    ->where('reference', $data['draft_reference'])
                    ->where('conversation_id', $conversation->id)
                    ->whereNull('converted_order_id')
                    ->update(['converted_order_id' => $order->id]);
            }
        });

        return ['order' => $order, 'session' => $session, ...$this->sendLink($conversation, $order, $session, $by)];
    }

    /**
     * A new payment link for an order still awaiting payment, e.g. when the
     * customer says the old one stopped working. Same order, same hold.
     *
     * @return array{order: Order, session: PaymentSession, message: ?WhatsAppMessage, warning: ?string}
     */
    public function resendLink(Order $order, AdminUser $by): array
    {
        $this->requireAwaitingPayment($order);

        $session = $this->checkout->reopenPayment($order);
        $conversation = WhatsAppConversation::query()->with('contact')->findOrFail($order->whatsapp_conversation_id);

        return ['order' => $order->fresh(), 'session' => $session, ...$this->sendLink($conversation, $order, $session, $by)];
    }

    /** Calls off an order awaiting payment and gives its stock back at once. */
    public function cancel(Order $order, AdminUser $by): Order
    {
        $this->requireAwaitingPayment($order);

        DB::transaction(function () use ($order, $by) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'pending') {
                throw new WhatsAppSendRefused('This order is no longer awaiting payment, so it can’t be cancelled from here.', 'not_pending');
            }

            $locked->update(['status' => 'cancelled']);
            $this->reservations->releaseOrder($locked, "Cancelled from the WhatsApp inbox by {$by->name}");
        });

        return $order->fresh();
    }

    /** @return array<string, mixed> */
    public function summary(Order $order): array
    {
        return [
            'reference' => $order->reference,
            'status' => $order->status,
            'total' => ['amount' => $order->total, 'currency' => $order->currency],
            'held_until' => $order->reservation_expires_at,
            'payment_reference' => $order->payment_reference,
            'placed_at' => $order->created_at,
        ];
    }

    /**
     * Inside the 24-hour window: a message with a "Pay" button. Outside it:
     * the approved `payment_link` template, if there is one. Otherwise the
     * order stands and the link is handed back for the team to pass on.
     *
     * @return array{message: ?WhatsAppMessage, warning: ?string}
     */
    private function sendLink(WhatsAppConversation $conversation, Order $order, PaymentSession $session, AdminUser $by): array
    {
        $url = (string) $session->authorizationUrl;
        $order->loadMissing('items');

        try {
            if ($conversation->serviceWindowOpen()) {
                return ['message' => $this->outbox->linkButton(
                    $conversation,
                    $this->linkMessage($order),
                    $this->buttonText($order),
                    $url,
                    $by,
                ), 'warning' => null];
            }

            if ($template = $this->paymentTemplate()) {
                // The template is the client's to write in WhatsApp Manager,
                // with four values in this order: name, order number, total, link.
                return ['message' => $this->outbox->template($conversation, $template, [
                    $this->firstName($order),
                    $order->reference,
                    $this->money($order->total, $order->currency),
                    $url,
                ], $by), 'warning' => null];
            }
        } catch (WhatsAppSendRefused $e) {
            return ['message' => null, 'warning' => "The order is placed, but the link wasn’t sent: {$e->getMessage()} Copy the link below and send it another way."];
        }

        return ['message' => null, 'warning' => 'The order is placed, but the 24-hour reply window has closed and there is no approved “payment_link” template, so the link wasn’t sent. Copy it below and send it another way.'];
    }

    /** The words above the Pay button. Plain, in the customer-service voice (§7). */
    private function linkMessage(Order $order): string
    {
        $lines = $order->items->map(fn ($i) => "• {$i->quantity} × {$i->product_name}".($i->variant_label ? " ({$i->variant_label})" : ''));
        $delivery = $order->shipping_cost > 0 ? "Delivery: {$this->money($order->shipping_cost, $order->currency)}\n" : '';
        $heldUntil = $order->reservation_expires_at?->copy()->setTimezone('Africa/Accra')->format('D j M, g:i A');

        return implode("\n", [
            "Here is your order {$order->reference}:",
            ...$lines,
            '',
            rtrim($delivery."Total: {$this->money($order->total, $order->currency)}"),
            '',
            'Tap the button to pay by card, mobile money or bank transfer through Paystack.'
                .($heldUntil ? " We’re holding your items until {$heldUntil}." : ''),
        ]);
    }

    /** WhatsApp caps button text at 20 characters. */
    private function buttonText(Order $order): string
    {
        $text = 'Pay '.$this->money($order->total, $order->currency);

        return mb_strlen($text) <= 20 ? $text : 'Pay now';
    }

    private function firstName(Order $order): string
    {
        $name = trim((string) ($order->shipping_address['full_name'] ?? ''));

        return $name !== '' ? explode(' ', $name)[0] : 'there';
    }

    private function paymentTemplate(): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::query()
            ->where('name', config('whatsapp.templates.payment_link', 'payment_link'))
            ->where('status', 'approved')
            ->first();
    }

    private function holdHours(): int
    {
        return max(1, min(72, (int) (SiteSetting::current()->whatsapp_hold_hours ?? 24)));
    }

    private function requireAwaitingPayment(Order $order): void
    {
        if ($order->channel !== 'whatsapp' || ! $order->whatsapp_conversation_id) {
            throw new WhatsAppSendRefused('Only an order placed from the WhatsApp inbox can be managed from here.', 'not_whatsapp');
        }

        if ($order->status !== 'pending') {
            throw new WhatsAppSendRefused('This order is no longer awaiting payment.', 'not_pending');
        }
    }

    /**
     * @param  array<int, array{inventory_item_id: int, quantity: int}>  $items
     * @return list<array<string, mixed>>
     */
    private function priceLines(array $items): array
    {
        $rate = $this->fx->getUsableRate()?->rate;
        $rows = InventoryItem::query()->with('product')->whereIn('id', array_column($items, 'inventory_item_id'))->get()->keyBy('id');

        return collect($items)->map(function (array $line) use ($rows, $rate) {
            /** @var InventoryItem|null $item */
            $item = $rows[$line['inventory_item_id']] ?? null;
            $product = $item?->product;
            $attributes = $item?->variant_attributes ?? [];

            return [
                'inventory_item_id' => (int) $line['inventory_item_id'],
                'quantity' => (int) $line['quantity'],
                'product_name' => $product?->name,
                'variant_label' => implode(' | ', array_filter([$attributes['size'] ?? null, $attributes['colour'] ?? null])) ?: null,
                'unit_price' => $product ? ['amount' => $product->base_price_ghs, 'currency' => 'GHS'] : null,
                'unit_price_usd' => $product && $rate ? ['amount' => (int) round($product->base_price_ghs * (float) $rate), 'currency' => 'USD'] : null,
                'sellable' => $item?->sellable_quantity ?? 0,
                // Why a line can't go into an order as it stands.
                'problem' => match (true) {
                    ! $item || ! $product => 'This item no longer exists.',
                    ! $product->is_active => 'This product is no longer on sale.',
                    $item->sellable_quantity < (int) $line['quantity'] => $item->sellable_quantity === 0
                        ? 'Sold out.'
                        : "Only {$item->sellable_quantity} available.",
                    default => null,
                },
            ];
        })->values()->all();
    }

    private function lastOrderFor(string $waId, ?int $customerId): ?Order
    {
        $tail = substr($waId, -4);

        return Order::query()
            ->where(function ($q) use ($customerId, $tail) {
                $q->whereRaw("COALESCE(CAST(shipping_address AS TEXT), '') LIKE ?", ["%{$tail}%"]);
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
            })
            ->latest()
            ->limit(20)
            ->get()
            ->first(fn (Order $o) => ($customerId && $o->customer_id === $customerId)
                || PhoneNumber::same($o->shipping_address['phone'] ?? null, $waId));
    }

    private function money(int $minor, string $currency): string
    {
        $symbol = $currency === 'USD' ? '$' : 'GH₵';

        return $symbol.number_format($minor / 100, 2);
    }
}
