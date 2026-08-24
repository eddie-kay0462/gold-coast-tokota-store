<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum only boots session middleware (EnsureFrontendRequestsAreStateful)
     * for requests whose Referer/Origin matches a configured stateful domain —
     * true for the real admin app, so we simulate it here for any test that
     * needs $request->session() (login/logout).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3001');
    }

    public function test_admin_can_log_in_with_correct_credentials(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@goldcoasttokota.store',
            'password' => Hash::make('correct-password'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@goldcoasttokota.store',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $admin->id);
        $response->assertJsonPath('data.role', 'admin');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_login_fails_with_incorrect_password(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@goldcoasttokota.store',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@goldcoasttokota.store',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest('admin');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@goldcoasttokota.store',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/login', [
                'email' => 'admin@goldcoasttokota.store',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@goldcoasttokota.store',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest('admin');

        RateLimiter::clear(mb_strtolower('admin@goldcoasttokota.store').'|127.0.0.1');
    }

    public function test_a_customer_cannot_authenticate_against_the_admin_guard(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@goldcoasttokota.store',
            'password' => Hash::make('correct-password'),
        ]);

        // A Customer with a matching email/password on the 'web' guard must
        // not be able to log into the 'admin' guard — separate identity
        // models, separate credential stores.
        Customer::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'customer@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest('admin');
    }

    public function test_authenticated_admin_can_fetch_me(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/me');

        $response->assertOk();
        $response->assertJsonPath('data.id', $admin->id);
        $response->assertJsonPath('data.role', 'staff');
    }

    public function test_guest_cannot_fetch_me(): void
    {
        $this->getJson('/api/v1/admin/me')->assertUnauthorized();
    }

    public function test_admin_can_log_out(): void
    {
        $admin = AdminUser::factory()->create();

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/logout');

        $response->assertNoContent();
        $this->assertGuest('admin');
    }
}
