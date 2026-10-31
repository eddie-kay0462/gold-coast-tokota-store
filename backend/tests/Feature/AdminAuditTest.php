<?php

namespace Tests\Feature;

use App\Models\AdminAuditEntry;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Settings › Audit log, and the dashboard's Recent activity. */
class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_change_is_logged_with_who_and_what(): void
    {
        $admin = AdminUser::factory()->create(['role' => 'admin', 'name' => 'Mary Seade']);
        $product = Product::factory()->create(['name' => 'Domfo']);

        $this->actingAs($admin, 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}", ['base_price_ghs' => 60_000])
            ->assertOk();

        $entry = AdminAuditEntry::sole();
        $this->assertSame('Mary Seade', $entry->actor_name);
        $this->assertSame('Admin', $entry->actor_role);
        $this->assertSame('Updated product', $entry->action);
        $this->assertSame('Domfo', $entry->target);
        $this->assertSame('Fields: base_price_ghs', $entry->detail);
    }

    public function test_reads_refusals_and_failures_are_not_logged(): void
    {
        $staff = AdminUser::factory()->create(['role' => 'staff']);
        $product = Product::factory()->create();

        $this->actingAs($staff, 'admin')->getJson('/api/v1/admin/products')->assertOk();
        $this->actingAs($staff, 'admin')->putJson("/api/v1/admin/products/{$product->id}", ['name' => 'x'])->assertForbidden();
        $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->putJson("/api/v1/admin/products/{$product->id}", ['base_price_ghs' => -1])->assertStatus(422);

        $this->assertSame(0, AdminAuditEntry::query()->count());
    }

    public function test_a_password_is_never_written_down(): void
    {
        $admin = AdminUser::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($admin, 'admin')->putJson('/api/v1/admin/me', [
            'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1',
            'current_password' => 'old-password-123',
        ])->assertOk();

        $entry = AdminAuditEntry::sole();
        $this->assertStringNotContainsString('password', (string) $entry->detail);
        $this->assertStringNotContainsString('a-long-password-1', (string) json_encode($entry->toArray()));
    }

    public function test_the_log_is_for_whoever_holds_audit_view(): void
    {
        AdminAuditEntry::query()->create([
            'actor_name' => 'Mary', 'actor_role' => 'Admin', 'action' => 'Updated product', 'route' => 'x',
        ]);

        $this->actingAs(AdminUser::factory()->create(['role' => 'admin']), 'admin')
            ->getJson('/api/v1/admin/audit')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'Updated product');
        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->getJson('/api/v1/admin/audit')
            ->assertForbidden();
    }

    public function test_recent_activity_is_built_from_orders_and_bookings(): void
    {
        Order::factory()->create(['status' => 'paid', 'reference' => 'GCT-ACT-1']);
        Booking::factory()->create();

        $titles = collect($this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->getJson('/api/v1/admin/activity')
            ->assertOk()
            ->json('data'))->pluck('title');

        $this->assertTrue($titles->contains(fn ($t) => str_contains($t, 'GCT-ACT-1')));
        $this->assertTrue($titles->contains(fn ($t) => str_starts_with($t, 'Booked') || str_starts_with($t, 'DIY')));
    }
}
