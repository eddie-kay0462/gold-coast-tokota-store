<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Wildcard is incompatible with supports_credentials below — Sanctum's
    // cookie-based SPA auth requires an explicit origin allowlist. Two
    // separate frontend apps call this API: the storefront (../frontend)
    // and the admin dashboard (../admin), each its own Nuxt deployment.
    'allowed_origins' => array_filter(explode(',', env(
        'FRONTEND_URLS',
        'http://localhost:3000,http://localhost:3001',
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
