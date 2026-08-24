<?php

namespace Tests\Feature;

use App\Models\FxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FxRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_fx_rate_endpoint_returns_the_latest_cached_rate(): void
    {
        FxRate::factory()->create(['fetched_at' => now()->subHours(2), 'rate' => 0.07]);
        $latest = FxRate::factory()->create(['fetched_at' => now(), 'rate' => 0.08]);

        $response = $this->getJson('/api/v1/fx-rate');

        $response->assertOk();
        $response->assertJsonPath('data.rate', (float) $latest->rate);
        $response->assertJsonPath('data.is_stale', false);
    }

    public function test_fx_rate_endpoint_returns_503_when_no_rate_has_ever_been_fetched(): void
    {
        $this->getJson('/api/v1/fx-rate')->assertStatus(503);
    }

    public function test_fx_rate_endpoint_flags_a_stale_rate(): void
    {
        FxRate::factory()->stale()->create();

        $response = $this->getJson('/api/v1/fx-rate');

        $response->assertOk();
        $response->assertJsonPath('data.is_stale', true);
    }
}
