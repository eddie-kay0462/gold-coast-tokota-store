<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\WorkshopSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'diy_order',
            'customer_id' => null,
            'workshop_session_id' => null,
            'scheduled_date' => null,
            'details' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->e164PhoneNumber(),
                'size' => (string) fake()->numberBetween(38, 46),
                'foot_length' => fake()->randomFloat(1, 24, 30),
                'fulfilment' => fake()->randomElement(['pickup', 'delivery']),
            ],
            'status' => 'pending',
        ];
    }

    public function workshop(?WorkshopSession $session = null): static
    {
        return $this->state(function () use ($session) {
            $resolvedSession = $session ?? WorkshopSession::factory()->create();

            return [
                'type' => 'workshop',
                'workshop_session_id' => $resolvedSession->id,
                'scheduled_date' => null,
                'details' => [
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                    'phone' => fake()->e164PhoneNumber(),
                    // One person unless a test says otherwise: seats are
                    // counted in people, so a random party size makes
                    // capacity tests flaky.
                    'attendee_count' => 1,
                ],
            ];
        });
    }

    public function waitlisted(): static
    {
        return $this->state(fn () => ['status' => 'waitlisted']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }
}
