<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'subscribed_at' => now(),
            'source' => fake()->randomElement(['footer', 'home_hero', 'checkout']),
        ];
    }
}
