<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Feature 2 FX rate provider — see FxRateService. Free tier requires an
    // access_key query param (https://exchangerate.host/#/docs); swapping
    // providers later only touches this file + FxRateService.
    'exchangerate_host' => [
        'base_url' => env('EXCHANGERATE_HOST_URL', 'https://api.exchangerate.host'),
        'key' => env('EXCHANGERATE_HOST_KEY'),
    ],

    // Feature 4 payments. §13 of the brand document names Paystack as the
    // payment gateway, settling in GHS, and §22.12 repeats it — so this is
    // the only gateway the application routes to. See PaymentGatewayFactory.
    'paystack' => [
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'public' => env('PAYSTACK_PUBLIC_KEY'),
        'secret' => env('PAYSTACK_SECRET_KEY'),
        // Paystack signs webhooks with the secret key itself; this exists for
        // deployments that rotate a separate value into it.
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        // Where the customer is returned to after paying. The storefront, not
        // the API — the first entry in FRONTEND_URLS is the storefront by
        // convention (the second is admin).
        'callback_base_url' => env('STOREFRONT_URL', strtok(
            (string) env('FRONTEND_URLS', 'http://localhost:3000'),
            ',',
        )),
    ],

    // Retained but unrouted. The README's Feature 4 pairs Stripe with USD;
    // §13 of the brand document names Paystack alone and puts Visa,
    // Mastercard and Verve under it, which covers a dollar card payment. The
    // config binding stays so the settings panel can keep reporting honestly
    // that nothing is configured, and so restoring a second gateway is a
    // factory change rather than an archaeology exercise.
    // **Flagged for the business owner — see FOR_THE_TEAM.md.**
    'stripe' => [
        'public' => env('STRIPE_PUBLIC_KEY'),
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // Feature 5 couriers.
    // Yango Delivery, the Accra zone's on-demand courier. `key` is the OAuth
    // token from the Yango business cabinet (Integration tab), sent as
    // `Authorization: Bearer`. There is no documented sandbox: an accepted
    // claim sends a real courier, which is why YangoCarrier refuses to accept
    // a claim anywhere but production. Off until YANGO_ENABLED=true.
    'yango' => [
        'enabled' => (bool) env('YANGO_ENABLED', false),
        'base_url' => env('YANGO_API_BASE_URL', 'https://b2b.delivery.yango.tech'),
        'key' => env('YANGO_API_KEY'),
    ],

    // DHL Express (MyDHL API), the International zone's courier. HTTP Basic
    // auth with the API username and password a DHL Express account
    // consultant issues, plus the shipper account number. Defaults to the
    // test environment (500 calls a day), which creates no real shipment.
    // `api_version` is sent as the `x-version` header DHL requires.
    'dhl' => [
        'enabled' => (bool) env('DHL_ENABLED', false),
        'base_url' => env('DHL_API_BASE_URL', 'https://express.api.dhl.com/mydhlapi/test'),
        'username' => env('DHL_API_USERNAME'),
        'password' => env('DHL_API_PASSWORD'),
        'account_number' => env('DHL_ACCOUNT_NUMBER'),
        'api_version' => env('DHL_API_VERSION', '3.3.1'),
    ],

    // Feature 8 SMS.
    'fish_africa' => [
        'base_url' => env('FISH_AFRICA_BASE_URL', 'https://api.letsfish.africa'),
        'app_id' => env('FISH_AFRICA_APP_ID'),
        'app_secret' => env('FISH_AFRICA_APP_SECRET'),
    ],

];
