<?php

namespace Database\Factories;

use App\Models\AdminUser;
use App\Models\WorkshopSession;
use App\Models\WorkshopType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopSession>
 */
class WorkshopSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Every session runs one of §15's experiences — see the
            // workshop_types migration.
            'workshop_type_id' => WorkshopType::factory(),
            'scheduled_date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'scheduled_slot' => fake()->randomElement(['10:00 - 13:00', '14:00 - 17:00']),
            'capacity' => 8,
            'location_notes' => 'Osu workshop, Accra',
            'created_by_admin_id' => AdminUser::factory(),
        ];
    }
}
