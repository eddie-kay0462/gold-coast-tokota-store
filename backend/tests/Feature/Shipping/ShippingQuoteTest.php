<?php

namespace Tests\Feature\Shipping;

use App\Contracts\ShippingCarrier;
use App\Enums\ShipmentStatus;
use App\Exceptions\ShippingQuoteUnavailableException;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShippingSetting;
use App\Models\ShippingZoneRate;
use App\Services\Shipping\CancelResult;
use App\Services\Shipping\DispatchResult;
use App\Services\Shipping\QuoteOption;
use App\Services\Shipping\QuoteRequest;
use App\Services\Shipping\QuoteResult;
use App\Services\Shipping\ShippingManager;
use App\Services\Shipping\StatusResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zones, quotes and the rule that what checkout shows is what it charges.
 */
class ShippingQuoteTest extends TestCase
{
    use RefreshDatabase;

    private const ACCRA_POLYGON = [
        'type' => 'Polygon',
        'coordinates' => [[[-0.30, 5.50], [-0.10, 5.50], [-0.10, 5.70], [-0.30, 5.70], [-0.30, 5.50]]],
    ];

    private const OSU = [
        'full_name' => 'Ama Serwaa', 'email' => 'ama@example.com', 'phone' => '233200000000',
        'line1' => '4 Oxford Street', 'city' => 'Accra', 'region' => 'Greater Accra',
        'country' => 'GH', 'lat' => 5.556, 'lng' => -0.18,
    ];

    private const KUMASI = [
        'full_name' => 'Kofi Mensah', 'email' => 'kofi@example.com', 'phone' => '233240000000',
        'line1' => '9 Prempeh Street', 'city' => 'Kumasi', 'region' => 'Ashanti', 'country' => 'GH',
    ];

    private const LONDON = [
        'full_name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '447700000000',
        'line1' => '5 Bridge Street', 'city' => 'London', 'country' => 'gb',
    ];

    private function stocked(int $priceGhs = 60_000): InventoryItem
    {
        return InventoryItem::factory()->create([
            'product_id' => Product::factory()->create(['base_price_ghs' => $priceGhs])->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);
    }

    private function quote(InventoryItem $item, array $address)
    {
        return $this->postJson('/api/v1/checkout/shipping-quote', [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            'shipping_address' => $address,
        ]);
    }

    private function checkout(InventoryItem $item, array $address, array $extra = [])
    {
        return $this->postJson('/api/v1/checkout/session', [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            'currency' => 'GHS',
            'shipping_address' => $address,
            ...$extra,
        ]);
    }

    /**
     * Stands in for Yango until Phase 2: a carrier that quotes a fixed price,
     * or fails, on demand.
     */
    private function liveCarrier(?int $priceGhs, string $name = 'yango'): void
    {
        $carrier = new class($name, $priceGhs) implements ShippingCarrier
        {
            public function __construct(private string $carrierName, private ?int $price) {}

            public function name(): string
            {
                return $this->carrierName;
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function quote(QuoteRequest $request): QuoteResult
            {
                if ($this->price === null) {
                    throw new ShippingQuoteUnavailableException('No courier nearby.');
                }

                return new QuoteResult($request->zone, [new QuoteOption(
                    id: QuoteOption::idFor($this->carrierName, 'courier'),
                    carrier: $this->carrierName,
                    serviceCode: 'courier',
                    label: 'Courier',
                    amountGhs: $this->price,
                    source: QuoteOption::SOURCE_LIVE,
                    rawAmount: $this->price,
                    rawCurrency: 'GHS',
                )]);
            }

            public function dispatch(Shipment $shipment): DispatchResult
            {
                return new DispatchResult(ShipmentStatus::Booking);
            }

            public function status(Shipment $shipment): StatusResult
            {
                return new StatusResult($shipment->status);
            }

            public function cancel(Shipment $shipment): CancelResult
            {
                return new CancelResult(true);
            }
        };

        app(ShippingManager::class)->extend($name, fn () => $carrier);
    }

    // --- zones ----------------------------------------------------------

    public function test_a_pin_inside_the_accra_polygon_is_the_accra_zone(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::ACCRA_POLYGON]);

        $this->quote($this->stocked(), self::OSU)
            ->assertOk()
            ->assertJsonPath('data.zone', 'accra');
    }

    /** No polygon uploaded yet means no Accra zone: flat-rate, manual, safe. */
    public function test_without_a_polygon_even_an_accra_pin_is_the_ghana_zone(): void
    {
        $this->quote($this->stocked(), self::OSU)
            ->assertOk()
            ->assertJsonPath('data.zone', 'ghana');
    }

    public function test_ghana_outside_the_polygon_is_the_ghana_zone_at_its_regional_rate(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::ACCRA_POLYGON]);
        ShippingZoneRate::factory()->create(['region' => 'Ashanti', 'amount_ghs' => 4_000]);

        $this->quote($this->stocked(), self::KUMASI)
            ->assertOk()
            ->assertJsonPath('data.zone', 'ghana')
            ->assertJsonPath('data.options.0.amount_ghs', 4_000)
            ->assertJsonPath('data.options.0.label', 'Delivery to Ashanti');
    }

    public function test_any_other_country_is_international(): void
    {
        $this->quote($this->stocked(), self::LONDON)
            ->assertOk()
            ->assertJsonPath('data.zone', 'international')
            ->assertJsonPath('data.options.0.amount_ghs', 35_000);
    }

    // --- regional rates -------------------------------------------------

    /** The fallback rates are set in admin; checkout quotes whatever is set. */
    public function test_a_fallback_rate_set_in_admin_is_what_checkout_quotes(): void
    {
        ShippingSetting::current()->update(['fallback_ghana_ghs' => 5_000, 'fallback_international_ghs' => 40_000]);

        $this->quote($this->stocked(), self::KUMASI)->assertOk()->assertJsonPath('data.options.0.amount_ghs', 5_000);
        $this->quote($this->stocked(), self::LONDON)->assertOk()->assertJsonPath('data.options.0.amount_ghs', 40_000);
    }

    /** Deploying regional rates must not change a price until one is entered. */
    public function test_an_unpriced_region_is_charged_the_ghana_fallback(): void
    {
        $this->quote($this->stocked(), self::KUMASI)
            ->assertOk()
            ->assertJsonPath('data.options.0.amount_ghs', 3_500);
    }

    public function test_an_inactive_region_is_not_offered_its_rate(): void
    {
        ShippingZoneRate::factory()->create(['region' => 'Ashanti', 'amount_ghs' => 9_000, 'is_active' => false]);

        $this->quote($this->stocked(), self::KUMASI)
            ->assertOk()
            ->assertJsonPath('data.options.0.amount_ghs', 3_500);
    }

    public function test_unpriced_regions_can_be_refused_instead(): void
    {
        config(['shipping.unpriced_regions' => 'refuse']);

        $this->quote($this->stocked(), self::KUMASI)
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_address.region');

        $this->checkout($this->stocked(), self::KUMASI)->assertStatus(422);
        $this->assertSame(0, Order::query()->count());
    }

    // --- live quotes ----------------------------------------------------

    public function test_a_live_quote_is_buffered_and_rounded_up_to_a_whole_cedi(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::ACCRA_POLYGON]);
        config(['shipping.quote_buffer_percent' => 10]);
        $this->liveCarrier(3_210);

        // 3210 + 10% = 3531, rounded up to 3600.
        $this->quote($this->stocked(), self::OSU)
            ->assertOk()
            ->assertJsonPath('data.options.0.carrier', 'yango')
            ->assertJsonPath('data.options.0.amount_ghs', 3_600);
    }

    /** A courier outage never stops someone paying. */
    public function test_a_failed_live_quote_falls_back_to_the_configured_rate(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::ACCRA_POLYGON]);
        $this->liveCarrier(null);

        $this->quote($this->stocked(), self::OSU)
            ->assertOk()
            ->assertJsonPath('data.options.0.carrier', 'yango')
            ->assertJsonPath('data.options.0.amount_ghs', 3_500);

        $item = $this->stocked();
        $this->checkout($item, self::OSU)->assertCreated();

        $option = Order::first()->shipping_option;
        $this->assertSame('fallback', $option['source']);
        $this->assertSame('yango', $option['carrier']);
    }

    /** The customer is never shown the carrier's raw figure or our buffer. */
    public function test_the_quote_response_keeps_cost_details_server_side(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::ACCRA_POLYGON]);
        $this->liveCarrier(3_000);

        $option = $this->quote($this->stocked(), self::OSU)->json('data.options.0');

        $this->assertArrayNotHasKey('raw_amount', $option);
        $this->assertArrayNotHasKey('buffer_percent', $option);
        $this->assertArrayNotHasKey('source', $option);
    }

    // --- shown equals charged -------------------------------------------

    public function test_checkout_charges_exactly_the_quote_it_was_shown(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::ACCRA_POLYGON]);
        $this->liveCarrier(3_000);
        $item = $this->stocked();

        $quote = $this->quote($item, self::OSU)->json('data');

        // The carrier's price moves before the customer pays.
        $this->liveCarrier(9_000);

        $this->checkout($item, self::OSU, [
            'shipping_quote_id' => $quote['quote_id'],
            'shipping_option_id' => $quote['options'][0]['id'],
        ])->assertCreated()->assertJsonPath('data.shipping_cost', $quote['options'][0]['amount_ghs']);

        $order = Order::first();
        $this->assertSame(60_000 + $quote['options'][0]['amount_ghs'], $order->total);
        $this->assertSame(3_000, $order->shipping_option['quoted_raw']);
    }

    /** A quote is for one cart to one place; moving the pin re-prices. */
    public function test_a_quote_for_a_different_address_is_refused_with_a_fresh_one(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::ACCRA_POLYGON]);
        $this->liveCarrier(3_000);
        $item = $this->stocked();

        $quote = $this->quote($item, self::OSU)->json('data');
        $moved = [...self::OSU, 'lat' => 5.60, 'lng' => -0.20];

        $response = $this->checkout($item, $moved, [
            'shipping_quote_id' => $quote['quote_id'],
            'shipping_option_id' => $quote['options'][0]['id'],
        ]);

        $response->assertStatus(409);
        $this->assertNotSame($quote['quote_id'], $response->json('shipping_quote.quote_id'));
        $this->assertSame(0, Order::query()->count());
    }

    public function test_an_unknown_quote_id_is_refused_with_a_fresh_quote(): void
    {
        $item = $this->stocked();

        $this->checkout($item, self::KUMASI, [
            'shipping_quote_id' => '7f0d6f9a-2b6c-4a43-9f39-2c4b6b3f9d10',
            'shipping_option_id' => 'manual:standard',
        ])->assertStatus(409)->assertJsonPath('shipping_quote.zone', 'ghana');
    }

    // --- validation -----------------------------------------------------

    public function test_half_a_coordinate_is_rejected(): void
    {
        $address = self::OSU;
        unset($address['lng']);

        $this->quote($this->stocked(), $address)
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_address.lng');
    }

    /** The storefront used to send OTHER for "somewhere else". */
    public function test_a_country_must_be_a_two_letter_code(): void
    {
        $this->quote($this->stocked(), [...self::LONDON, 'country' => 'OTHER'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_address.country');
    }
}
