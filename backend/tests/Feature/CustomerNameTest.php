<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * First and last name: stored apart, `name` kept as the full name.
 */
class CustomerNameTest extends TestCase
{
    use RefreshDatabase;

    /** The storefront's origin, so Sanctum gives sign-up a session (see CustomerAuthTest). */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3000');
    }

    private function register(array $names): TestResponse
    {
        return $this->postJson('/api/v1/register', $names + [
            'email' => 'ama@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);
    }

    public function test_sign_up_stores_both_names_and_the_full_name(): void
    {
        $this->register(['first_name' => 'Nana Ama', 'last_name' => 'Serwaa'])
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Nana Ama')
            ->assertJsonPath('data.last_name', 'Serwaa')
            ->assertJsonPath('data.name', 'Nana Ama Serwaa');

        $this->assertDatabaseHas('customers', ['first_name' => 'Nana Ama', 'last_name' => 'Serwaa', 'name' => 'Nana Ama Serwaa']);
    }

    public function test_sign_up_needs_both_names(): void
    {
        $this->register(['first_name' => 'Ama'])->assertUnprocessable()->assertJsonValidationErrors('last_name');
        $this->register(['last_name' => 'Serwaa'])->assertUnprocessable()->assertJsonValidationErrors('first_name');
    }

    public function test_an_older_storefront_sending_one_name_still_signs_up(): void
    {
        $this->register(['name' => 'Ama Serwaa Mensah'])
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Ama')
            ->assertJsonPath('data.last_name', 'Serwaa Mensah')
            ->assertJsonPath('data.name', 'Ama Serwaa Mensah');
    }

    public function test_changing_a_part_updates_the_full_name(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Ama', 'last_name' => 'Serwaa']);
        $customer->update(['last_name' => 'Mensah']);

        $this->assertSame('Ama Mensah', $customer->fresh()->name);
    }

    public function test_a_full_name_set_on_its_own_is_split(): void
    {
        $customer = Customer::factory()->create(['name' => 'Kofi Boateng']);

        $this->assertSame(['Kofi', 'Boateng'], [$customer->first_name, $customer->last_name]);
    }

    public function test_splitting_a_whole_name(): void
    {
        $this->assertSame(['Ama', 'Serwaa Mensah'], PersonName::split('  Ama   Serwaa Mensah '));
        $this->assertSame(['Kwame', null], PersonName::split('Kwame'));
        $this->assertSame('Ama Serwaa', PersonName::join(' Ama ', 'Serwaa'));
        $this->assertSame('Kwame', PersonName::join('Kwame', null));
    }
}
