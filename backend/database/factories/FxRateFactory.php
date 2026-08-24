<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FxRate>
 */
class FxRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'base_currency' => 'GHS',
            'quote_currency' => 'USD',
            'rate' => fake()->randomFloat(6, 0.06, 0.09),
            'fetched_at' => now(),
            'source' => 'exchangerate.host',
        ];
    }

    public function stale(): static
    {
        return $this->state(fn () => ['fetched_at' => now()->subDays(2)]);
    }
}
