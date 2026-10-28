<?php

namespace App\Services\Checkout;

use App\Events\OrderPaid;
use App\Exceptions\GiftCardUnusableException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\NoFxRateException;
use App\Exceptions\RegionNotServedException;
use App\Exceptions\ShippingOptionChangedException;
use App\Exceptions\UnavailableProductException;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Currency\FxRateService;
use App\Services\GiftCards\GiftCardService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentSession;
use App\Services\Shipping\CheckoutShipping;
use App\Support\OrderReference;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns a validated cart into a priced, FX-locked, stock-reserved Order plus an
 * open payment session (README Feature 4).
 *
 * Order of operations matters and is not arbitrary:
 *
 *   1. Re-price from the database. Nothing about money is taken from the
 *      request — a client that posts its own prices is a client that can set
 *      them.
 *   2. Price delivery from the quote the customer was shown, so the figure
 *      shown is the figure charged (see CheckoutShipping).
 *   3. Lock the FX rate for USD orders, once, so the whole order converts on
 *      one rate.
 *   4. Reserve stock, inside a transaction, with the row locks
 *      InventoryReservationService already provides.
 *   5. Only then, once that transaction has committed, open a gateway session.
 *
 * A gift card code, when given, is spent in the same transaction as the stock
 * holds, on the cedi total, and comes off the order before it converts. An
 * order the card covers in full needs no payment session: it is paid on the
 * spot and the customer goes straight to the confirmation page.
 *
 * Steps 3–4 are one transaction: if any throws, the Order and its
 * reservations roll away together. Steps 1–2 run before it, because a live
 * courier quote is a network call and must not hold stock rows locked. Step 5 is a network call to Paystack that
 * can take tens of seconds, so it runs after the commit — inside, it held the
 * stock rows locked, and every other checkout for the same size waited on it.
 * If it fails, the holds are released and the Order deleted by hand. Either
 * way an abandoned checkout leaves no stock held and no orphan order behind,
 * which is a stated Feature 4 acceptance criterion.
 */
class CheckoutSessionService
{
    /** How long stock is held for a checkout in progress (README Feature 3). */
    private const RESERVATION_TTL_MINUTES = 15;

    public function __construct(
        private readonly FxRateService $fxRates,
        private readonly InventoryReservationService $reservations,
        private readonly CheckoutShipping $shipping,
        private readonly PaymentGatewayFactory $gateways,
        private readonly GiftCardService $giftCards,
    ) {}

    /**
     * `$options` is how an order built by a team member in the WhatsApp
     * inbox goes through the same path as a web checkout: same re-pricing,
     * FX lock, stock holds and payment session, with its own channel and a
     * longer hold (the customer pays from a link in the chat).
     *
     * @param  array{items: array<int, array{inventory_item_id: int, quantity: int}>, currency: string, shipping_address: array<string, mixed>, shipping_quote_id?: string|null, shipping_option_id?: string|null, customer_id?: int|null, gift_card_code?: string|null}  $data
     * @param  array{channel?: string, hold_minutes?: int, created_by_admin_id?: int|null, whatsapp_conversation_id?: int|null}  $options
     * @return array{order: Order, session: PaymentSession}
     *
     * @throws InsufficientStockException
     * @throws RegionNotServedException
     * @throws ShippingOptionChangedException
     * @throws GiftCardUnusableException
     */
    public function create(array $data, array $options = []): array
    {
        $expiresAt = now()->addMinutes($options['hold_minutes'] ?? self::RESERVATION_TTL_MINUTES);

        // Resolved before anything is reserved: a production API with no
        // Paystack key refuses here, holding nothing.
        $gateway = $this->gateways->for($data['currency']);

        $lines = $this->priceLines($data['items']);
        $subtotal = array_sum(array_map(
            fn (array $line) => $line['unit_price'] * $line['quantity'],
            $lines,
        ));

        $quoteRequest = $this->shipping->request($data['shipping_address'], $lines, $subtotal);
        $delivery = $this->shipping->choose(
            $quoteRequest,
            $data['shipping_quote_id'] ?? null,
            $data['shipping_option_id'] ?? null,
        );

        $order = DB::transaction(function () use ($data, $options, $expiresAt, $lines, $subtotal, $quoteRequest, $delivery) {
            $shippingCost = $delivery->amountGhs;

            // One rate read for the whole order. Two reads could straddle the
            // hourly refresh and convert the subtotal and the shipping on
            // different rates.
            $fxRate = $data['currency'] === 'USD'
                // getUsableRate(), not getCachedRate(): a rate old enough to
                // be stale is a real loss on every unit sold, and refusing the
                // order is cheaper than honouring a week-old cedi.
                ? ($this->fxRates->getUsableRate()?->rate ?? throw new NoFxRateException)
                : null;

            $order = Order::create([
                'reference' => OrderReference::forOrder(),
                'customer_id' => $data['customer_id'] ?? null,
                'currency' => $data['currency'],
                'fx_rate_applied' => $fxRate,
                'subtotal' => $this->convert($subtotal, $fxRate),
                'shipping_cost' => $this->convert($shippingCost, $fxRate),
                // No tax line is charged today. The column exists because the
                // README's Order model has it; when VAT/NHIL is configured it
                // is computed here, before the total.
                'tax' => 0,
                'total' => $this->convert($subtotal + $shippingCost, $fxRate),
                'status' => 'pending',
                'payment_gateway' => null,
                'delivery_provider' => $delivery->carrier,
                'shipping_zone' => $quoteRequest->zone,
                'shipping_option' => $delivery->snapshot(),
                'shipping_address' => $data['shipping_address'],
                'reservation_expires_at' => $expiresAt,
                'channel' => $options['channel'] ?? 'web',
                'created_by_admin_id' => $options['created_by_admin_id'] ?? null,
                'whatsapp_conversation_id' => $options['whatsapp_conversation_id'] ?? null,
            ]);

            foreach ($lines as $line) {
                // Reserve before writing the line, so a stock failure aborts
                // the whole transaction rather than leaving a paid-for order
                // item with nothing behind it.
                $this->reservations->reserve($line['inventory_item'], $line['quantity'], $expiresAt, $order);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'inventory_item_id' => $line['inventory_item']->id,
                    'product_name' => $line['product']->name,
                    'variant_label' => $line['variant_label'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $this->convert($line['unit_price'], $fxRate),
                    'currency' => $data['currency'],
                ]);
            }

            $code = trim((string) ($data['gift_card_code'] ?? ''));
            if ($code !== '') {
                $grossGhs = $subtotal + $shippingCost;
                $appliedGhs = $this->giftCards->redeem($code, $order, $grossGhs);
                $gross = $this->convert($grossGhs, $fxRate);
                $total = $this->convert($grossGhs - $appliedGhs, $fxRate);

                $order->forceFill([
                    'total' => $total,
                    'gift_card_amount' => $gross - $total,
                ])->save();
            }

            return $order;
        });

        if ($order->total === 0) {
            return ['order' => $this->paidByGiftCard($order), 'session' => $this->giftCardSession($order)];
        }

        try {
            $session = $gateway->createSession($order);
        } catch (Throwable $e) {
            $this->abandon($order);

            throw $e;
        }

        $order->update([
            'payment_gateway' => $session->gateway,
            'payment_reference' => $session->reference,
        ]);

        return ['order' => $order->fresh('items'), 'session' => $session];
    }

    /**
     * A fresh payment session for an order still awaiting payment, under a
     * new transaction reference (`GCT-…-2`, `-3`, …), since a gateway takes
     * each reference once. Used when a WhatsApp payment link is resent. The
     * hold, prices and FX rate are untouched: this is the same order.
     */
    public function reopenPayment(Order $order): PaymentSession
    {
        $gateway = $this->gateways->for($order->currency);

        $attempt = 2;
        $last = (string) $order->payment_reference;
        if (preg_match('/^'.preg_quote($order->reference, '/').'-(\d+)$/', $last, $m)) {
            $attempt = (int) $m[1] + 1;
        }

        $session = $gateway->createSession($order, "{$order->reference}-{$attempt}");

        $order->update([
            'payment_gateway' => $session->gateway,
            'payment_reference' => $session->reference,
        ]);

        return $session;
    }

    /**
     * The card covered everything: there is nothing to charge, so the order is
     * paid now, through the same `OrderPaid` that a Paystack payment fires
     * (stock is finalised, a shipment opened, the confirmation sent).
     */
    private function paidByGiftCard(Order $order): Order
    {
        $order->update([
            'status' => 'paid',
            'payment_gateway' => 'gift_card',
            'payment_reference' => $order->reference,
        ]);

        OrderPaid::dispatch($order);

        return $order->fresh('items');
    }

    /** Shaped like a gateway's, so the storefront redirects the same way. */
    private function giftCardSession(Order $order): PaymentSession
    {
        $storefront = rtrim((string) config('services.paystack.callback_base_url'), '/');

        return new PaymentSession(
            gateway: 'gift_card',
            reference: $order->reference,
            authorizationUrl: "{$storefront}/order-confirmation/{$order->reference}",
        );
    }

    /** Undoes a committed checkout whose payment session never opened. */
    private function abandon(Order $order): void
    {
        $this->giftCards->release($order, 'Payment session failed to open');

        DB::transaction(function () use ($order) {
            $this->reservations->releaseOrder($order, 'Payment session failed to open');

            // order_items and stock_holds cascade.
            $order->delete();
        });
    }

    /**
     * Re-reads every line from the database. The request supplies an inventory
     * item and a quantity; everything else — which product it is, what it
     * costs, what the variant is called — comes from here.
     *
     * @param  array<int, array{inventory_item_id: int, quantity: int}>  $items
     * @return array<int, array<string, mixed>>
     */
    private function priceLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            /** @var InventoryItem $inventoryItem */
            $inventoryItem = InventoryItem::query()
                ->with('product')
                ->findOrFail($item['inventory_item_id']);

            $product = $inventoryItem->product;

            if (! $product || ! $product->is_active) {
                throw new UnavailableProductException($inventoryItem->id);
            }

            $lines[] = [
                'inventory_item' => $inventoryItem,
                'product' => $product,
                'quantity' => $item['quantity'],
                // Always the selling price, never compare_at_ghs — that is the
                // was-price, and charging it would invert every sale.
                'unit_price' => $product->base_price_ghs,
                'variant_label' => $this->variantLabel($inventoryItem),
            ];
        }

        return $lines;
    }

    /** e.g. "42" or "42 | Black" — the label frozen onto the receipt. */
    private function variantLabel(InventoryItem $inventoryItem): ?string
    {
        // Size, then colour — "42 | Tan" — however the jsonb happens to order
        // its keys, then anything else a future variant axis adds.
        $attributes = $inventoryItem->variant_attributes ?? [];
        $ordered = array_filter([
            $attributes['size'] ?? null,
            $attributes['colour'] ?? null,
            ...array_values(array_diff_key($attributes, ['size' => 0, 'colour' => 0])),
        ], fn ($value) => $value !== null && $value !== '');

        return $ordered ? implode(' | ', $ordered) : null;
    }

    /** GHS minor units to the order's currency, at the locked rate. */
    private function convert(int $amountGhs, ?string $fxRate): int
    {
        return $fxRate === null ? $amountGhs : (int) round($amountGhs * (float) $fxRate);
    }
}
