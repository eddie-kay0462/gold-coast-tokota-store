<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Holds a checkout quote between the delivery step and payment, so the price
 * the customer saw is the price checkout charges.
 *
 * A quote is only handed back for the same fingerprint it was made for. A
 * changed cart or a moved pin is a different delivery, and gets a new price.
 */
class QuoteCache
{
    public function put(QuoteRequest $request, QuoteResult $result): string
    {
        $id = (string) Str::uuid();

        Cache::put($this->key($id), [
            'fingerprint' => $request->fingerprint(),
            'result' => $result->toArray(),
        ], (int) config('shipping.quote_ttl_seconds'));

        return $id;
    }

    public function get(?string $id, QuoteRequest $request): ?QuoteResult
    {
        if ($id === null || ! Str::isUuid($id)) {
            return null;
        }

        $cached = Cache::get($this->key($id));

        if (! is_array($cached) || ! hash_equals($cached['fingerprint'], $request->fingerprint())) {
            return null;
        }

        return QuoteResult::fromArray($cached['result']);
    }

    private function key(string $id): string
    {
        return "shipping-quote:{$id}";
    }
}
