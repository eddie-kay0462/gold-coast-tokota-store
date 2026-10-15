<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Read by Laravel's TrustProxies middleware. In production the API runs in
    | Docker behind the Caddy that fronts the shared Contabo box
    | (docs/deploy/contabo.md): Caddy -> the stack's nginx -> PHP-FPM. Every
    | request therefore reaches PHP from nginx's private address. Trusting it
    | lets the app take the customer's address and https from the
    | X-Forwarded-* headers Caddy set. Without it, request()->ip() is nginx's
    | for everyone, so the per-IP rate limit (120/min, AppServiceProvider) is
    | shared by the whole shop, and Laravel takes every request for plain http.
    |
    | Comma-separated addresses or CIDR ranges. Unset means trust nothing,
    | which is right locally.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
