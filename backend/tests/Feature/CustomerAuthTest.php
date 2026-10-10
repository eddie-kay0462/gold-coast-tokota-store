<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum only boots session middleware for requests whose Referer/Origin
     * matches a configured stateful domain — true for the real storefront on
     * :3000, so it is simulated here for anything touching $request->session().
     * AdminAuthTest does the same against :3001.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3000');
        RateLimiter::clear('ama@example.com|127.0.0.1');
    }

    // --- register -------------------------------------------------------

    public function test_a_visitor_can_register_and_is_signed_in_immediately(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Ama Serwaa',
            'email' => 'ama@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'ama@example.com');
        $this->assertAuthenticatedAs(Customer::first(), 'web');
    }

    public function test_passwords_are_never_stored_in_the_clear(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Ama Serwaa',
            'email' => 'ama@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertCreated();

        $customer = Customer::first();
        $this->assertNotSame('correct-horse-battery', $customer->password);
        $this->assertTrue(Hash::check('correct-horse-battery', $customer->password));
    }

    public function test_the_password_is_never_returned(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Ama Serwaa',
            'email' => 'ama@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    public function test_registering_a_taken_email_is_rejected(): void
    {
        Customer::factory()->create(['email' => 'ama@example.com']);

        $this->postJson('/api/v1/register', [
            'name' => 'Someone Else',
            'email' => 'ama@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Ama Serwaa',
            'email' => 'ama@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'something-else',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    // --- login / logout -------------------------------------------------

    public function test_a_customer_can_sign_in(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'ama@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'ama@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', (string) $customer->id);
        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_a_wrong_password_is_refused(): void
    {
        Customer::factory()->create(['email' => 'ama@example.com', 'password' => 'password']);

        $this->postJson('/api/v1/login', [
            'email' => 'ama@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->assertGuest('web');
    }

    /** Feature 12: repeated failures lock the pair out, not the address alone. */
    public function test_repeated_failures_are_rate_limited(): void
    {
        Customer::factory()->create(['email' => 'ama@example.com', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => 'ama@example.com', 'password' => 'wrong']);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => 'ama@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('seconds', $response->json('message'));
    }

    public function test_a_customer_can_sign_out(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'web')->postJson('/api/v1/logout')->assertOk();

        $this->assertGuest('web');
    }

    public function test_me_returns_the_signed_in_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ama Serwaa']);

        $this->actingAs($customer, 'web')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Ama Serwaa');
    }

    public function test_me_is_closed_to_guests(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    /**
     * config/sanctum.php lists both `web` and `admin` in its guard array, so
     * `auth:sanctum` would have let an admin session satisfy a customer route —
     * and $request->user() would then be an AdminUser whose id could collide
     * with a real customer's. Customer routes use `auth:web` for that reason.
     */
    public function test_an_admin_session_cannot_reach_customer_routes(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')->getJson('/api/v1/me')->assertUnauthorized();
        $this->actingAs($admin, 'admin')->getJson('/api/v1/orders')->assertUnauthorized();
    }

    // --- order history --------------------------------------------------

    public function test_order_history_returns_only_the_signed_in_customers_orders(): void
    {
        $customer = Customer::factory()->create();
        $someoneElse = Customer::factory()->create();

        Order::factory()->count(2)->create(['customer_id' => $customer->id]);
        Order::factory()->create(['customer_id' => $someoneElse->id]);
        Order::factory()->create(['customer_id' => null]);

        $response = $this->actingAs($customer, 'web')->getJson('/api/v1/orders');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_order_history_is_closed_to_guests(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }
}
