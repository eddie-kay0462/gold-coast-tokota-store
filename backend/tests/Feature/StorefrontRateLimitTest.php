<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront renders on Vercel, so its server-side fetches all arrive from
 * one address. With the shared key they are limited per forwarded visitor
 * address instead; without it, the forwarded address counts for nothing.
 */
class StorefrontRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-storefront-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.storefront.key' => self::KEY]);
    }

    /** Spend a caller's whole 120-a-minute budget. */
    private function exhaust(array $headers): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/categories', $headers)->assertOk();
        }
    }

    public function test_the_storefront_key_gives_each_visitor_their_own_budget(): void
    {
        $this->exhaust(['X-Storefront-Key' => self::KEY, 'X-Storefront-Client-IP' => '203.0.113.10']);

        $this->getJson('/api/v1/categories', ['X-Storefront-Key' => self::KEY, 'X-Storefront-Client-IP' => '203.0.113.10'])
            ->assertStatus(429);

        $this->getJson('/api/v1/categories', ['X-Storefront-Key' => self::KEY, 'X-Storefront-Client-IP' => '203.0.113.11'])
            ->assertOk();
    }

    public function test_a_forwarded_address_without_the_key_is_ignored(): void
    {
        $this->exhaust(['X-Storefront-Client-IP' => '203.0.113.10']);

        $this->getJson('/api/v1/categories', ['X-Storefront-Client-IP' => '203.0.113.11'])
            ->assertStatus(429);
    }

    public function test_a_wrong_key_is_ignored(): void
    {
        $this->exhaust(['X-Storefront-Key' => 'wrong', 'X-Storefront-Client-IP' => '203.0.113.10']);

        $this->getJson('/api/v1/categories', ['X-Storefront-Key' => 'wrong', 'X-Storefront-Client-IP' => '203.0.113.11'])
            ->assertStatus(429);
    }

    public function test_no_key_configured_means_no_exemption(): void
    {
        config(['services.storefront.key' => null]);

        $this->exhaust(['X-Storefront-Key' => '', 'X-Storefront-Client-IP' => '203.0.113.10']);

        $this->getJson('/api/v1/categories', ['X-Storefront-Key' => '', 'X-Storefront-Client-IP' => '203.0.113.11'])
            ->assertStatus(429);
    }
}
