<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingZone;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'zone' => ShippingZone::Ghana,
            'carrier' => 'manual',
            'status' => ShipmentStatus::Pending,
        ];
    }

    public function status(ShipmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
