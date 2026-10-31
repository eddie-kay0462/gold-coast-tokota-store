<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferred_currency' => $this->preferred_currency,
            // Whether they ever set a password. Guest checkout means a Customer
            // row can exist without one, so "has an account" and "has ordered"
            // are different questions.
            'has_account' => $this->password !== null,
            'orders_count' => $this->whenCounted('orders'),
            'order_count' => $this->whenCounted('orders'),
            // Spend in money actually taken, one figure per currency. USD is
            // null for a customer who never paid in dollars.
            'lifetime_value' => ['amount' => (int) ($this->lifetime_ghs ?? 0), 'currency' => 'GHS'],
            'lifetime_value_usd' => $this->lifetime_usd ? ['amount' => (int) $this->lifetime_usd, 'currency' => 'USD'] : null,
            'last_order_at' => $this->last_order_at ? Carbon::parse($this->last_order_at)->toIso8601String() : null,
            // Where their latest order went. A customer keeps no address of
            // their own, so someone who has never ordered has no country.
            'country_code' => $this->countryCode(),
            'country' => $this->countryName(),
            'created_at' => $this->created_at,
            'registered_at' => $this->created_at,
        ];
    }

    private function countryCode(): ?string
    {
        $code = $this->relationLoaded('latestOrder') ? ($this->latestOrder?->shipping_address['country'] ?? null) : null;

        return is_string($code) && $code !== '' ? strtoupper($code) : null;
    }

    private function countryName(): ?string
    {
        $code = $this->countryCode();
        if ($code === null) {
            return null;
        }
        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, 'en') : '';

        return $name !== '' && $name !== $code ? $name : $code;
    }
}
