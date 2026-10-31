<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CSV exports for orders and customers, and the order list's date range. */
class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'admin']);
    }

    public function test_orders_export_honours_the_date_range_and_paid_filter(): void
    {
        Order::factory()->create(['reference' => 'GCT-IN', 'status' => 'paid', 'created_at' => '2026-10-10 12:00:00']);
        Order::factory()->create(['reference' => 'GCT-OLD', 'status' => 'paid', 'created_at' => '2026-09-01 12:00:00']);
        Order::factory()->create(['reference' => 'GCT-UNPAID', 'status' => 'pending', 'created_at' => '2026-10-11 12:00:00']);

        $csv = $this->actingAs($this->admin(), 'admin')
            ->get('/api/v1/admin/orders/export?from=2026-10-01&to=2026-10-31&paid=1')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('GCT-IN', $csv);
        $this->assertStringNotContainsString('GCT-OLD', $csv);
        $this->assertStringNotContainsString('GCT-UNPAID', $csv);
    }

    public function test_staff_cannot_export_orders(): void
    {
        $this->actingAs(AdminUser::factory()->create(['role' => 'staff']), 'admin')
            ->get('/api/v1/admin/orders/export')
            ->assertForbidden();
    }

    public function test_customers_export_lists_accounts(): void
    {
        Customer::factory()->create(['email' => 'ama@example.com']);

        $csv = $this->actingAs($this->admin(), 'admin')
            ->get('/api/v1/admin/customers/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('ama@example.com', $csv);
    }

    public function test_the_order_list_takes_a_bigger_page(): void
    {
        Order::factory()->count(30)->create();

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/api/v1/admin/orders?per_page=500')
            ->assertOk()
            ->assertJsonCount(30, 'data');
    }
}
