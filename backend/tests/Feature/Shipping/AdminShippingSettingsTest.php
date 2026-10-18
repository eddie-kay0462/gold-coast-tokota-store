<?php

namespace Tests\Feature\Shipping;

use App\Models\AdminUser;
use App\Models\ShippingSetting;
use App\Models\ShippingZoneRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regional rates, the Accra polygon and the free-shipping threshold, and who
 * may change each.
 */
class AdminShippingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const POLYGON = [
        'type' => 'Polygon',
        'coordinates' => [[[-0.30, 5.50], [-0.10, 5.50], [-0.10, 5.70], [-0.30, 5.70], [-0.30, 5.50]]],
    ];

    private function as(string $role): static
    {
        return $this->actingAs(AdminUser::factory()->create(['role' => $role]), 'admin');
    }

    // --- regional rates -------------------------------------------------

    public function test_the_rate_list_names_every_region_even_unsaved_ones(): void
    {
        ShippingZoneRate::factory()->create(['region' => 'Volta', 'amount_ghs' => 5_000]);

        $response = $this->as('staff')->getJson('/api/v1/admin/shipping/rates')->assertOk();

        $this->assertCount(16, $response->json('data'));
        $volta = collect($response->json('data'))->firstWhere('region', 'Volta');
        $this->assertSame(5_000, $volta['amount']['amount']);
        $this->assertTrue($volta['is_active']);
    }

    public function test_an_admin_can_set_rates(): void
    {
        $this->as('admin')->putJson('/api/v1/admin/shipping/rates', ['rates' => [
            ['region' => 'Ashanti', 'amount_ghs' => 4_000, 'is_active' => true],
            ['region' => 'Northern', 'amount_ghs' => 7_500, 'is_active' => false],
        ]])->assertOk();

        $this->assertSame(4_000, ShippingZoneRate::forRegion('ashanti')->amount_ghs);
        $this->assertNull(ShippingZoneRate::forRegion('Northern'));
    }

    /** §18: Staff move stock but do not set prices. A delivery rate is a price. */
    public function test_staff_cannot_set_rates(): void
    {
        $this->as('staff')->putJson('/api/v1/admin/shipping/rates', ['rates' => [
            ['region' => 'Ashanti', 'amount_ghs' => 1, 'is_active' => true],
        ]])->assertForbidden();

        $this->assertSame(0, ShippingZoneRate::query()->count());
    }

    public function test_a_region_must_be_one_of_ghanas_sixteen(): void
    {
        $this->as('admin')->putJson('/api/v1/admin/shipping/rates', ['rates' => [
            ['region' => 'Brong Ahafo', 'amount_ghs' => 4_000, 'is_active' => true],
        ]])->assertStatus(422)->assertJsonValidationErrors('rates.0.region');
    }

    // --- polygon and threshold ------------------------------------------

    public function test_an_admin_can_upload_the_accra_polygon_and_set_a_threshold(): void
    {
        $this->as('admin')->putJson('/api/v1/admin/shipping/settings', [
            'accra_polygon' => self::POLYGON,
            'free_shipping_threshold_ghs' => 150_000,
        ])->assertOk()
            ->assertJsonPath('data.accra_polygon_summary.polygons', 1)
            ->assertJsonPath('data.accra_polygon_summary.bounds.north', 5.7)
            ->assertJsonPath('data.free_shipping_threshold.amount', 150_000);

        $this->assertSame(150_000, ShippingSetting::current()->free_shipping_threshold_ghs);
    }

    public function test_the_threshold_and_polygon_can_be_cleared(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::POLYGON, 'free_shipping_threshold_ghs' => 150_000]);

        $this->as('admin')->putJson('/api/v1/admin/shipping/settings', [
            'accra_polygon' => null,
            'free_shipping_threshold_ghs' => null,
        ])->assertOk();

        $settings = ShippingSetting::current();
        $this->assertNull($settings->accra_polygon);
        $this->assertNull($settings->free_shipping_threshold_ghs);
    }

    /** A screen saving only the threshold must not wipe the polygon. */
    public function test_an_omitted_polygon_is_left_as_it_is(): void
    {
        ShippingSetting::current()->update(['accra_polygon' => self::POLYGON]);

        $this->as('admin')->putJson('/api/v1/admin/shipping/settings', [
            'free_shipping_threshold_ghs' => 200_000,
        ])->assertOk();

        $settings = ShippingSetting::current();
        $this->assertSame(self::POLYGON, $settings->accra_polygon);
        $this->assertSame(200_000, $settings->free_shipping_threshold_ghs);
    }

    /** The classic mistake: [lat, lng] puts Accra in the sea off Gabon. */
    public function test_a_polygon_with_swapped_coordinates_is_refused(): void
    {
        $swapped = ['type' => 'Polygon', 'coordinates' => [array_map(
            fn ($point) => [$point[1], $point[0]],
            self::POLYGON['coordinates'][0],
        )]];

        $this->as('admin')->putJson('/api/v1/admin/shipping/settings', [
            'accra_polygon' => $swapped,
            'free_shipping_threshold_ghs' => null,
        ])->assertStatus(422)->assertJsonValidationErrors('accra_polygon');
    }

    public function test_something_that_is_not_a_polygon_is_refused(): void
    {
        $this->as('admin')->putJson('/api/v1/admin/shipping/settings', [
            'accra_polygon' => ['type' => 'Point', 'coordinates' => [-0.18, 5.556]],
            'free_shipping_threshold_ghs' => null,
        ])->assertStatus(422)->assertJsonValidationErrors('accra_polygon');
    }

    public function test_staff_cannot_change_shipping_settings(): void
    {
        $this->as('staff')->putJson('/api/v1/admin/shipping/settings', [
            'accra_polygon' => self::POLYGON,
            'free_shipping_threshold_ghs' => null,
        ])->assertForbidden();
    }

    public function test_the_delivery_panel_reports_what_serves_each_zone(): void
    {
        $response = $this->as('staff')->getJson('/api/v1/admin/settings/delivery')->assertOk();

        $zones = collect($response->json('data.zones'))->keyBy('zone');
        // No courier API is built or switched on in a test: everything is manual.
        $this->assertSame(['yango', 'swoove'], $zones['accra']['configured']);
        $this->assertSame(['manual'], $zones['accra']['active']);
        $this->assertSame(['manual'], $zones['international']['active']);
    }
}
