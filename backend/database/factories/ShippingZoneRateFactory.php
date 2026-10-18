<?php

namespace Database\Factories;

use App\Models\ShippingZoneRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZoneRate>
 */
class ShippingZoneRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'region' => fake()->unique()->randomElement(ShippingZoneRate::REGIONS),
            'amount_ghs' => 4_000,
            'is_active' => true,
        ];
    }
}
