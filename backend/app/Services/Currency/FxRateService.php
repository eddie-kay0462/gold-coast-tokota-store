<?php

namespace App\Services\Currency;

use App\Models\FxRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FxRateService
{
    private const BASE_CURRENCY = 'GHS';

    private const QUOTE_CURRENCY = 'USD';

    private const CACHE_KEY = 'fx-rate:latest';

    private const CACHE_TTL_SECONDS = 300;

    public const STALE_AFTER_HOURS = 24;

    /**
     * Fetch the live rate from exchangerate.host and persist it as the new
     * latest FxRate row. On failure, logs and returns null — callers should
     * fall back to getCachedRate(), which keeps serving the last good value
     * (Feature 2 edge case: a provider outage must never break product reads).
     */
    public function fetchLatest(): ?FxRate
    {
        try {
            $response = Http::timeout(10)->get(
                config('services.exchangerate_host.base_url').'/live',
                array_filter([
                    'access_key' => config('services.exchangerate_host.key'),
                    'source' => self::BASE_CURRENCY,
                    'currencies' => self::QUOTE_CURRENCY,
                ]),
            );

            $response->throw();

            $rate = $response->json('quotes.'.self::BASE_CURRENCY.self::QUOTE_CURRENCY);

            if (! is_numeric($rate)) {
                throw new \RuntimeException('exchangerate.host response missing expected quote.');
            }

            $fxRate = FxRate::create([
                'base_currency' => self::BASE_CURRENCY,
                'quote_currency' => self::QUOTE_CURRENCY,
                'rate' => $rate,
                'fetched_at' => now(),
                'source' => 'exchangerate.host',
            ]);

            Cache::forget(self::CACHE_KEY);

            return $fxRate;
        } catch (Throwable $e) {
            Log::warning('FxRateService: refresh failed, continuing to serve last cached rate.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Latest known rate, short-TTL cached. Never throws — returns null only if no rate has ever been fetched. */
    public function getCachedRate(): ?FxRate
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => FxRate::query()->latestFetched()->first(),
        );
    }

    public function isStale(FxRate $fxRate): bool
    {
        return $fxRate->fetched_at->lt(now()->subHours(self::STALE_AFTER_HOURS));
    }
}
