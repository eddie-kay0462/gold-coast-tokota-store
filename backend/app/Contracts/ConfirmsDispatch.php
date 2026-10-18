<?php

namespace App\Contracts;

use App\Models\Shipment;
use App\Services\Shipping\DispatchResult;

/**
 * A carrier whose booking is two steps: create, then confirm once the carrier
 * has priced it. Yango works this way — a claim is created, estimated, and
 * only costs money once accepted.
 */
interface ConfirmsDispatch
{
    public function confirm(Shipment $shipment): DispatchResult;
}
