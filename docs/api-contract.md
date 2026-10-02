# API contract

The shared source of truth between `backend/` and the two frontends. When the
storefront reads a field, it is because this document says the API sends it.

Written because the two halves had already drifted: the storefront's
`ApiProduct` type declared thirteen fields `ProductResource` never emitted, and
nobody found out, because every storefront fetch ends in `.catch(() => null)`
and falls back to fixtures. A broken endpoint and a working one looked identical
in the browser.

**If you change a response shape, change it here in the same commit.**

---

## Conventions

| Rule | Why |
|---|---|
| **Stale FX rates are dropped, not shown.** Beyond 24h, `price_usd` and `compare_at_usd` go null and USD checkout returns 503 | A dead provider must not break product reads, but it must not let us charge a week-old rate either. `/fx-rate` still serves the stale value flagged `is_stale` |
| Every API route carries a 120/min baseline throttle | Floor under everything, so a route added later is never unthrottled. Tighter limits: checkout 20/min, feedback 10/min, login per-email+IP |
| All money is in **minor units** (pesewas / cents) as integers | No float arithmetic on money; `60000` is GH₵600.00 |
| **USD is always derived** from GHS × the cached `FxRate`, never stored | README Feature 2. The one exception is `orders.fx_rate_applied`, snapshotted at checkout so a charged amount can't move |
| A price and its was-price convert on the **same** rate | `price_usd` and `compare_at_usd` are computed from one `FxRate` read |
| `sizes` are **strings**, not integers | PHP casts numeric array keys to ints; the storefront's size facet compares against strings, so an int list matches nothing — silently |
| Stock-derived fields only appear when `inventoryItems` is loaded | `in_stock`, `merchandising_badge`, `sizes`, `size_availability`, `variant_availability` |
| Listing and detail return the **same** product shape | A separate listing resource is one more place for the contract to drift |

---

## `Product`

Consumed by `frontend/utils/catalog.ts` (`ApiProduct`). Served by
`GET /api/v1/products` and `GET /api/v1/products/{slug}`.

| Field | Type | Source | Notes |
|---|---|---|---|
| `id` `name` `slug` `sku` | | column | |
| `description` | string¦null | column | Long-form body copy |
| `description_heading` | string¦null | column | Titles the body copy |
| `model_note` | string¦null | column | e.g. "Model is 5′11″, wearing a size 42" |
| `category` | object¦null | relation | Top-level split — Sandals, Ahenema |
| `collection` | object¦null | relation | Merchandising group — Obrempong, Sikapa, Slides |
| `base_price_ghs` | int | column | Minor units |
| `compare_at_ghs` | int¦null | column | Was-price. Present **only** while on sale, and must exceed `base_price_ghs` |
| `price_usd` | int¦null | derived | `base_price_ghs` × FxRate. `null` if no rate has ever been fetched |
| `compare_at_usd` | int¦null | derived | Same rate as `price_usd` |
| `images` | string[] | column | Paths. **Never empty in seeded data** — `ProductGallery` has no placeholder, so an empty array renders the detail page as a bare grey frame |
| `color` | string¦null | column | The colourway pictured |
| `colors` | `{name, hex}[]` | column | Swatch row. `jsonb`, not a table — see "Open decisions" |
| `colour_images` | `{colour: string[]}` | column | Which of `images` show which colourway (2 Oct). The gallery shows the chosen colour's photos. Its own column, not inside `colors`, because the colour filter substring-searches `colors` |
| `product_type` | string¦null | column | `ahenema` ¦ `slippers` ¦ `sandals` ¦ `closed-toe`. What the listing sidebar's "Category" facet filters on |
| `departments` | string[] | column | `mens` ¦ `womens` ¦ `kids`. What the header nav's `?category=` resolves to |
| `widths` | string[] | column | `s` ¦ `m` ¦ `l` |
| `tags` | string[] | column | Free text, e.g. "Custom Made", "Renewed Materials" |
| `materials` | string[] | column | What the style is made of, in the brand's order, e.g. "Soft leather", "Welt". Not a card badge — that is `tags` |
| `is_pre_order` | bool | column | Renders a Pre-Order badge, blocks add-to-cart |
| `is_active` `is_featured` | bool | column | |
| `in_stock` | bool | inventory | Sellable (available − reserved) > 0 |
| `merchandising_badge` | string¦null | inventory | `out_of_stock` / `limited_stock` always computed live; `back_in_stock` is the one editorial value |
| `sizes` | string[] | inventory | The full range, **including** out-of-stock sizes — they render struck through |
| `size_availability` | `{size: int}` | inventory | Sellable count per size, **summed across colours** |
| `variant_availability` | `{colour: {size: int}}` | inventory | Sellable count per colour and size (2 Oct). What the purchase panel strikes through once a colour is chosen. Rows without a colour (a style with no colour axis) are left out. Also on `GET /products/{slug}/stock` |
| `cost_breakdown` | `{label, amount_ghs, icon}[]` | column | The "Transparent Pricing" panel. Ordered editorial content |
| `rating` | object¦null | **not built** | See "Open decisions" |
| `reviews` | array | **not built** | See "Open decisions" |

### Not sent, and deliberately so

`sizes` and `size_availability` are computed from `InventoryItem`, never stored.
Anything that writes them directly is a bug.

---

## Endpoints

### Live

| Method | Path | Notes |
|---|---|---|
| GET | `/products` | Paginated, 12/page. `?category_id=` `?featured=` |
| GET | `/products/{slug}` | |
| GET | `/products/{slug}/stock` | Live stock for the polling composable — see below |
| GET | `/categories` `/collections` | `/categories` lists only categories with at least one active product (1 Oct) — an empty one would be a link to an empty shop page |
| GET | `/fx-rate` | |
| GET | `/pages/{slug}` · `/site-settings` | |
| GET | `/blog-posts` | Paginated, 9/page. `?limit=` (clamped 1–24) |
| GET | `/blog-posts/{slug}` | |
| POST | `/checkout/session` | Guest-friendly. Throttled 20/min — see below |
| GET | `/orders/{reference}` | Throttled 60/min. **Reference, never the numeric id** |
| POST | `/register` · `/login` | Customer sessions, `web` guard |
| POST | `/logout` · GET `/me` · GET `/orders` | `auth:web`. `/orders` is the signed-in customer's own history |
| POST | `/feedback` | Guest-friendly. Throttled 10/min |
| POST | `/newsletter` | |
| GET | `/workshop-types` | The six experiences §15 publishes, active only |
| GET | `/workshop-sessions` · POST `/bookings` | Sessions carry their `workshop_type` |
| POST | `/webhooks/paystack` | HMAC-signed, idempotent. Throttled 300/min — see below |
| POST | `/admin/login` · `/admin/logout` · GET `/admin/me` | Sanctum cookie session, `admin` guard. Login takes optional `remember` (boolean, the "Keep me signed in" box). Writes need the `X-XSRF-TOKEN` header from the `XSRF-TOKEN` cookie, re-read after login — Laravel rotates it |
| GET | `/admin/inventory` | Admin **and** Staff. `?low_stock=true` `?product_id=` `?per_page=` — 50/page by default, max 500 (one row per size; the admin screen asks for 500) |
| PATCH | `/admin/inventory/{id}` | `inventory.adjust` (Staff and up; not Intern). Body: `quantity_available` and/or `low_stock_threshold`, both absolute integers ≥ 0. `422` if the count would fall below `quantity_reserved`. `quantity_reserved` is never writable. Returns the row |
| GET | `/admin/feedback` | Admin **and** Staff. Read-only, newest first — 50/page |
| GET | `/admin/dashboard/metrics` | Admin **and** Staff. Live queries, no caching |
| GET | `/admin/orders` · `/admin/orders/{reference}` | `?status=` `?q=` — 25/page |
| PATCH | `/admin/orders/{reference}` | Status. **`refunded` needs `orders.refund`**. `delivered` stamps `delivered_at` |
| GET/POST | `/admin/returns` · GET `/admin/returns/{id}` | `returns.view`. `?status=` `?reason=` — 25/page |
| PATCH | `/admin/returns/{id}` | `returns.resolve` (Admin+). `refunded` also needs `orders.refund` |
| GET | `/admin/bookings` | `?status=` `?type=` `?workshop_session_id=` |
| PATCH | `/admin/bookings/{id}` | Status, incl. waitlist promotion |
| GET | `/admin/workshop-types` | `bookings.view`. Read-only — the published programme |
| GET/POST | `/admin/workshop-sessions` | `?upcoming=true` `?type=` on index. `workshop_type_id` required on create |
| PUT/DELETE | `/admin/workshop-sessions/{id}` | Capacity editing, guarded — see below |
| GET/POST | `/admin/blog` · GET/PUT/DELETE `/admin/blog/{id}` | Includes drafts. Body sanitised server-side. Returns `AdminBlogPostResource` (1 Oct), not the storefront shape: adds `excerpt`, `cover_image_alt`, `meta_description`, `author_name` (the `author` column), `is_published`, `updated_at`; text fields are `''` rather than null. Writes accept `excerpt` (≤500), `cover_image_alt`, `meta_description` (≤200) |
| GET | `/admin/pages` · `/admin/pages/{id}` | |
| PUT | `/admin/pages/{id}` | Body sanitised. **No create or delete** — see below |
| GET | `/admin/site-settings` | Staff may read |
| PUT | `/admin/site-settings` | **Admin only** |
| GET | `/admin/newsletter` · `/admin/newsletter/export` | Read-only. Export streams CSV |
| GET | `/admin/categories` | Categories and collections together |
| GET | `/admin/customers` · `/admin/customers/{id}` | Read-only. `?q=` |
| GET | `/admin/team` | `team.view` — every tier holds it |
| POST/PUT/DELETE | `/admin/team/{id}` | **`team.manage` — Super Admin only** |
| GET | `/admin/shipments` | A view over orders. `?provider=` `?status=` |
| GET | `/admin/dashboard/charts` | Year-over-year series |
| GET/POST | `/admin/media` · DELETE `/admin/media/{id}` | Image library |
| GET | `/admin/settings/{commerce\|delivery\|notifications\|whatsapp}` | Read-only config reflections. `settings.view` |
| GET | `/admin/settings/payments` | **`settings.payments` — Super Admin only** (§18) |
| GET | `/admin/settings/diy-turnaround` | Per-order-type estimates. Staff may read |
| PUT | `/admin/settings/diy-turnaround` | `settings.write` — Admin+ |
| GET | `/admin/products` · `/admin/products/{id}` | `products.view` — every tier. **Unscoped:** drafts included. `?active=` `?q=` `?category_id=` `?per_page=` |
| POST/PUT | `/admin/products` | `products.write` **and** `pricing.write` — Admin+ |
| DELETE | `/admin/products/{id}` | `products.delete` — Admin+ |

#### The admin product shape is not the storefront's

All five `/admin/products` endpoints — reads *and* writes — return
`AdminProductResource`, not the storefront's `ProductResource`. Three
differences, each load-bearing:

- **`base_price_ghs` is `{ amount, currency }`**, not a bare integer, matching
  the admin app's `Money` type. The write endpoints used to echo the storefront
  shape, which the admin app would have read as a malformed object; that was
  fixed when the read endpoints landed rather than left as two shapes for one
  entity.
- **No `price_usd`.** The admin screens derive dollars at render time from the
  cached rate so the editable cedi field and the read-only dollar figure move
  together as you type. README Feature 2 forbids a stored USD price, and a
  server-side one here would be a second source of truth for it.
- **Stock is rolled up** (`total_available`, `total_reserved`, `low_stock`)
  rather than per-variant. `low_stock` is true when *any* variant is at or
  below its own threshold — a product with 60 of size 42 and none of size 39
  needs a restock, and comparing summed totals would hide that.

`GET /admin/products` pages at 100 (override with `?per_page=`, capped at 200),
far above the storefront's 12: the admin table searches, sorts and pages
client-side over the set it is handed, so a small page would leave those
controls quietly operating on a fraction of the catalogue.


### Specified, not yet built

| Method | Path | Consumer | Stage |
|---|---|---|---|
| GET | `/admin/inbox/{threads\|messages\|templates}` | admin app | new scope — see open decisions |
| GET | `/admin/activity` · `/admin/audit` | admin app | new scope — see open decisions |

`/webhooks/paystack` shipped; `/webhooks/stripe` will not — see the payments
note below.

`POST /checkout/session` has its request and response already written out in a
comment at `frontend/components/checkout/PaymentStep.vue:13`:

```
POST /checkout/session { items, currency, shipping_address, delivery_method }
  → GHS: a Paystack authorization URL to redirect to
  → USD: a Stripe PaymentIntent client secret to confirm
  → gateway redirects back to /order-confirmation/{id}
```

**The USD line is now out of date, and the storefront needs a small change.**
§13 of `GOLD_COAST_TOKOTA.md` names Paystack as the payment gateway and does
not mention Stripe; both currencies route to Paystack, so `client_secret` is
always `null` and USD gets an `authorization_url` exactly as GHS does. The
currency branch in `PaymentStep.vue` has nothing on the other side of it.
Redirect on `authorization_url` whenever it is present — that is correct today
and stays correct if a second gateway is ever added back.

---

## Catalogue filtering (`GET /products`)

Server-side as of 8 Sep. The query string is the one
`frontend/pages/shop/index.vue` already sends, unchanged — the page was written
against it, and a different contract would mean editing both sides for nothing.

| Param | Meaning |
|---|---|
| `q` | Free text over name, colour, product type and tags |
| `type` | `product_type` — the sidebar's "Category" group |
| `color` | Substring of any colourway name (`tan` finds "Tan Leather") |
| `size` | Any variant made in that size, in stock or not |
| `width` | `widths` array |
| `category` | **The department** (`mens`/`womens`/`kids`/`merchandise`), *not* the catalogue category |
| `category_id` · `collection_id` | The real taxonomy ids |
| `sale=true` | `compare_at_ghs` present **and** genuinely higher |
| `featured=true` | Featured only |
| `sort` | `newest` · `best-selling` (default: featured first, then newest) |
| `per_page` | Default 12, capped at 48 |

Values are comma-separated. **Within one facet the match is OR; across facets
it is AND** — two colours widen the result, a colour plus a size narrows it.

Four things worth knowing before editing `ProductFilter`:

- **`?category=` is the department, not the category.** The sidebar's
  "Category" group filters `type` instead. Sharing one key would filter every
  product out, which is why the storefront comments on it so emphatically.
- **`sort=top-rated` is not honoured, and cannot be.** There is no ratings data
  anywhere in this system — reviews are unplanned scope with a fully built UI
  and no model (open decision 24). Inventing a `rating` column to satisfy the
  sort would be building the reviews feature by the back door. An unrecognised
  sort falls through to the default rather than erroring, because a shopper who
  clicks "Top Rated" should still get products.
- **`best-selling` sums `quantity`, not order lines**, and counts only orders
  where money settled and stayed (`paid`/`processing`/`shipped`/`delivered`) —
  a product everybody sent back must not lead the best-sellers.
- **Search deliberately covers the same fields as the storefront's own local
  predicate.** The page falls back to filtering a bundled catalogue when the API
  is unreachable, and a search returning different things depending on whether
  the API answered is a miserable bug to chase.

Every clause is portable across Postgres and SQLite (known issue 29) —
`whereJsonContains` compiles to `@>` on one and a `json_each` subquery on the
other, and `LOWER(CAST(col AS text)) LIKE ?` works on both. `ILIKE` is
deliberately absent. **The filter suite was run against both drivers**, not
just the SQLite default.

---

## Stock (`GET /products/{slug}/stock`)

Polled every 15–30s by `frontend/composables/useInventoryPolling.ts` while a
product detail page is open, and paused when the tab is backgrounded.

```json
{ "data": {
  "slug": "kentehene-collection",
  "quantity_available": 22,
  "size_availability": { "39": 4, "40": 9, "41": 6, "42": 0, "43": 3 },
  "in_stock": true,
  "merchandising_badge": null
} }
```

**`quantity_available` is sellable stock** — physical minus reserved — not the
raw `inventory_items.quantity_available` column. A unit held by someone else's
in-progress payment is not purchasable, and offering it is worse than showing it
as gone. The key keeps the name the composable already reads; the storefront's
sense of "available" has always meant "available to buy".

**This endpoint cannot oversell anything.** It takes no locks and creates no
reservations. Correctness lives in `InventoryReservationService` at checkout,
and holds regardless of how stale this response is by the time someone acts on
it — which is what makes a polling interval an acceptable design here at all.

`size_availability` is included even though the composable only reads the
aggregate today: `ProductPurchasePanel` takes a `liveStock` prop as a per-size
map and nothing currently passes it. Wiring those two together is a frontend
change with no further API work.

**`/admin/inventory` reports the opposite number on purpose.** Its
`?low_stock=true` filter compares raw `quantity_available <= low_stock_threshold`,
ignoring reservations, because it answers "what do we need to make more of" —
and a reserved unit is still on the shelf. Rows carry both counts plus
`sellable_quantity`, since "12 in stock, 11 spoken for" is a very different
operational picture from "12 in stock".

---

## Checkout (`POST /checkout/session`)

```json
{
  "items": [{ "slug": "domfo", "size": "42", "quantity": 1 }],
  "currency": "GHS",
  "delivery_method": "standard",
  "shipping_address": {
    "full_name": "…", "email": "…", "phone": "…",
    "line1": "…", "city": "…", "region": "…", "postcode": "…", "country": "GH"
  }
}
```

Each line names its stock row **either** as `inventory_item_id` **or** as
`slug` + `size` (1 Oct) + `colour` (2 Oct). `colour` is matched
case-insensitively; it is required when the size comes in more than one
colourway (`422` on `items.N.colour` otherwise), refused if the style isn't made
in it, and ignored for a style with no colour axis. The order line's
`variant_label` reads `"42 | Tan"`. The storefront sends the second: its cart keys lines
by product and size and has never held real row ids. An unknown slug, or a size
the style isn't made in, is a `422` on `items.N.size`.

**Browser callers need Sanctum's CSRF handshake** (`GET /sanctum/csrf-cookie`,
then `X-XSRF-TOKEN` on the POST) because the storefront origin is a stateful
domain. Without it every POST from the storefront is a `419`. The same applies
to `/newsletter`, `/feedback`, `/bookings` and `/booking-uploads`. Use
`frontend/composables/useApi.ts`, not bare `$fetch`.

Returns `201` with `{ data: <Order>, payment: { gateway, reference, authorization_url, client_secret } }`.
**Both currencies get an `authorization_url` to redirect to**, and
`client_secret` is always `null` — see the payments note above. `country` is
**required** — it routes the courier, so a missing one is a validation error
rather than an unpriced order.

The `Order` in `data` also carries `processing_hours` (48) and a
`delivery_estimate` band resolved from the destination, both straight from §8
of the brand document, so the confirmation page quotes the published figure
rather than inventing one.

**No prices are accepted from the request.** The client sends an inventory item
and a quantity; unit price, shipping and total are all read or computed
server-side. Anything price-shaped in the body is ignored, and there is a test
that says so.

### What it does, in order

1. Re-price every line from the database.
2. Quote shipping *before* payment, so the figure shown is the figure charged.
3. Lock the FX rate for USD, once — one read for the whole order, so the
   subtotal and the shipping can't convert on rates either side of the hourly
   refresh. No rate cached → `503`, never a guessed rate.
4. Reserve stock through `InventoryReservationService` (15-minute TTL).
5. Only then open the gateway session.

All of it inside one transaction: a failure at any step rolls the order away
*and* releases anything already reserved.

### Failure codes

| Code | Means |
|---|---|
| `409` | Sold out, or the product went inactive. Body carries `inventory_item_id`, `available`, and `line` — the index of the offending entry in `items`, so the storefront can name it. Not `422` — the request was fine, the world changed |
| `422` | Validation, including a missing `country` or an unresolvable `slug`/`size` |
| `503` | USD checkout with no FX rate to lock, **or production with no `PAYSTACK_SECRET_KEY`** |

### Without a Paystack key

Outside production the API answers with `FakeGateway`. Its
`authorization_url` is `{API}/fake-gateway/{reference}`, a dev-only route that
does what a successful charge plus its webhook would: marks the order paid,
fires `OrderPaid`, then redirects to `{STOREFRONT_URL}/order-confirmation/{order reference}`.
`?outcome=cancel` returns without paying. **That route is not registered in
production**, and a keyless production refuses checkout with `503` instead. A
fake gateway there would be a free order for anyone.

`OrderPaid`'s listeners (stock finalising, the confirmation email) are
**queued**. Locally, run `php artisan queue:work` or they wait in the `jobs`
table.

### Payments: one gateway, Paystack

§13 of `GOLD_COAST_TOKOTA.md` names Paystack as the payment gateway — settling
in Ghana Cedis, accepting Visa, Mastercard, Verve, MTN/Telecel/AirtelTigo mobile
money and bank transfer — and §22.12 repeats it in a line. Nothing in the
document mentions Stripe. `PaymentGatewayFactory` therefore routes **both**
currencies to Paystack; the currency split is gone, and so is
`client_secret`.

`PaystackService` is complete and untested against the live API, because
`PAYSTACK_SECRET_KEY` is still empty. Amounts are sent in the currency's
subunit, which is what every money column in this codebase already holds, so
nothing is scaled on the way out — there is a test asserting that specifically,
since a factor of a hundred here is a real charge of the wrong size.

Without a key, `PaymentGatewayFactory` still resolves `FakeGateway` in every
environment and logs that it did. It shapes its response like the real thing so
the storefront can be built now, but it never moves money and never confirms
anything — an order it opens stays `pending` until a webhook says otherwise,
which is exactly how Paystack behaves.

### `POST /webhooks/paystack`

Where an order actually becomes paid. Three properties, in order:

1. **Authenticity.** HMAC-SHA512 of the *raw* body with the secret key,
   compared constant-time against `x-paystack-signature`. Unsigned or
   mis-signed bodies get `401` and change nothing. This is the only thing
   separating a real payment notification from someone marking their own order
   paid.
2. **Idempotency.** `processed_webhook_events` carries a unique
   `(gateway, event_id)`; the insert either succeeds — first delivery — or
   violates the constraint, in which case the retry is acknowledged and
   dropped. Without it a replay would finalise the same reservation twice: one
   sale, two decrements. There is a test.
3. **Speed.** Everything downstream of "the money arrived" hangs off the queued
   `OrderPaid` event, so a slow mailer cannot cause a timeout that triggers a
   retry of a payment already recorded.

A signed request always gets `200`, including for events nothing acts on — a
4xx would put Paystack into a retry loop over a message that is never going to
be interesting. A charge whose amount or currency does not match the order is
logged and refused: a partial payment is not a completed sale.

Point the Paystack dashboard at `POST {API}/api/v1/webhooks/paystack`.

### Delivery, until courier credentials exist

`YangoService` and `DhlService` implement the real interface with static rate
tables. Ghana standard is ₵25, free over ₵1,500 — matching what the product
page already promises — and express is ₵50. International is ₵350 / ₵600.
Swapping in live quotes changes only the body of `quote()`.

Delivery *times*, unlike rates, are not guesses: `DeliveryEstimates` is §8's
published table transcribed, and every surface reads it rather than carrying
its own copy.

## Notifications (Feature 8)

Not an endpoint — nothing about notifications is client-driven — but part of
the contract, because five customer-facing messages now fire from server-side
triggers.

| Trigger | Fires on | Source |
|---|---|---|
| `order_placed` | `OrderPaid`, from the Paystack webhook | README Feature 8 |
| `order_shipped` | admin order status → `shipped` | brand document, Domestic Shipping |
| `booking_submitted` | `POST /bookings` | README Feature 8 |
| `booking_confirmed` | admin booking status → `confirmed` | README Feature 8 |
| `waitlist_promoted` | a waitlisted booking promoted into a free place | README Feature 8 |

Four rules worth knowing before touching any of it:

1. **Order confirmation hangs off `OrderPaid`, never off checkout.** An open
   payment session is a customer looking at a payment page, not a sale.
2. **Status triggers fire on the transition, not the status.** Re-saving an
   already-shipped order sends nothing; a customer told twice that their order
   shipped has been given wrong information, not extra service.
3. **Delivery failure is non-fatal.** `NotificationDispatcher` never throws —
   each channel is tried independently and a failure is logged and stepped
   over. Feature 8's acceptance criteria require that a bounced text cannot
   fail an order whose money has already been taken.
4. **Every timeframe in the copy is quoted from `GOLD_COAST_TOKOTA.md`** — the
   48-hour processing window, 1–2 day domestic delivery, the 7-day return
   window. §22.3 forbids changing any of them, and an email is exactly where an
   invented one becomes a promise. A test asserts the confirmation still says
   48 hours.

Without `FISH_AFRICA_APP_ID`/`_SECRET` the SMS channel resolves to
`LogSmsChannel`, which writes the message to the log instead of sending it —
the same fallback shape `PaymentGatewayFactory` uses for `FakeGateway`.
`GET /admin/settings/notifications` reports `sms_channel: log_only` in that
state rather than implying texts are going out.

**Workshop reminders and return-resolution notices were deliberately not
built.** Neither document states either one, and §22 forbids inventing a policy
the brand document does not state.

---

## Orders (`GET /orders/{reference}`)

**Addressed by `reference`, never by the numeric id, and `/orders/1` is a 404.**
Guest checkout means this endpoint cannot sit behind auth, and an order carries
a name, an email and a home address — a sequential id would let anyone walk the
order table by counting. References are `GCT-` plus 12 characters from an
unambiguous 32-character alphabet, and the route is throttled on top.

`payment_reference` (the gateway's own id) is deliberately **not** in the
response. Line items carry snapshotted `name` and `variant_label` so a receipt
survives its product being renamed or hard-deleted; `slug` and `image` come
from the live product and go null when it's gone.

The confirmation page polls this while an order reads `pending`, because the
webhook can land after the customer is redirected back.

---

## Customer accounts

Sanctum SPA cookie sessions on the **`web`** guard — the mirror of the admin
block, which uses `admin`. The storefront must `GET /sanctum/csrf-cookie` first
and send `credentials: 'include'`; `composables/useAuth.ts` documents both steps
and has an `AUTH_ENABLED` flag to flip.

| Route | Body |
|---|---|
| `POST /register` | `{ name, email, password, password_confirmation, phone?, preferred_currency? }` → `201`, signed in |
| `POST /login` | `{ email, password, remember? }` → `200` |
| `POST /logout` | — |
| `GET /me` | — |
| `GET /orders` | The signed-in customer's own history, 20/page |

**Guarded with `auth:web`, never `auth:sanctum`.** `config/sanctum.php` lists
both `web` and `admin` in its guard array, so `auth:sanctum` would let an admin
session through a customer route — and `$request->user()` would then be an
`AdminUser` whose id could collide with a real customer's, handing back someone
else's orders. There is a test for this.

`GET /orders` scopes to the session and takes no customer parameter, because the
only safe answer to "whose orders?" is "the session's".

Login is throttled 5 attempts per **email + IP**, not per email — otherwise
anyone hammering a known address from elsewhere could lock a real customer out
of their own account.

**Registration does not adopt existing passwordless rows.** Nothing creates them
today (guest checkout leaves `customer_id` null rather than writing a Customer),
and if post-purchase account creation ever does, letting registration claim one
would hand anyone who knows a customer's email their full order history. Any
future claiming flow needs an emailed confirmation link.

## Password reset

| Method | Path | Notes |
|---|---|---|
| POST | `/forgot-password` | `{ email }` — throttled 6/min per IP |
| POST | `/reset-password` | `{ token, email, password, password_confirmation }` |

**`/forgot-password` always answers the same thing**, whether or not the
address has an account, and validates the email for *format only* — no
`exists:customers,email`. Either would turn it into an enumeration oracle: a
way to test which of a list of addresses shops here. The broker's own 60-second
per-email throttle sits underneath, so a real address does not get repeat
emails; the route's per-IP ceiling is what stops a script working a list. It
also sends mail to an address the caller chooses, which is the other reason it
is capped.

**`/reset-password` does distinguish failure** (422 on an expired, used or
forged token) — by then the customer is holding a link we sent them, and a dead
link has to say so or they will retype it. It reveals nothing new, since anyone
with the token already had the email.

A reset rotates `remember_token`, invalidating "remember me" cookies issued
before it — somebody resetting a password may be doing it *because* an old
session is not theirs — and fires `Illuminate\Auth\Events\PasswordReset`.

### The link points at the storefront

`Customer::sendPasswordResetNotification` is overridden. Laravel's built-in
notification builds its URL from `route('password.reset')`, a named web route a
headless API has no reason to define; the page that accepts the token belongs
to the Nuxt storefront. The link is:

```
{config('app.storefront_url')}/account/reset-password?token={token}&email={email}
```

**That page does not exist yet** — `frontend/pages/account/` has `login`,
`register`, `index`, `orders` and `settings` and no reset page. The backend is
complete and tested; the storefront needs the two screens (request a link, set a
new password) before a customer can use it. `app.storefront_url` reads
`STOREFRONT_URL`, defaulting to the first entry in `FRONTEND_URLS`.

The email goes through the Feature 8 dispatcher like every other customer
message, and is **email-only — its `sms` body is deliberately null.** A reset
link is a credential, and SMS is forwarded, screenshotted and read off lock
screens.

---

## DIY reference photos

| Method | Path | Notes |
|---|---|---|
| POST | `/booking-uploads` | multipart `file` — throttled 10/hour per IP |

Two steps rather than a multipart booking: upload here, then send the returned
`path` as `details.reference_image` when creating the booking. That keeps
`POST /bookings` plain JSON, and an abandoned form leaves a file rather than a
half-made booking.

```
POST /booking-uploads  (file)
  → 201 { data: { path, url, filename } }
POST /bookings         { …, details: { …, reference_image: <path> } }
```

**This is the only unauthenticated write-to-disk endpoint on the API.** Guest
bookings are supported by design, so it cannot sit behind a login, and
everything a session would normally provide has to be done here instead:

- 5MB cap, an allowlist checked against file *contents* (`mimes`), and a
  `dimensions` rule that forces the file through an image decoder — so a
  renamed script that satisfies the mime check still fails.
- Laravel generates the stored name, so a hostile original filename never
  reaches the filesystem and the URL is not guessable from the customer's name
  or the date. That unguessability matters: the file is on the `public` disk,
  so anyone holding the URL can open it. If reference photos are ever judged
  sensitive, the change is a private disk plus a signed admin-only route.
- `PruneOrphanedBookingUploads` runs daily and deletes unattached uploads older
  than 24 hours. Without it, an unauthenticated upload endpoint grows the disk
  without limit. Attached uploads are never pruned regardless of age.

`AdminBookingResource` exposes `reference_image_url`, and it is **null unless
`reference_image` holds a path this API issued.** Bookings taken before this
endpoint existed recorded the customer's *filename* there (the photo came over
WhatsApp), so turning that into a URL would give every historical booking a
broken image — and the prefix check is also what stops an arbitrary string in
that field being rendered as a link.

**The storefront still sends the filename.** `DiyOrderForm.vue` sets
`reference_image: referenceImage.value?.name`. It needs to POST the file here
first and send the returned `path` instead; validation stays permissive
(`nullable|string|max:255`) so the current form keeps working until it does.

---

## Feedback

`POST /feedback` takes `{ name, email, message, rating? }` and returns `201`
with no body — `FeedbackForm.vue` replaces itself with a thank-you and has
nothing to render. Unauthenticated by design, so it is throttled and `message`
is capped at 5,000 characters; an open text field with no ceiling is a way to
fill a disk. A `customer_id` in the body is ignored — it comes from the session
or not at all.

`GET /admin/feedback` is read-only for Admin and Staff. There is no update or
delete: feedback is a record of what someone said, and an admin panel that can
quietly edit it is worse than one that cannot. The resource emits
`submitted_at`, which the admin app normalises to the `submittedAt` its
`FeedbackEntry` type expects.

---

## Admin: roles and capabilities

**Routes name the capability they need, not the tier allowed to reach them.**
`EnsureAdminRole` and `EnsureStaffOrAdminRole` are gone; there is one
middleware, `capability:<name>` (several comma-separated means all are
required), checked against `App\Support\AdminCapability`.

The old pair could only ask "admin or not", and §18 of the brand document does
not divide that way: an Admin may change prices and issue refunds but **cannot
modify system-level settings or payment credentials**, while a Staff member may
adjust stock but not price it. Both of those sit on the same side of a
two-value check, so neither was enforceable.

Four tiers. §17/§18 name three — Super Admin, Admin, Staff — and `intern` is the
fourth the business asked for, already shipped in the dashboard as a time-boxed
read-only account. `admin_users.role` is now a plain string with
`access_expires_at`, `access_extensions`, `job_title`, `avatar` and
`last_active_at` alongside it.

| Tier | Holds |
|---|---|
| `super_admin` | Everything |
| `admin` | Everything except `settings.payments`, `settings.fx`, `team.manage` |
| `staff` | Operations: orders, returns, shipments, inventory, bookings, content drafts, media upload. No pricing, refunds, deletions or settings writes |
| `intern` | Read-only, plus drafting an inbox reply. Lapses on `access_expires_at` |

A **lapsed** account still authenticates and can still reach `GET /admin/me` and
`POST /admin/logout` — it has to, or the person cannot see why they are locked
out — but holds no capabilities at all until someone extends it.

`GET /admin/me` sends the server's `capabilities` array. `admin/utils/permissions.ts`
keeps its own copy for hiding buttons; the two must agree, and shipping the
server's answer means they can be compared rather than assumed equal.

Every 403 carries a written sentence (`message`), not a raw blob — README
Feature 9 requires it, and the copy matches the frontend's `denialMessage()`
so a refusal reads the same whichever side caught it.

---

## Admin: operations

Everything under `/admin/*` is Staff-and-above except where noted. Money in
admin responses is `{ amount, currency }`, not a bare integer — the admin app's
`Money` type makes the pair inseparable so a number can never be mistaken for a
price. This is the one place admin and storefront shapes deliberately differ.

### Orders

`AdminOrderResource` adds what the storefront's deliberately withholds:
`payment_reference` (the gateway id, which is what you reconcile against a
Paystack or Stripe dashboard), `customer_name` / `customer_email` /
`is_guest`, and `placed_at`.

Search (`?q=`) covers the reference, the customer record, **and the shipping
address** — guests have no Customer row, so without the address half the orders
would be unfindable. It uses `LOWER(...) LIKE`, not Postgres's `ILIKE`:
production is Postgres but the test suite runs on SQLite, and a search that
works in only one environment is worse than a slightly longer clause.

**`refunded` needs `orders.refund`** (Admin and above). The README names refunds
alongside pricing and site settings, and §18 keeps money away from the Staff
tier. It is enforced on the submitted *value* rather than the route, because
the same endpoint is legal for Staff right up until they ask for a refund — and
the 403 carries a sentence, not a raw blob.

**`delivered` stamps `delivered_at`**, once. §9 runs the returns window from the
day the order is *received*, so that is the moment the clock starts; a
correction back to `shipped` and forward again does not quietly extend a
customer's window.

### Returns and exchanges

§9 and §21 of the brand document, transcribed into `ReturnPolicy`: a seven-day
window from receipt, three accepted reasons (all of them the seller's error) and
a separate size-exchange path, four non-returnable categories, refunds in 7–14
business days on the original method, and shipping refundable only when the
error was Gold Coast Tokota's.

**There is no customer-facing endpoint.** §9 gives exactly one route in for a
return — "Return / Exchange Contact: WhatsApp" — so a request arrives as a
message and staff record it. A self-service portal is new scope, and §22.2 says
not to invent policy that is not written down.

Eligibility is assessed once, at the moment the request is recorded, and frozen:
it is a decision made against the policy as it stood that day, and a later
policy change must not silently rewrite a call someone already made. Staff can
override it — §9's "products damaged through misuse" is a judgement made on
seeing the pair, which no rule can reach from the order alone.

Two facts the schema gained for this: `orders.delivered_at` (the window runs
from receipt, and `status = 'delivered'` said *that* it arrived but not *when*)
and `products.is_returnable` (§9 excludes custom and personalised products
outright, and `product_type` is the listing facet while `tags` is free-text
merchandising copy — keying a refund off either would let a badge rename change
what customers are owed). Sale items are *not* flagged: "clearance or sale
items, unless defective" is conditional, so it is decided per request against
`compare_at_ghs`.

### The workshop programme

`workshop_types` is §15's table — six experiences, each with its own recurrence,
slot, duration and capacity ceiling — and every session now belongs to one.
Before this a session was a date, a time and a seat count with **no name**, so
the booking page could not tell a Saturday Sip & Paint from a Friday Be a
Shoemaker for a Day.

Capacity lives in both places deliberately: the type carries the published
ceiling ("up to 40 students"), the session carries what is actually offered on
the day, which may be lower. A session may never exceed its type's ceiling —
§22.15 asks for capacity to be enforced where the booking system supports it.

Three of the six run only by appointment and so never appear in the session
list; `requires_appointment` is the flag to branch on, not the days label.
`GET /workshop-types` exists publicly for exactly that reason — without it half
the published programme is invisible to customers.

The types are read-only over the API. Their days, times and capacities are
published commitments and §22.3 forbids changing them without instruction; what
changes day to day is a session scheduled against one.

### Bookings and workshop capacity

Promoting a waitlisted booking is the one status change that can oversell:
every other transition moves a seat the booking already holds. It re-checks
capacity under a row lock, for the same reason checkout does, and returns `409`
when the session is full.

Two guards on sessions, both returning `422` with the number of people affected:
capacity cannot be cut below the seats already taken, and a session with
bookings cannot be deleted. Both would otherwise leave someone turning up to a
seat that no longer exists.

### Dashboard metrics

Direct queries, no caching or pre-aggregation — the README requires live data on
every load, and says the upgrade is a later concern if volume demands it.
`generated_at` is in the response because "metrics show when they were read" is
an acceptance criterion.

**Revenue is split by currency and never summed.** Adding GHS and USD would need
a rate that would then disagree with every order's own locked
`fx_rate_applied`. Only `paid`/`processing`/`shipped`/`delivered` count.

`unread_messages` and `open_returns` are **`null`, not `0`**. There is no inbox
and no returns table — see "Admin screens with nothing behind them" below. A
confident zero would read as "no open returns" rather than "returns do not
exist", and the admin app's own rule is that invented numbers must look
invented.

## Admin screens with nothing behind them

The admin app calls 30 distinct endpoints. **Twenty-five now exist.** What
remains is the surface whose data model cannot be guessed from a screen:

| Path | Why it is still a question |
|---|---|
| `/admin/inbox/threads` · `/messages` · `/templates` | Could be WhatsApp thread history, a ticketing system, or email. Each produces a completely different schema |
| `/admin/activity` · `/admin/audit` | Buildable, but retention and what-gets-logged are policy questions with compliance weight |

The cost asymmetry is the whole argument for asking rather than guessing: a
wrong guess on customers costs an hour, a wrong guess on an inbox leaves a
production schema and screens built against it.

**Two came off this list when `GOLD_COAST_TOKOTA.md` arrived.** Returns and
workshop types were open because the README covers neither, so the data model
was unguessable. §9/§21 and §15 write both down in full — a seven-day window
with named reasons and exclusions, and six experiences with their own
recurrences and capacity ceilings — which turned them from questions into
transcription. Both are built and documented above.

**Built earlier from an obvious shape:** `/admin/customers`, `/admin/team`,
`/admin/shipments`, `/admin/dashboard/charts`, `/admin/media`,
`/admin/settings/diy-turnaround` and the `/admin/settings/*` panels.

**None of what remains is a bug.** Those screens fall back to fixtures and show
the demo-data chip. But they are unbudgeted work, and that should be a decision
rather than something discovered during a launch checklist.

---

## Deploying this

Four things the API needs in production that are easy to miss, all now in
`render.yaml` and `backend/Dockerfile`:

- **A cron service running `schedule:run` every minute.** Without it neither
  `RefreshFxRate` nor `ReleaseExpiredReservations` is ever dispatched, and
  expired checkout reservations hold stock permanently.
- **A queue worker.** `QUEUE_CONNECTION` is `database` and both scheduled jobs
  are dispatched to the queue, so a scheduler with no worker just fills a table.
- **`php artisan storage:link` on boot.** `public/storage` is gitignored;
  without the link every uploaded image 404s.
- **Env var names that match `config/services.php`.** The blueprint previously
  set `FX_RATE_API_KEY`, which nothing reads.
- **`STOREFRONT_URL` set to the storefront's public origin**, not the API's. It
  is where Paystack returns the customer after payment; wrong, and every paid
  customer lands on a 404 instead of their confirmation page.
- **The Paystack webhook registered** in the Paystack dashboard, pointing at
  `POST {API}/api/v1/webhooks/paystack`. Without it no order ever leaves
  `pending`, however many payments succeed.

> **Known issue:** the container still serves the API with
> `php artisan serve`, Laravel's single-threaded dev server. Fine for a demo,
> not for production traffic — see FOR_THE_TEAM.md decision 30.

---

## Known gaps

**Listing filters run client-side.** `frontend/pages/shop/index.vue` sends
`type`, `color`, `size`, `width`, `category`, `q`, `sale` and `sort` as query
params, but `ProductController::index` ignores all of them and the page filters
the response in `matchesFilters()`. That works today because the catalogue is
small. It breaks as soon as the catalogue exceeds one page: the API returns 12
products, and the filters only ever see those 12. Server-side filtering is the
fix, and it is not urgent yet — but it is a correctness bug waiting on
catalogue size, not a performance nicety.

---

## Open decisions

**Product reviews.** `ProductReviews.vue` is fully built — sort, star filter,
rating distribution, a fit meter — and no README feature covers reviews at all.
`rating` and `reviews` are therefore the only two `ApiProduct` fields the API
does not send. Before building a `product_reviews` table someone needs to decide
who writes reviews, whether they are moderated, and whether they are seeded.
Until then the product page hides both sections via `v-if`.

**Colourways: column or table?** `colors` is `jsonb` because nothing currently
hangs stock, images or a price off a colour — they are swatches. The moment a
colourway needs its own photos or its own inventory it has become a variant and
belongs in a table with `inventory_items` pointing at it.

**Seeded products have no collection.** `database/data/products.json` is the
client's real catalogue (28 styles, supplied 30 Sep 2026). The client calls each
style a "collection", which in this schema is a `Product`; they supplied no
grouping above that, so `collection` is `null` on every seeded product rather
than guessed. Categories are the client's own split — `slippers` and `shoes`.
