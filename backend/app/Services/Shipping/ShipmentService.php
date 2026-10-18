<?php

namespace App\Services\Shipping;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingZone;
use App\Jobs\DispatchShipment;
use App\Jobs\SendOrderNotification;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Notifications\OpsAlert;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Every change to a shipment goes through here, so the shipment, its event
 * log and the order it belongs to cannot disagree.
 *
 * The order's own status follows the shipment: a parcel that has left the
 * workshop makes the order `shipped` (and tells the customer, once), and a
 * delivered parcel makes it `delivered`. `delivery_provider` and
 * `delivery_reference` on the order mirror the live shipment so everything
 * that already reads them — the confirmation page, the shipped email — keeps
 * working.
 */
class ShipmentService
{
    /** How carriers are named in the history staff read. */
    private const CARRIER_NAMES = ['yango' => 'Yango', 'swoove' => 'Swoove', 'dhl' => 'DHL Express', 'manual' => 'a manual courier'];

    /** Order statuses a shipment may move forward. Anything else is a human's call. */
    private const FULFILLABLE = ['paid', 'processing', 'shipped'];

    public function __construct(
        private readonly ShippingManager $shipping,
        private readonly OpsAlert $alerts,
    ) {}

    /**
     * Opens the shipment for a newly paid order. Idempotent: a second call
     * returns the shipment the first one made.
     *
     * Nothing is booked yet. Yango and DHL wait for "Mark as packed"; a manual
     * shipment waits for a person, who is told about it now.
     */
    public function createForPaidOrder(Order $order): Shipment
    {
        $shipment = DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($existing = $order->shipment) {
                return $existing;
            }

            $zone = $order->shipping_zone ?? $this->shipping->zoneFor($order->shipping_address ?? []);
            $carrier = $this->carrierFor($order, $zone);
            $option = $order->shipping_option ?? [];

            $shipment = $order->shipments()->create([
                'zone' => $zone,
                'carrier' => $carrier,
                'service_code' => ($option['carrier'] ?? null) === $carrier ? ($option['service_code'] ?? null) : null,
                'status' => ShipmentStatus::Pending,
                'quoted_cost' => $option['quoted_raw'] ?? null,
                'quoted_currency' => $option['quoted_currency'] ?? null,
            ]);

            $order->update(['shipping_zone' => $zone, 'delivery_provider' => $carrier]);
            $shipment->record('system', $carrier === 'manual'
                ? 'Opened on payment; waiting for a courier to be booked by hand.'
                : 'Opened on payment; waiting to be packed.');

            return $shipment;
        });

        if ($shipment->wasRecentlyCreated && $shipment->carrier === 'manual') {
            $this->alerts->newManualShipment($shipment);
        }

        return $shipment;
    }

    /**
     * The carrier chosen at checkout if it is still switched on, otherwise
     * whatever now serves the zone. A carrier disabled between payment and
     * dispatch must not strand the order.
     */
    private function carrierFor(Order $order, ShippingZone $zone): string
    {
        $chosen = $order->shipping_option['carrier'] ?? null;
        $chain = array_map(fn ($carrier) => $carrier->name(), $this->shipping->chainFor($zone));

        return in_array($chosen, $chain, true) ? $chosen : $chain[0];
    }

    /**
     * Staff have packed the parcel. For a courier with an API this is the
     * moment it is booked — not payment: a Yango courier arrives within
     * minutes, and the order has up to 48 hours to be made ready (§21).
     * For a manual shipment it is only recorded.
     *
     * Idempotent: a shipment already packed is returned untouched, so a
     * double click books one courier, not two.
     *
     * @return bool whether a booking was queued
     */
    public function markPacked(Shipment $shipment, AdminUser $by): bool
    {
        $queued = DB::transaction(function () use ($shipment, $by) {
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($shipment->packed_at !== null) {
                return false;
            }

            if ($shipment->status !== ShipmentStatus::Pending) {
                throw new InvalidArgumentException('Only a shipment waiting to be packed can be marked packed.');
            }

            if (! in_array($shipment->order->status, ['paid', 'processing'], true)) {
                throw new InvalidArgumentException(
                    "Order {$shipment->order->reference} is {$shipment->order->status}; settle that before packing it.",
                );
            }

            $shipment->fill(['packed_at' => now(), 'packed_by' => $by->id]);
            $shipment->order->update(['status' => 'processing']);

            if ($shipment->carrier === 'manual') {
                $shipment->save();
                $shipment->record('admin', "Packed by {$by->name}; waiting for a courier to be booked by hand.");

                return false;
            }

            $this->transition($shipment, ShipmentStatus::Booking, 'admin', "Packed by {$by->name}; booking a ".self::carrierName($shipment->carrier).' courier.');

            return true;
        });

        if ($queued) {
            DispatchShipment::dispatch($shipment->fresh());
        }

        return $queued;
    }

    /**
     * Records what a carrier said when a booking was created or confirmed,
     * and when to look at it next.
     */
    public function recordBooking(Shipment $shipment, DispatchResult $result, string $message): Shipment
    {
        $shipment->fill(array_filter([
            'carrier_reference' => $result->carrierReference,
            'carrier_status' => $result->carrierStatus,
            'carrier_version' => $result->carrierVersion,
            'tracking_url' => $result->trackingUrl,
            'pickup_reference' => $result->pickupReference,
        ], fn ($value) => $value !== null));
        $shipment->next_poll_at = now()->addSeconds($this->pollDelay($shipment));

        // Labels and customs invoices go to the private bucket: they carry
        // the customer's name and address. Admin opens them by signed link.
        foreach ($result->documents as $kind => $bytes) {
            $column = ['label' => 'label_path', 'invoice' => 'invoice_path'][$kind] ?? null;

            if ($column !== null && $bytes !== '') {
                $path = "shipments/{$shipment->uuid}/{$kind}.pdf";
                MediaStorage::privateDisk()->put($path, $bytes);
                $shipment->{$column} = $path;
            }
        }

        if ($shipment->status === $result->status) {
            $shipment->save();
            $shipment->record('system', $message, $result->payload);
            $this->syncOrder($shipment, $shipment->status);

            return $shipment;
        }

        return $this->transition($shipment, $result->status, 'system', $message, $result->payload);
    }

    /**
     * Seconds until a shipment is next polled: quickly while a booking is
     * being priced and confirmed (an offer lapses after 10 minutes), at the
     * carrier's normal cadence after that.
     */
    public function pollDelay(Shipment $shipment): int
    {
        $confirming = $shipment->status === ShipmentStatus::Booking
            && in_array($shipment->carrier_status, [null, 'new', 'estimating', 'ready_for_approval'], true);

        return max(10, (int) ($confirming
            ? config("shipping.{$shipment->carrier}.confirm_poll_seconds", 30)
            : config("shipping.{$shipment->carrier}.poll_seconds", 300)));
    }

    /**
     * Whether a booking has been looking for a courier for longer than the
     * carrier's configured patience. Carriers without a limit never time out.
     */
    public function searchTimedOut(Shipment $shipment): bool
    {
        $minutes = config("shipping.{$shipment->carrier}.courier_search_timeout_minutes");

        if ($minutes === null || $shipment->status !== ShipmentStatus::Booking) {
            return false;
        }

        $started = $shipment->packed_at && $shipment->packed_at->gt($shipment->created_at)
            ? $shipment->packed_at
            : $shipment->created_at;

        return $started->lt(now()->subMinutes((int) $minutes));
    }

    /**
     * No courier took the job. Cancel the booking, then, in order: try the
     * same carrier again (up to its max_attempts), hand over to the next
     * carrier in the zone's chain (Swoove after Yango), or give it to a
     * person — failed, with the team told.
     */
    public function courierNotFound(Shipment $shipment): ?Shipment
    {
        $shipment->refresh();

        if ($shipment->status->isTerminal()) {
            return null;
        }

        $carrier = $this->shipping->forShipment($shipment);

        try {
            $cancelled = $carrier->cancel($shipment);
        } catch (Throwable $e) {
            Log::warning('Cancelling a courier-less booking failed.', ['shipment' => $shipment->uuid, 'error' => $e->getMessage()]);
            $cancelled = null;
        }

        // A booking that will not cancel may still have a courier on the way.
        // Booking a second one on top would send two.
        if ($cancelled === null || ! $cancelled->cancelled) {
            $this->markFailed($shipment, 'No courier was found, and the booking could not be cancelled. Check with '
                .self::carrierName($carrier->name()).' before booking another courier.');

            return null;
        }

        $maxAttempts = (int) config("shipping.{$shipment->carrier}.max_attempts", 1);
        $next = $shipment->attempt < $maxAttempts
            ? ['carrier' => $shipment->carrier, 'attempt' => $shipment->attempt + 1]
            : (($fallback = $this->shipping->fallbackAfter($shipment->zone, $shipment->carrier))
                ? ['carrier' => $fallback->name(), 'attempt' => 1]
                : null);

        if ($next === null) {
            $this->markFailed($shipment, 'No '.self::carrierName($shipment->carrier)." courier was found after {$shipment->attempt} attempt(s), and no other courier is switched on.");

            return null;
        }

        $replacement = DB::transaction(function () use ($shipment, $cancelled, $next) {
            $shipment->failure_reason = 'No courier found.';
            $this->transition($shipment, ShipmentStatus::Cancelled, 'system',
                'No courier found; booking cancelled'.($cancelled->charged ? ' (a cancellation fee applies).' : '.'),
                $cancelled->payload);

            $replacement = $shipment->order->shipments()->create([
                'zone' => $shipment->zone,
                'carrier' => $next['carrier'],
                'attempt' => $next['attempt'],
                'supersedes_id' => $shipment->id,
                'status' => ShipmentStatus::Booking,
                'packed_at' => $shipment->packed_at,
                'packed_by' => $shipment->packed_by,
            ]);
            $replacement->record('system', $next['carrier'] === $shipment->carrier
                ? 'Trying '.self::carrierName($next['carrier'])." again (attempt {$next['attempt']})."
                : 'Handed over from '.self::carrierName($shipment->carrier).' to '.self::carrierName($next['carrier']).'.');

            return $replacement;
        });

        DispatchShipment::dispatch($replacement);

        return $replacement;
    }

    /**
     * Staff booked a courier themselves and recorded it. A failed automated
     * shipment can be rescued this way too: it is superseded by a new manual
     * one, so the failed booking stays on record as what it was.
     *
     * @param  array{courier_name: string, tracking_number?: string|null, tracking_url?: string|null, cost_paid_ghs?: int|null, notes?: string|null}  $data
     */
    public function dispatchManually(Shipment $shipment, array $data, AdminUser $by): Shipment
    {
        if ($shipment->carrier !== 'manual' && $shipment->status !== ShipmentStatus::Failed) {
            throw new InvalidArgumentException('Only a manual or a failed shipment can be dispatched by hand.');
        }

        if ($shipment->carrier === 'manual' && $shipment->status !== ShipmentStatus::Pending) {
            throw new InvalidArgumentException('This shipment has already been dispatched.');
        }

        return DB::transaction(function () use ($shipment, $data, $by) {
            if ($shipment->carrier !== 'manual') {
                $shipment = $shipment->order->shipments()->create([
                    'zone' => $shipment->zone,
                    'carrier' => 'manual',
                    'attempt' => 1,
                    'supersedes_id' => $shipment->id,
                    'status' => ShipmentStatus::Pending,
                    'packed_at' => $shipment->packed_at,
                    'packed_by' => $shipment->packed_by,
                ]);
            }

            $previous = $shipment->status;

            $shipment->update([
                'status' => ShipmentStatus::InTransit,
                'courier_name' => $data['courier_name'],
                'carrier_reference' => $data['tracking_number'] ?? null,
                'tracking_url' => $data['tracking_url'] ?? null,
                'actual_cost' => $data['cost_paid_ghs'] ?? null,
                'actual_currency' => isset($data['cost_paid_ghs']) ? 'GHS' : null,
                'notes' => $data['notes'] ?? null,
                'dispatched_at' => now(),
                'dispatched_by' => $by->id,
            ]);

            $shipment->record('admin', "Dispatched by hand with {$data['courier_name']} by {$by->name}.");
            $this->syncOrder($shipment, $previous);

            return $shipment;
        });
    }

    public function markDelivered(Shipment $shipment, ?AdminUser $by = null): Shipment
    {
        return $this->transition(
            $shipment,
            ShipmentStatus::Delivered,
            'admin',
            'Marked delivered'.($by ? " by {$by->name}" : '').'.',
        );
    }

    public function markFailed(Shipment $shipment, string $reason, ?AdminUser $by = null): Shipment
    {
        $shipment->failure_reason = $reason;
        $shipment = $this->transition(
            $shipment,
            ShipmentStatus::Failed,
            $by ? 'admin' : 'system',
            "Marked failed: {$reason}".($by ? " ({$by->name})" : ''),
        );

        $this->alerts->shipmentFailed($shipment, $reason);

        return $shipment;
    }

    /**
     * Applies what a carrier reported. Writes an event only when something
     * changed, so a poller asking every minute leaves a readable history.
     */
    public function applyStatus(Shipment $shipment, StatusResult $result, string $source = 'poll'): Shipment
    {
        $changed = $shipment->status !== $result->status || $shipment->carrier_status !== $result->carrierStatus;

        $shipment->next_poll_at = now()->addSeconds($this->pollDelay($shipment));
        $shipment->fill(array_filter([
            'carrier_version' => $result->carrierVersion,
            'tracking_url' => $result->trackingUrl,
            'actual_cost' => $result->actualCost,
            'actual_currency' => $result->actualCurrency,
        ], fn ($value) => $value !== null));
        $shipment->last_polled_at = now();

        if (! $changed) {
            $shipment->save();

            return $shipment;
        }

        $shipment->carrier_status = $result->carrierStatus;

        return $this->transition($shipment, $result->status, $source, null, $result->payload);
    }

    /** @param  array<string, mixed>|null  $payload */
    private function transition(Shipment $shipment, ShipmentStatus $to, string $source, ?string $message, ?array $payload = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $to, $source, $message, $payload) {
            $previous = $shipment->getOriginal('status');
            $previous = $previous instanceof ShipmentStatus ? $previous : ShipmentStatus::tryFrom((string) $previous);

            $shipment->status = $to;

            if ($to === ShipmentStatus::Delivered) {
                $shipment->delivered_at ??= now();
            }

            if ($to === ShipmentStatus::Cancelled) {
                $shipment->cancelled_at ??= now();
            }

            if ($to->hasShipped()) {
                $shipment->dispatched_at ??= now();
            }

            $shipment->save();
            $shipment->record($source, $message, $payload);
            $this->syncOrder($shipment, $previous);

            return $shipment;
        });
    }

    /**
     * Carries the shipment's progress onto its order, forwards only. An order
     * a person has cancelled, refunded or flagged is left alone.
     */
    private function syncOrder(Shipment $shipment, ?ShipmentStatus $previous): void
    {
        $order = $shipment->order()->first();

        $order->fill([
            'delivery_provider' => $shipment->carrier,
            'delivery_reference' => $shipment->carrier_reference ?? $order->delivery_reference,
        ]);

        if (! in_array($order->status, self::FULFILLABLE, true)) {
            $order->save();

            return;
        }

        $announce = false;

        if ($shipment->status === ShipmentStatus::Delivered) {
            $order->status = 'delivered';
            // §9 runs the returns window from receipt; set once and kept.
            $order->delivered_at ??= now();
        } elseif ($shipment->status->hasShipped() && $order->status !== 'shipped') {
            $order->status = 'shipped';
            $announce = ! ($previous?->hasShipped() ?? false);
        }

        $order->save();

        // The brand document's dispatch promise, made once per order.
        if ($announce) {
            SendOrderNotification::dispatch($order, 'shipped');
        }
    }

    public static function carrierName(string $carrier): string
    {
        return self::CARRIER_NAMES[$carrier] ?? $carrier;
    }
}
