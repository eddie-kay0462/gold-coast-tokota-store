<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin surface that had no spec but an obvious shape: customers, team,
 * shipments, charts, media and the read-only settings panels.
 */
class AdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'super_admin']);
    }

    private function admin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'admin']);
    }

    private function staff(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'staff']);
    }

    // --- customers ------------------------------------------------------

    public function test_staff_can_list_customers_with_their_order_counts(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ama Serwaa']);
        Order::factory()->count(2)->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/customers');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Ama Serwaa');
        $response->assertJsonPath('data.0.orders_count', 2);
    }

    /** Guest checkout means a Customer row can exist with no password. */
    public function test_has_account_distinguishes_a_registered_customer(): void
    {
        Customer::factory()->create(['password' => null]);

        $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/customers')
            ->assertJsonPath('data.0.has_account', false);
    }

    public function test_a_customer_detail_carries_their_order_history(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $customer->id]);
        Order::factory()->create();

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson("/api/v1/admin/customers/{$customer->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('orders'));
    }

    public function test_customers_are_read_only(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson("/api/v1/admin/customers/{$customer->id}")
            ->assertStatus(405);
    }

    // --- team -----------------------------------------------------------

    /**
     * Everyone can see who is on the team — §18 gives no tier a reason to be
     * surprised by that, and the dashboard's Roles & access screen is gated on
     * `team.view`, which Staff holds.
     */
    public function test_staff_can_see_the_team_but_not_change_it(): void
    {
        $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/team')->assertOk();

        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/team', [
            'name' => 'New Staffer',
            'email' => 'staff@goldcoasttokota.store',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role' => 'staff',
        ])->assertForbidden();
    }

    /**
     * §18: an Admin "cannot modify system-level settings" — users are the most
     * privileged of those. Until the role tiers widened, this was unenforceable
     * because `super_admin` could not exist.
     */
    public function test_an_admin_cannot_create_an_account(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/team', [
            'name' => 'New Staffer',
            'email' => 'staff@goldcoasttokota.store',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role' => 'staff',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Only the Super Admin can add or change team members.');
    }

    public function test_a_super_admin_can_create_a_staff_account(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'admin')->postJson('/api/v1/admin/team', [
            'name' => 'New Staffer',
            'email' => 'staff@goldcoasttokota.store',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role' => 'staff',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role', 'staff');
        $response->assertJsonPath('data.role_label', 'Staff');
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    /** An intern account with no expiry is just a weaker staff account. */
    public function test_an_intern_account_needs_an_access_expiry(): void
    {
        $payload = [
            'name' => 'Intern',
            'email' => 'intern@goldcoasttokota.store',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role' => 'intern',
        ];

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson('/api/v1/admin/team', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_expires_at');

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson('/api/v1/admin/team', [...$payload, 'access_expires_at' => now()->addDays(30)->toIso8601String()])
            ->assertCreated();
    }

    /** And an expiry on a permanent tier would silently lock someone out. */
    public function test_only_an_intern_can_have_an_access_expiry(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')->postJson('/api/v1/admin/team', [
            'name' => 'Staffer',
            'email' => 'staffer@goldcoasttokota.store',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role' => 'staff',
            'access_expires_at' => now()->addDays(30)->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors('access_expires_at');
    }

    /** A lapsed account still signs in — it has to, or nobody can see why. */
    public function test_a_lapsed_intern_holds_no_capabilities(): void
    {
        $intern = AdminUser::factory()->create([
            'role' => 'intern',
            'access_expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($intern, 'admin')->getJson('/api/v1/admin/orders');

        $response->assertForbidden();
        $this->actingAs($intern, 'admin')->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.has_lapsed', true)
            ->assertJsonPath('data.capabilities', []);
    }

    public function test_extending_an_interns_access_is_recorded(): void
    {
        $intern = AdminUser::factory()->create([
            'role' => 'intern',
            'access_expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($this->superAdmin(), 'admin')->putJson(
            "/api/v1/admin/team/{$intern->id}",
            ['access_expires_at' => now()->addDays(33)->toIso8601String()],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data.access_extensions'));
        $this->assertSame(30, $response->json('data.access_extensions.0.days'));
    }

    /** Otherwise you cannot undo it, and if you are the last one nobody can. */
    public function test_a_super_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->putJson("/api/v1/admin/team/{$admin->id}", ['role' => 'staff'])
            ->assertStatus(422);

        $this->assertSame('super_admin', $admin->fresh()->role);
    }

    public function test_the_last_super_admin_account_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $other = $this->staff();

        $this->actingAs($admin, 'admin')
            ->deleteJson("/api/v1/admin/team/{$other->id}")
            ->assertNoContent();

        // Now only one super admin remains; a second is needed to even try.
        $second = $this->superAdmin();
        $this->actingAs($second, 'admin')
            ->deleteJson("/api/v1/admin/team/{$admin->id}")
            ->assertNoContent();

        $this->actingAs($second, 'admin')
            ->deleteJson("/api/v1/admin/team/{$second->id}")
            ->assertStatus(422);
    }

    public function test_a_blank_password_on_update_leaves_it_unchanged(): void
    {
        $admin = $this->superAdmin();
        $target = $this->staff();
        $before = $target->password;

        $this->actingAs($admin, 'admin')
            ->putJson("/api/v1/admin/team/{$target->id}", ['name' => 'Renamed', 'password' => null])
            ->assertOk();

        $this->assertSame($before, $target->fresh()->password);
        $this->assertSame('Renamed', $target->fresh()->name);
    }

    // --- shipments ------------------------------------------------------

    /** A shipment is an order that has been paid for and has somewhere to go. */
    public function test_shipments_exclude_orders_that_were_never_paid(): void
    {
        Order::factory()->create(['status' => 'paid']);
        Order::factory()->create(['status' => 'shipped']);
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'cancelled']);

        $response = $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/shipments');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_shipments_can_be_filtered_by_courier(): void
    {
        Order::factory()->create(['status' => 'paid', 'delivery_provider' => 'yango']);
        Order::factory()->create(['status' => 'paid', 'delivery_provider' => 'dhl']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson('/api/v1/admin/shipments?provider=dhl');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // --- charts ---------------------------------------------------------

    public function test_charts_return_twelve_zero_filled_months(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/charts');

        $response->assertOk();
        $this->assertCount(12, $response->json('data.revenue_this_year'));
        $this->assertCount(12, $response->json('data.orders_last_year'));
        // A month with no orders is a real zero, not a gap in the line.
        $this->assertSame(0, $response->json('data.revenue_this_year.0.value'));
    }

    public function test_charts_count_orders_in_the_month_they_were_placed(): void
    {
        Order::factory()->create(['status' => 'paid', 'currency' => 'GHS', 'total' => 50_000]);

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/charts');

        $month = (int) now()->format('n') - 1;
        $response->assertJsonPath("data.revenue_this_year.{$month}.value", 50_000);
        $response->assertJsonPath("data.orders_this_year.{$month}.value", 1);
    }

    /**
     * Nothing collects traffic data — Feature 11 is unbuilt. An empty array
     * would render as a chart showing no traffic, which is a different and
     * false claim from "we are not measuring this".
     */
    public function test_traffic_series_are_null_rather_than_empty(): void
    {
        $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/dashboard/charts')
            ->assertJsonPath('data.traffic_by_source', null)
            ->assertJsonPath('data.traffic_by_device', null);
    }

    // --- media ----------------------------------------------------------

    public function test_staff_can_upload_an_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->image('sandal.jpg', 800, 1000),
            'alt_text' => 'A brown leather sandal',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.filename', 'sandal.jpg');
        $response->assertJsonPath('data.width', 800);
        $response->assertJsonPath('data.height', 1000);
        $response->assertJsonPath('data.alt_text', 'A brown leather sandal');

        Storage::disk('public')->assertExists(MediaAsset::first()->path);
    }

    /** Nothing a browser will execute may be uploaded and then linked from a page. */
    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->create('payload.php', 16, 'application/x-php'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_an_image_renamed_to_look_like_a_script_is_still_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->create('sandal.jpg.php', 16, 'image/jpeg'),
        ])->assertStatus(422);
    }

    /** The stored name is generated, so a hostile filename never touches disk. */
    public function test_the_original_filename_is_not_used_as_the_stored_path(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->image('../../../etc/passwd.jpg', 200, 200),
        ])->assertCreated();

        $this->assertStringNotContainsString('..', MediaAsset::first()->path);
    }

    public function test_deleting_an_asset_removes_the_file_too(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->image('sandal.jpg', 200, 200),
        ])->assertCreated();

        $asset = MediaAsset::first();

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson("/api/v1/admin/media/{$asset->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing($asset->path);
        $this->assertSame(0, MediaAsset::query()->count());
    }

    // --- settings panels ------------------------------------------------

    public function test_settings_panels_are_readable_by_staff(): void
    {
        foreach (['commerce', 'delivery', 'notifications', 'whatsapp'] as $panel) {
            $this->actingAs($this->staff(), 'admin')
                ->getJson("/api/v1/admin/settings/{$panel}")
                ->assertOk();
        }
    }

    /**
     * §18 is explicit that an Admin "cannot modify system-level settings [or]
     * payment credentials". Masked or not, this panel is where those live.
     */
    public function test_the_payments_panel_is_super_admin_only(): void
    {
        $this->actingAs($this->staff(), 'admin')
            ->getJson('/api/v1/admin/settings/payments')->assertForbidden();

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/api/v1/admin/settings/payments')
            ->assertForbidden()
            ->assertJsonPath('message', 'Payment credentials are restricted to the Super Admin.');

        $this->actingAs($this->superAdmin(), 'admin')
            ->getJson('/api/v1/admin/settings/payments')->assertOk();
    }

    /** §9's window is seven days from receipt. This panel used to say 30. */
    public function test_the_commerce_panel_quotes_the_published_policy_figures(): void
    {
        $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/settings/commerce')
            ->assertJsonPath('data.returns_window_days', 7)
            ->assertJsonPath('data.processing_hours', 48);
    }

    /** §8's international table, which two of these bands used to understate. */
    public function test_the_delivery_panel_quotes_the_published_delivery_times(): void
    {
        $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/settings/delivery')
            ->assertJsonPath('data.domestic_eta_label', '1–2 business days')
            ->assertJsonPath('data.international_bands.0.region', 'West Africa')
            ->assertJsonPath('data.international_bands.0.eta', '5–10 business days');
    }

    public function test_settings_panels_are_read_only(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->putJson('/api/v1/admin/settings/payments', ['paystack_enabled' => true])
            ->assertStatus(405);
    }

    /**
     * An admin panel that displays a live Stripe secret turns a session
     * compromise into a payments compromise.
     */
    public function test_payment_secrets_are_never_returned(): void
    {
        config([
            'services.stripe.secret' => 'sk_live_supersecretvalue',
            'services.stripe.public' => 'pk_live_abcdefgh1234',
        ]);

        $response = $this->actingAs($this->superAdmin(), 'admin')->getJson('/api/v1/admin/settings/payments');

        $body = $response->getContent();
        $this->assertStringNotContainsString('sk_live_supersecretvalue', $body);
        $this->assertStringNotContainsString('pk_live_abcdefgh1234', $body);
        // Masked to the last four — enough to tell two keys apart, not to use one.
        $response->assertJsonPath(
            'data.stripe_publishable_key_masked',
            str_repeat('•', strlen('pk_live_abcdefgh1234') - 4).'1234',
        );
        $response->assertJsonPath('data.stripe_enabled', true);
    }

    /** The panel must not claim payments work when no key is configured. */
    public function test_a_gateway_with_no_key_reports_itself_disabled(): void
    {
        config(['services.paystack.secret' => null]);

        $this->actingAs($this->superAdmin(), 'admin')->getJson('/api/v1/admin/settings/payments')
            ->assertJsonPath('data.paystack_enabled', false)
            ->assertJsonPath('data.paystack_public_key_masked', null);
    }

    public function test_whatsapp_reports_the_link_integration_it_actually_uses(): void
    {
        $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/settings/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.integration', 'wa_me_link');
    }

    public function test_every_new_endpoint_is_closed_to_guests(): void
    {
        foreach (['/customers', '/team', '/shipments', '/media', '/dashboard/charts', '/settings/payments'] as $path) {
            $this->getJson("/api/v1/admin{$path}")->assertUnauthorized();
        }
    }

    // --- DIY turnaround tiers -------------------------------------------

    public function test_staff_can_read_the_diy_turnaround_tiers(): void
    {
        SiteSetting::current()->update(['diy_turnaround_tiers' => [
            ['id' => 'kit', 'label' => 'DIY sandal kit', 'estimate' => '1-2 business days', 'sort_order' => 3],
            ['id' => 'standard', 'label' => 'Standard sandal order', 'estimate' => '1-2 business days', 'sort_order' => 1],
        ]]);

        $response = $this->actingAs($this->staff(), 'admin')
            ->getJson('/api/v1/admin/settings/diy-turnaround');

        $response->assertOk();
        // Sorted server-side, so every client renders the same order without
        // having to remember to.
        $response->assertJsonPath('data.0.id', 'standard');
        $response->assertJsonPath('data.1.id', 'kit');
    }

    /** The README lists DIY turnaround under Site Settings, which is Admin-tier. */
    public function test_staff_cannot_edit_the_diy_turnaround_tiers(): void
    {
        $this->actingAs($this->staff(), 'admin')
            ->putJson('/api/v1/admin/settings/diy-turnaround', ['tiers' => [
                ['id' => 'standard', 'label' => 'Changed', 'estimate' => 'instantly', 'sort_order' => 1],
            ]])
            ->assertForbidden();
    }

    public function test_an_admin_edit_reaches_the_public_endpoint(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/settings/diy-turnaround', ['tiers' => [
                ['id' => 'bulk', 'label' => 'Bulk orders', 'estimate' => '1-3 weeks', 'sort_order' => 1],
            ]])
            ->assertOk();

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.diy_turnaround_tiers.0.estimate', '1-3 weeks');
    }

    public function test_two_tiers_cannot_share_an_id(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/settings/diy-turnaround', ['tiers' => [
                ['id' => 'bulk', 'label' => 'One', 'estimate' => 'a week', 'sort_order' => 1],
                ['id' => 'bulk', 'label' => 'Two', 'estimate' => 'a month', 'sort_order' => 2],
            ]])
            ->assertStatus(422);
    }

    /**
     * "1-3 weeks (depending on quantity)" is a legitimate answer. Forcing a
     * number here would make it unsayable.
     */
    public function test_an_estimate_can_be_free_text(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/settings/diy-turnaround', ['tiers' => [
                ['id' => 'bulk', 'label' => 'Bulk', 'estimate' => '1-3 weeks (depending on quantity)', 'sort_order' => 1],
            ]])
            ->assertOk()
            ->assertJsonPath('data.0.estimate', '1-3 weeks (depending on quantity)');
    }
}
