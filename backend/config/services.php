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

    // Feature 4 payment gateways. Every value below is empty today; the
    // `.env.example` has carried these names since scaffolding but nothing
    // read them, so `config('services.paystack.secret')` was always null —
    // including in PaymentGatewayFactory, which therefore fell through to
    // FakeGateway for the right reason by accident. Binding them here makes
    // that check real, and lets the admin settings panel report honestly
    // whether payments are actually configured.
    'paystack' => [
        'public' => env('PAYSTACK_PUBLIC_KEY'),
        'secret' => env('PAYSTACK_SECRET_KEY'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    ],

    'stripe' => [
        'public' => env('STRIPE_PUBLIC_KEY'),
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // Feature 5 couriers.
    'yango' => [
        'base_url' => env('YANGO_API_BASE_URL'),
        'key' => env('YANGO_API_KEY'),
    ],

    'dhl' => [
        'base_url' => env('DHL_API_BASE_URL'),
        'key' => env('DHL_API_KEY'),
    ],

    // Feature 8 SMS.
    'fish_africa' => [
        'base_url' => env('FISH_AFRICA_BASE_URL', 'https://api.letsfish.africa'),
        'app_id' => env('FISH_AFRICA_APP_ID'),
        'app_secret' => env('FISH_AFRICA_APP_SECRET'),
    ],

];
