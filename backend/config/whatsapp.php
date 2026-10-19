<?php

/*
| WhatsApp Business Platform (Cloud API).
|
| Every value is an environment secret, never a database setting: the brand
| number is connected once, by the Super Admin, through Meta's Embedded
| Signup with coexistence, and the access token is a system-user token that
| would let anyone holding it speak as the brand.
|
| With no access token or phone number id, LogWhatsAppClient stands in, the
| same fallback FishAfrica/LogSms and Paystack/FakeGateway use, so the inbox
| works end to end in development and tests and the log says plainly that
| nothing was sent.
*/

return [
    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'waba_id' => env('WHATSAPP_WABA_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    // Meta signs every webhook with the app secret (X-Hub-Signature-256).
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    // Echoed back once, when Meta verifies the webhook URL.
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    // Free-form replies are allowed only this long after the customer's last
    // message; after that WhatsApp accepts an approved template only.
    'service_window_hours' => 24,

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
];
