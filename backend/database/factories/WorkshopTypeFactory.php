<?php

namespace Database\Factories;

use App\Models\WorkshopType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkshopType>
 */
class WorkshopTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'days_label' => 'Every Saturday',
            'slot_label' => '10:00 AM – 1:00 PM',
            'duration_label' => '3 hours',
            'capacity' => 20,
            'requires_appointment' => false,
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function byAppointment(): static
    {
        return $this->state(fn () => [
            'days_label' => 'By Appointment',
            'slot_label' => 'Flexible',
            'requires_appointment' => true,
        ]);
    }
}
