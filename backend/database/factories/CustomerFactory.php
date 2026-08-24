<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'preferred_currency' => fake()->randomElement(['GHS', 'USD']),
            // Nullable in the schema (guest checkout), but the factory
            // default gives a real password so auth-boundary tests can
            // exercise the 'web' guard directly.
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
