<?php

namespace App\Services\Checkout;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\NoFxRateException;
use App\Exceptions\UnavailableProductException;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Currency\FxRateService;
use App\Services\Delivery\DeliveryProviderFactory;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentSession;
use Illuminate\Support\Facades\DB;

/**
 * Turns a validated cart into a priced, FX-locked, stock-reserved Order plus an
 * open payment session (README Feature 4).
 *
 * Order of operations matters and is not arbitrary:
 *
 *   1. Re-price from the database. Nothing about money is taken from the
 *      request — a client that posts its own prices is a client that can set
 *      them.
 *   2. Quote shipping before payment, so the figure shown is the figure
 *      charged.
 *   3. Lock the FX rate for USD orders, once, so the whole order converts on
 *      one rate.
 *   4. Reserve stock, inside a transaction, with the row locks
 *      InventoryReservationService already provides.
 *   5. Only then open a gateway session.
 *
 * If any step throws, the transaction rolls the Order away and the reservations
 * with it — an abandoned checkout must not leave stock held or an orphan order
 * behind, which is a stated Feature 4 acceptance criterion.
 */
class CheckoutSessionService
{
    /** How long stock is held for a checkout in progress (README Feature 3). */
    private const RESERVATION_TTL_MINUTES = 15;

    /** Unambiguous alphabet — no O/0 or I/1, since customers read these aloud. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly FxRateService $fxRates,
        private readonly InventoryReservationService $reservations,
        private readonly DeliveryProviderFactory $delivery,
        private readonly PaymentGatewayFactory $gateways,
    ) {}

    /**
     * @param  array{items: array<int, array{inventory_item_id: int, quantity: int}>, currency: string, shipping_address: array<string, mixed>, delivery_method: string, customer_id?: int|null}  $data
     * @return array{order: Order, session: PaymentSession}
     *
     * @throws InsufficientStockException
     */
    public function create(array $data): array
    {
        $expiresAt = now()->addMinutes(self::RESERVATION_TTL_MINUTES);

        return DB::transaction(function () use ($data, $expiresAt) {
            $lines = $this->priceLines($data['items']);
            $subtotal = array_sum(array_map(
                fn (array $line) => $line['unit_price'] * $line['quantity'],
                $lines,
            ));

            $provider = $this->delivery->for($data['shipping_address']);
            $shippingCost = $provider->quote(
                $data['shipping_address'],
                $data['delivery_method'],
                $subtotal,
            );

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
                'reference' => $this->generateReference(),
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
                'delivery_provider' => $provider->name(),
                'shipping_address' => $data['shipping_address'],
            ]);

            foreach ($lines as $line) {
                // Reserve before writing the line, so a stock failure aborts
                // the whole transaction rather than leaving a paid-for order
                // item with nothing behind it.
                $this->reservations->reserve($line['inventory_item'], $line['quantity'], $expiresAt);

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

            $session = $this->gateways->for($data['currency'])->createSession($order);

            $order->update([
                'payment_gateway' => $session->gateway,
                'payment_reference' => $session->reference,
            ]);

            return ['order' => $order->fresh('items'), 'session' => $session];
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
        $attributes = array_filter($inventoryItem->variant_attributes ?? []);

        return $attributes ? implode(' | ', $attributes) : null;
    }

    /** GHS minor units to the order's currency, at the locked rate. */
    private function convert(int $amountGhs, ?string $fxRate): int
    {
        return $fxRate === null ? $amountGhs : (int) round($amountGhs * (float) $fxRate);
    }

    private function generateReference(): string
    {
        $max = strlen(self::REFERENCE_ALPHABET) - 1;

        do {
            $reference = 'GCT-'.collect(range(1, 12))
                ->map(fn () => self::REFERENCE_ALPHABET[random_int(0, $max)])
                ->implode('');
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
