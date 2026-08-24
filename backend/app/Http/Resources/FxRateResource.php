<?php

namespace App\Http\Resources;

use App\Services\Currency\FxRateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FxRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'base_currency' => $this->base_currency,
            'quote_currency' => $this->quote_currency,
            'rate' => (float) $this->rate,
            'fetched_at' => $this->fetched_at,
            'source' => $this->source,
            // Feature 2 edge case: flag staleness rather than silently serving
            // a very old cached rate if the provider has been down a while.
            'is_stale' => app(FxRateService::class)->isStale($this->resource),
        ];
    }
}
