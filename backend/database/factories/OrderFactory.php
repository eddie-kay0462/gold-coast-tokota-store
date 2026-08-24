<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(5_000, 50_000);
        $shippingCost = 2_500;
        $tax = 0;

        return [
            'customer_id' => null,
            'currency' => 'GHS',
            'fx_rate_applied' => null,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $subtotal + $shippingCost + $tax,
            'status' => 'pending',
            'payment_gateway' => 'paystack',
            'payment_reference' => null,
            'delivery_provider' => 'yango',
            'delivery_reference' => null,
            'shipping_address' => [
                'name' => fake()->name(),
                'phone' => fake()->e164PhoneNumber(),
                'address' => fake()->streetAddress(),
                'city' => 'Accra',
                'country' => 'GH',
            ],
        ];
    }

    public function usd(): static
    {
        return $this->state(fn () => [
            'currency' => 'USD',
            'fx_rate_applied' => 0.075,
            'payment_gateway' => 'stripe',
            'delivery_provider' => 'dhl',
            'shipping_address' => [
                'name' => fake()->name(),
                'phone' => fake()->e164PhoneNumber(),
                'address' => fake()->streetAddress(),
                'city' => fake()->city(),
                'country' => 'US',
            ],
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'payment_reference' => strtoupper(Str::random(12)),
        ]);
    }
}
