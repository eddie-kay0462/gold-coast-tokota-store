<?php

/*
|--------------------------------------------------------------------------
| Shipping
|--------------------------------------------------------------------------
|
| How an order gets from the workshop to the customer: which carrier serves
| which zone, what the business charges when a carrier cannot quote, and how
| often active shipments are checked on.
|
| Carrier *credentials* live in config/services.php beside every other
| third-party key. This file holds behaviour only, and nothing in it is a
| secret.
|
| Things that change without a deploy — the Accra service polygon, the free
| shipping threshold and the regional flat rates — are not here. They live in
| the database (`shipping_settings`, `shipping_zone_rates`) and are edited in
| the admin app.
|
*/

return [

    /*
    | Driver chain per zone, tried in order. A driver whose `enabled` flag is
    | off (or that is not built yet) is skipped, and a chain that ends up empty
    | falls back to `manual` — so an order can always be placed and always be
    | dispatched by hand.
    */
    'zones' => [
        'accra' => ['yango', 'swoove'],
        'ghana' => ['manual'],
        'international' => ['dhl'],
    ],

    /*
    | Where couriers collect from. Yango needs coordinates for the source point
    | and DHL needs a shipper address, so both read this.
    */
    'origin' => [
        'name' => env('SHIPPING_ORIGIN_NAME', 'Gold Coast Tokota'),
        'phone' => env('SHIPPING_ORIGIN_PHONE'),
        'email' => env('SHIPPING_ORIGIN_EMAIL'),
        'address' => env('SHIPPING_ORIGIN_ADDRESS'),
        'city' => env('SHIPPING_ORIGIN_CITY', 'Accra'),
        'postcode' => env('SHIPPING_ORIGIN_POSTCODE', ''),
        'country' => 'GH',
        'lat' => env('SHIPPING_ORIGIN_LAT'),
        'lng' => env('SHIPPING_ORIGIN_LNG'),
    ],

    /*
    | Added on top of a live carrier quote, because the courier is booked at
    | "Mark as packed", up to 48 hours after the customer paid, and the price
    | can move in between. Never applied to flat or fallback rates — those are
    | the business's own numbers already.
    */
    'quote_buffer_percent' => (int) env('SHIPPING_QUOTE_BUFFER_PERCENT', 10),

    /* Live quotes are rounded up to this many minor units — 100 is a whole cedi. */
    'round_to_minor' => 100,

    /* How long a checkout quote is honoured before it is recomputed. */
    'quote_ttl_seconds' => (int) env('SHIPPING_QUOTE_TTL', 900),

    /*
    | What the customer is charged when a zone has no live carrier, or when a
    | live quote fails at checkout. GHS minor units. The defaults are the static
    | rates checkout charged before this module existed (₵25 domestic, ₵350
    | international), kept so turning this on changes no price.
    */
    'fallback_rates' => [
        'accra' => (int) env('SHIPPING_FALLBACK_ACCRA_GHS', 2_500),
        'ghana' => (int) env('SHIPPING_FALLBACK_GHANA_GHS', 2_500),
        'international' => (int) env('SHIPPING_FALLBACK_INTERNATIONAL_GHS', 35_000),
    ],

    /*
    | A Ghana region whose flat rate has not been set and switched on in admin.
    |   'fallback' — charge fallback_rates.ghana, which is what every Ghana
    |                order paid before regional rates existed. The default, so
    |                deploying this changes no price until rates are entered.
    |   'refuse'   — refuse the quote, and checkout points the customer at
    |                WhatsApp. Switch to this once every region you deliver to
    |                has its own rate.
    */
    'unpriced_regions' => env('SHIPPING_UNPRICED_REGIONS', 'fallback'),

    /*
    | A packed pair of sandals, for carriers that want a weight and size and a
    | product that has none on record yet. Grams and millimetres.
    */
    'default_parcel' => [
        'weight_grams' => (int) env('SHIPPING_DEFAULT_WEIGHT_GRAMS', 1_000),
        'length_mm' => (int) env('SHIPPING_DEFAULT_LENGTH_MM', 320),
        'width_mm' => (int) env('SHIPPING_DEFAULT_WIDTH_MM', 200),
        'height_mm' => (int) env('SHIPPING_DEFAULT_HEIGHT_MM', 120),
    ],

    /*
    | Queue for booking and polling jobs. 'default' works with the worker as
    | deployed; set 'shipping' once the worker runs `--queue=shipping,default`
    | (docs/deploy/contabo.md), so a backlog of other jobs can never push a
    | Yango acceptance past its 10-minute offer window.
    */
    'queue' => env('SHIPPING_QUEUE', 'default'),

    /*
    | Yango Delivery (Accra). Credentials are in config/services.php.
    */
    'yango' => [
        // courier | express | cargo. A pair of sandals fits a courier.
        'taxi_class' => env('YANGO_TAXI_CLASS', 'courier'),
        // How soon after creating a claim to look for its price. The offer
        // must be accepted within 10 minutes of being made.
        'confirm_poll_seconds' => (int) env('YANGO_CONFIRM_POLL_SECONDS', 30),
        // How often an accepted claim is checked on.
        'poll_seconds' => (int) env('YANGO_POLL_SECONDS', 60),
        // A claim still looking for a courier after this long is cancelled
        // and tried again.
        'courier_search_timeout_minutes' => (int) env('YANGO_COURIER_TIMEOUT_MINUTES', 15),
        // Yango bookings per order before falling back to the next carrier
        // in the Accra chain (Swoove), or to a person.
        'max_attempts' => 2,
    ],

    /*
    | DHL Express (International). Credentials are in config/services.php.
    */
    'dhl' => [
        // Tracking moves slowly; once an hour is plenty.
        'poll_seconds' => 60 * (int) env('DHL_POLL_MINUTES', 60),
        // Duties and taxes are the customer's, paid on arrival (§8).
        'incoterm' => 'DAP',
        // Show an estimate of those duties at checkout. Off until the
        // landed-cost response has been checked against DHL's test API: its
        // breakdown shape is the least certain part of the integration.
        'show_landed_cost' => (bool) env('SHIPPING_DHL_LANDED_COST', false),
        // Book a DHL pickup with each shipment. Off means drop-off.
        'request_pickup' => (bool) env('DHL_REQUEST_PICKUP', true),
        'pickup_close_time' => env('DHL_PICKUP_CLOSE_TIME', '17:00'),
        'pickup_location' => env('DHL_PICKUP_LOCATION', 'reception'),
        // Product codes to offer (comma-separated, e.g. "P"). Empty offers
        // every product DHL quotes for the destination.
        'products' => array_values(array_filter(explode(',', (string) env('DHL_PRODUCTS', '')))),
    ],

    /*
    | Who hears about shipments that need a person: a new manual order, a
    | courier that never turned up, a booking that failed.
    */
    'ops' => [
        'email' => env('OPS_ALERT_EMAIL'),
        'phone' => env('OPS_ALERT_PHONE'),
        // A shipment still `pending` after this long shows in the admin
        // "Needs attention" list. §21 promises 48h processing.
        'stale_pending_hours' => (int) env('SHIPPING_STALE_PENDING_HOURS', 48),
    ],

];
