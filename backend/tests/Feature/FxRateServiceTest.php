<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Services\Currency\FxRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FxRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_latest_persists_a_new_rate_on_success(): void
    {
        Http::fake([
            'api.exchangerate.host/*' => Http::response([
                'success' => true,
                'quotes' => ['GHSUSD' => 0.0725],
            ]),
        ]);

        $fxRate = app(FxRateService::class)->fetchLatest();

        $this->assertNotNull($fxRate);
        $this->assertSame(0.0725, (float) $fxRate->rate);
        $this->assertDatabaseHas('fx_rates', ['source' => 'exchangerate.host']);
    }

    public function test_fetch_latest_returns_null_and_keeps_previous_rate_on_outage(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);

        Http::fake([
            'api.exchangerate.host/*' => Http::response(['success' => false], 500),
        ]);

        Log::shouldReceive('warning')->once();

        $result = app(FxRateService::class)->fetchLatest();

        $this->assertNull($result);
        // Feature 2 edge case: an outage at fetch time must never remove or
        // corrupt the previously cached rate that reads are still serving.
        $this->assertSame(1, FxRate::query()->count());
    }

    public function test_get_cached_rate_falls_back_to_the_last_successfully_fetched_rate(): void
    {
        $stale = FxRate::factory()->create(['rate' => 0.081, 'fetched_at' => now()->subDay()]);

        $cached = app(FxRateService::class)->getCachedRate();

        $this->assertSame($stale->id, $cached->id);
    }

    public function test_is_stale_detects_a_rate_older_than_the_threshold(): void
    {
        $service = app(FxRateService::class);

        $fresh = FxRate::factory()->make(['fetched_at' => now()]);
        $stale = FxRate::factory()->make(['fetched_at' => now()->subHours(FxRateService::STALE_AFTER_HOURS + 1)]);

        $this->assertFalse($service->isStale($fresh));
        $this->assertTrue($service->isStale($stale));
    }
}
