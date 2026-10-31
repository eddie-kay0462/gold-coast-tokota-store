<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Customer;
use App\Jobs\SendPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Password;
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

    public function test_keep_me_signed_in_issues_a_remember_token(): void
    {
        $admin = AdminUser::factory()->create([
            'password' => Hash::make('correct-password'),
            'remember_token' => null,
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
            'remember' => true,
        ])->assertOk();

        $this->assertNotNull($admin->fresh()->remember_token);
    }

    public function test_login_without_remember_issues_no_remember_token(): void
    {
        $admin = AdminUser::factory()->create([
            'password' => Hash::make('correct-password'),
            'remember_token' => null,
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertNull($admin->fresh()->remember_token);
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

    // --- own account and password reset (31 Oct) -----------------------------

    public function test_you_can_change_your_own_name_without_a_password(): void
    {
        $admin = AdminUser::factory()->create(['name' => 'Old']);

        $this->actingAs($admin, 'admin')->putJson('/api/v1/admin/me', ['name' => 'New', 'job_title' => 'Production Lead'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New');
    }

    public function test_changing_your_password_needs_the_current_one(): void
    {
        $admin = AdminUser::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($admin, 'admin')->putJson('/api/v1/admin/me', [
            'password' => 'new-password-456', 'password_confirmation' => 'new-password-456',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->actingAs($admin, 'admin')->putJson('/api/v1/admin/me', [
            'password' => 'new-password-456', 'password_confirmation' => 'new-password-456',
            'current_password' => 'old-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-456', $admin->fresh()->password));
    }

    public function test_your_own_role_cannot_be_changed_from_your_account(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'staff']);

        $this->actingAs($admin, 'admin')->putJson('/api/v1/admin/me', ['role' => 'super_admin'])->assertOk();

        $this->assertSame('staff', $admin->fresh()->role);
    }

    public function test_forgot_password_sends_an_admin_link_and_a_reset_works(): void
    {
        Bus::fake();
        $admin = AdminUser::factory()->create(['email' => 'mary@example.com']);

        $this->postJson('/api/v1/admin/forgot-password', ['email' => 'mary@example.com'])->assertOk();
        $this->postJson('/api/v1/admin/forgot-password', ['email' => 'nobody@example.com'])->assertOk();
        Bus::assertDispatchedTimes(SendPasswordReset::class, 1);
        Bus::assertDispatched(SendPasswordReset::class, fn ($job) => str_contains($job->message->data['url'], config('app.admin_url').'/reset-password'));

        $token = Password::broker('admins')->createToken($admin);
        $this->postJson('/api/v1/admin/reset-password', [
            'email' => 'mary@example.com', 'token' => $token,
            'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass-1', $admin->fresh()->password));
    }
}
