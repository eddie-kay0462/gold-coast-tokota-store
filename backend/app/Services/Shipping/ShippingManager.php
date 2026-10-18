<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingCarrier;
use App\Enums\ShippingZone;
use App\Exceptions\RegionNotServedException;
use App\Exceptions\ShippingDataMissingException;
use App\Models\Shipment;
use App\Models\ShippingSetting;
use App\Services\Shipping\Carriers\DhlExpressCarrier;
use App\Services\Shipping\Carriers\FakeDhlCarrier;
use App\Services\Shipping\Carriers\FakeYangoCarrier;
use App\Services\Shipping\Carriers\ManualCarrier;
use App\Services\Shipping\Carriers\YangoCarrier;
use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Which courier serves an order, and what the customer pays for it.
 *
 * Replaces DeliveryProviderFactory. Each zone has a chain of drivers in
 * config/shipping.php; the first one that is switched on serves it, and a
 * chain with none switched on falls back to the manual carrier, so an order
 * can always be placed and always be dispatched by hand.
 *
 * Pricing rules live here rather than in the drivers, so every carrier is
 * treated alike:
 *   - a live carrier quote gets the configured buffer, rounded up;
 *   - a carrier that cannot quote is replaced by the configured fallback rate,
 *     so a courier outage never stops someone paying;
 *   - domestic orders over the free-shipping threshold, when one is set,
 *     are charged nothing.
 */
class ShippingManager
{
    /** @var array<string, Closure(): ShippingCarrier> */
    private array $drivers = [];

    /** @var array<string, ShippingCarrier> */
    private array $resolved = [];

    public function __construct(private readonly ZoneResolver $zones)
    {
        $this->extend('manual', fn () => new ManualCarrier);

        // The real driver whenever a token exists. Without one: the fake
        // outside production, so the Accra flow can be run locally, and in
        // production the real driver, which then reports itself disabled and
        // is skipped — the same rule PaymentGatewayFactory applies to Paystack.
        $this->extend('yango', fn () => YangoCarrier::isConfigured() || app()->isProduction()
            ? new YangoCarrier
            : new FakeYangoCarrier);

        $this->extend('dhl', fn () => DhlExpressCarrier::isConfigured() || app()->isProduction()
            ? new DhlExpressCarrier
            : new FakeDhlCarrier);
    }

    /** Registers a driver under a name used in config('shipping.zones'). */
    public function extend(string $name, Closure $factory): void
    {
        $this->drivers[$name] = $factory;
        unset($this->resolved[$name]);
    }

    /** @param  array<string, mixed>  $address */
    public function zoneFor(array $address): ShippingZone
    {
        return $this->zones->resolve($address);
    }

    /** A driver by name, or null when none is registered under it yet. */
    public function carrier(string $name): ?ShippingCarrier
    {
        if (! isset($this->drivers[$name])) {
            return null;
        }

        return $this->resolved[$name] ??= ($this->drivers[$name])();
    }

    /**
     * The enabled drivers for a zone, in fallback order.
     *
     * @return list<ShippingCarrier>
     */
    public function chainFor(ShippingZone $zone): array
    {
        $chain = [];

        foreach ((array) config("shipping.zones.{$zone->value}", []) as $name) {
            $carrier = $this->carrier($name);

            if ($carrier !== null && $carrier->isEnabled()) {
                $chain[] = $carrier;
            }
        }

        return $chain ?: [$this->carrier('manual')];
    }

    /**
     * The next enabled carrier in a zone's chain after the one given — Swoove
     * after Yango in Accra — or null when there is none and a person has to
     * take over.
     */
    public function fallbackAfter(ShippingZone $zone, string $carrier): ?ShippingCarrier
    {
        $names = (array) config("shipping.zones.{$zone->value}", []);
        $position = array_search($carrier, $names, true);

        if ($position === false) {
            return null;
        }

        foreach (array_slice($names, $position + 1) as $name) {
            $next = $this->carrier($name);

            if ($next !== null && $next->isEnabled()) {
                return $next;
            }
        }

        return null;
    }

    public function primaryFor(ShippingZone $zone): ShippingCarrier
    {
        return $this->chainFor($zone)[0];
    }

    /** The driver that owns an existing shipment. */
    public function forShipment(Shipment $shipment): ShippingCarrier
    {
        return $this->carrier($shipment->carrier)
            ?? throw new RuntimeException("No shipping driver is registered for '{$shipment->carrier}'.");
    }

    /**
     * @throws RegionNotServedException
     * @throws ShippingDataMissingException
     */
    public function quote(QuoteRequest $request): QuoteResult
    {
        $carrier = $this->primaryFor($request->zone);

        try {
            $result = $carrier->quote($request);

            if ($result->options === []) {
                throw new RuntimeException('The carrier returned no delivery options.');
            }
        } catch (RegionNotServedException|ShippingDataMissingException $e) {
            // Not a carrier outage: a fallback rate would price a parcel that
            // cannot be sent. Checkout says why instead.
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Shipping quote failed; charging the fallback rate.', [
                'carrier' => $carrier->name(),
                'zone' => $request->zone->value,
                'error' => $e->getMessage(),
            ]);

            $result = $this->fallback($carrier, $request);
        }

        $threshold = ShippingSetting::current()->free_shipping_threshold_ghs;
        $free = $request->zone->isDomestic() && $threshold !== null && $request->subtotalGhs >= $threshold;

        return $result->withOptions(array_map(function (QuoteOption $option) use ($free) {
            if ($option->source === QuoteOption::SOURCE_LIVE) {
                $option = $this->buffered($option);
            }

            return $free ? $option->with(amountGhs: 0, free: true) : $option;
        }, $result->options));
    }

    /**
     * The business's own rate for the zone, labelled as the carrier that will
     * still be booked at dispatch: a failed quote changes the price, not the
     * courier.
     */
    private function fallback(ShippingCarrier $carrier, QuoteRequest $request): QuoteResult
    {
        $manual = $this->carrier('manual')->quote($request);

        return $manual->withOptions(array_map(
            fn (QuoteOption $option) => $carrier->name() === 'manual'
                ? $option
                : $option->with(
                    id: QuoteOption::idFor($carrier->name(), null),
                    carrier: $carrier->name(),
                    source: QuoteOption::SOURCE_FALLBACK,
                ),
            $manual->options,
        ));
    }

    private function buffered(QuoteOption $option): QuoteOption
    {
        $percent = max(0, (int) config('shipping.quote_buffer_percent'));
        $step = max(1, (int) config('shipping.round_to_minor'));

        $withBuffer = intdiv($option->amountGhs * (100 + $percent) + 99, 100);
        $rounded = intdiv($withBuffer + $step - 1, $step) * $step;

        return $option->with(amountGhs: $rounded, bufferPercent: $percent);
    }
}
