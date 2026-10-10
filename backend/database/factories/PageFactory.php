<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($title),
            'title' => Str::title($title),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'is_draft' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_draft' => true]);
    }
}
