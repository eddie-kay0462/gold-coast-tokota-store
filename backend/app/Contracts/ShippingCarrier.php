<?php

namespace App\Contracts;

use App\Models\Shipment;
use App\Services\Shipping\CancelResult;
use App\Services\Shipping\DispatchResult;
use App\Services\Shipping\QuoteRequest;
use App\Services\Shipping\QuoteResult;
use App\Services\Shipping\StatusResult;

/**
 * A courier: Yango, Swoove, DHL Express, or a person with a phone.
 *
 * The same shape as PaymentGateway and NotificationChannel, for the same
 * reason: the real drivers need credentials nobody has yet, and the contract
 * is what lets checkout, admin and the poller be built against all of them
 * now. Swapping a carrier is a config change in config/shipping.php, never an
 * edit to checkout or order code.
 *
 * Implementations throw on transport or API failure. Callers decide what a
 * failure means — at checkout it means "charge the fallback rate", on a
 * queue it means "retry".
 */
interface ShippingCarrier
{
    /** Stored on the shipment: 'yango' | 'swoove' | 'dhl' | 'manual'. */
    public function name(): string;

    /** Switched on in config and able to run here (credentials, or a fake). */
    public function isEnabled(): bool;

    /** Prices before buffer. ShippingManager adds the buffer and the threshold. */
    public function quote(QuoteRequest $request): QuoteResult;

    /** Books the courier. Must be safe to call twice for the same shipment. */
    public function dispatch(Shipment $shipment): DispatchResult;

    public function status(Shipment $shipment): StatusResult;

    public function cancel(Shipment $shipment): CancelResult;
}
