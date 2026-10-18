<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * The first admin account. Locally it is the well-known `password`; in
 * production the seeder refuses anything shorter than 12 characters, so a
 * live super_admin can never be guessable.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_outside_production_creates_the_local_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = AdminUser::query()->where('email', 'admin@goldcoasttokota.store')->firstOrFail();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_production_refuses_to_seed_without_a_strong_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_ADMIN_PASSWORD');

        // Called directly: `db:seed` in production stops to ask for
        // confirmation before the seeder ever runs.
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();
    }
}
