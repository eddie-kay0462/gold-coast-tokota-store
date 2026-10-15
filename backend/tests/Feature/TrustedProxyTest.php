<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Production reaches PHP through Caddy and then the stack's nginx
 * (docs/deploy/contabo.md). The customer's address and https only come
 * through when that hop is trusted, and only when it is.
 */
class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/client', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
        ]);
    }

    private function viaCaddy()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeaders([
                'X-Forwarded-For' => '41.66.200.7',
                'X-Forwarded-Proto' => 'https',
            ])
            ->getJson('/_test/client');
    }

    public function test_forwarded_headers_are_used_from_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16']);

        $this->viaCaddy()->assertExactJson(['ip' => '41.66.200.7', 'secure' => true]);
    }

    public function test_forwarded_headers_are_ignored_when_no_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->viaCaddy()->assertExactJson(['ip' => '172.18.0.5', 'secure' => false]);
    }

    public function test_forwarded_headers_are_ignored_from_outside_the_trusted_range(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);

        $this->viaCaddy()->assertExactJson(['ip' => '172.18.0.5', 'secure' => false]);
    }
}
