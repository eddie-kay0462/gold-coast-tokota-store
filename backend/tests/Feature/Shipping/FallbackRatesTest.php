<?php

namespace Tests\Feature\Shipping;

use App\Models\AdminUser;
use App\Models\ShippingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The fallback delivery rates are set in admin, and are prices. */
class FallbackRatesTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): self
    {
        return $this->actingAs(AdminUser::factory()->create(['role' => $role]), 'admin');
    }

    public function test_accra_and_ghana_default_to_35_cedis(): void
    {
        $this->assertSame(3_500, ShippingSetting::fallbackRate('accra'));
        $this->assertSame(3_500, ShippingSetting::fallbackRate('ghana'));
        $this->assertSame(35_000, ShippingSetting::fallbackRate('international'));
    }

    public function test_an_admin_sets_them_and_the_settings_show_them(): void
    {
        $this->as('admin')
            ->putJson('/api/v1/admin/shipping/fallback-rates', ['accra_ghs' => 4_000, 'ghana_ghs' => 4_500, 'international_ghs' => 40_000])
            ->assertOk()
            ->assertJsonPath('data.fallback_rates.accra.amount', 4_000)
            ->assertJsonPath('data.fallback_rates.ghana.amount', 4_500)
            ->assertJsonPath('data.fallback_rates.international.amount', 40_000);

        $this->assertSame(4_500, ShippingSetting::fallbackRate('ghana'));
        $this->as('staff')->getJson('/api/v1/admin/settings/delivery')
            ->assertJsonPath('data.fallback_rates.accra.amount', 4_000);
    }

    public function test_staff_cannot_change_a_price(): void
    {
        $this->as('staff')
            ->putJson('/api/v1/admin/shipping/fallback-rates', ['accra_ghs' => 100, 'ghana_ghs' => 100, 'international_ghs' => 100])
            ->assertForbidden();

        $this->assertSame(3_500, ShippingSetting::fallbackRate('accra'));
    }

    public function test_rates_must_be_whole_and_sensible(): void
    {
        $this->as('admin')
            ->putJson('/api/v1/admin/shipping/fallback-rates', ['accra_ghs' => -1, 'ghana_ghs' => 'abc', 'international_ghs' => 2_000_000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accra_ghs', 'ghana_ghs', 'international_ghs']);
    }
}
