<?php

namespace Database\Factories;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WorkshopSession>
 */
class WorkshopSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scheduled_date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'scheduled_slot' => fake()->randomElement(['10:00 - 13:00', '14:00 - 17:00']),
            'capacity' => 8,
            'location_notes' => 'Osu workshop, Accra',
            'created_by_admin_id' => AdminUser::factory(),
        ];
    }
}
