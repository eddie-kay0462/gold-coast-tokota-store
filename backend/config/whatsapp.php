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

    // Record every verified webhook body, unchanged, to
    // storage/logs/whatsapp-payloads.log. For the test-number rehearsal
    // (docs/deploy/whatsapp-rehearsal.md): it shows exactly what Meta sends, so
    // the coexistence field names can be checked against the processor. The
    // bodies hold customers' numbers and messages: switch it off afterwards.
    'log_payloads' => (bool) env('WHATSAPP_LOG_PAYLOADS', false),

    // Templates the system sends by name. They are written and approved in
    // WhatsApp Manager by the business; these are the names it looks for.
    'templates' => [
        // Sent when a payment link has to go outside the 24-hour window.
        // Four body values, in order: first name, order number, total, link.
        'payment_link' => env('WHATSAPP_TEMPLATE_PAYMENT_LINK', 'payment_link'),

        // Status updates outside the window (WhatsAppChannel). Inside it the
        // SMS wording goes as plain text and needs no template. Body values:
        //   order_placed       first name, order number, total
        //   order_shipped      first name, order number
        //   booking_confirmed  first name
        //   waitlist_promoted  first name
        // booking_submitted has none: it is only ever sent inside the window,
        // straight after the customer submits.
        'order_placed' => env('WHATSAPP_TEMPLATE_ORDER_PLACED', 'order_confirmed'),
        'order_shipped' => env('WHATSAPP_TEMPLATE_ORDER_SHIPPED', 'order_dispatched'),
        'booking_confirmed' => env('WHATSAPP_TEMPLATE_BOOKING_CONFIRMED', 'booking_confirmed'),
        'waitlist_promoted' => env('WHATSAPP_TEMPLATE_WAITLIST_PROMOTED', 'waitlist_place_open'),
        'booking_submitted' => null,
    ],
];
