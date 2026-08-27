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
| All money is in **minor units** (pesewas / cents) as integers | No float arithmetic on money; `60000` is GH₵600.00 |
| **USD is always derived** from GHS × the cached `FxRate`, never stored | README Feature 2. The one exception is `orders.fx_rate_applied`, snapshotted at checkout so a charged amount can't move |
| A price and its was-price convert on the **same** rate | `price_usd` and `compare_at_usd` are computed from one `FxRate` read |
| `sizes` are **strings**, not integers | PHP casts numeric array keys to ints; the storefront's size facet compares against strings, so an int list matches nothing — silently |
| Stock-derived fields only appear when `inventoryItems` is loaded | `in_stock`, `merchandising_badge`, `sizes`, `size_availability` |
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
| `product_type` | string¦null | column | `ahenema` ¦ `slippers` ¦ `sandals` ¦ `closed-toe`. What the listing sidebar's "Category" facet filters on |
| `departments` | string[] | column | `mens` ¦ `womens` ¦ `kids`. What the header nav's `?category=` resolves to |
| `widths` | string[] | column | `s` ¦ `m` ¦ `l` |
| `tags` | string[] | column | Free text, e.g. "Custom Made", "Renewed Materials" |
| `is_pre_order` | bool | column | Renders a Pre-Order badge, blocks add-to-cart |
| `is_active` `is_featured` | bool | column | |
| `in_stock` | bool | inventory | Sellable (available − reserved) > 0 |
| `merchandising_badge` | string¦null | inventory | `out_of_stock` / `limited_stock` always computed live; `back_in_stock` is the one editorial value |
| `sizes` | string[] | inventory | The full range, **including** out-of-stock sizes — they render struck through |
| `size_availability` | `{size: int}` | inventory | Sellable count per size |
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
| GET | `/categories` `/collections` | |
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
| GET | `/workshop-sessions` · POST `/bookings` | |
| POST | `/admin/login` · `/admin/logout` · GET `/admin/me` | Sanctum cookie session, `admin` guard |
| GET | `/admin/inventory` | Admin **and** Staff. `?low_stock=true` `?product_id=` — 50/page |
| GET | `/admin/feedback` | Admin **and** Staff. Read-only, newest first — 50/page |
| GET | `/admin/dashboard/metrics` | Admin **and** Staff. Live queries, no caching |
| GET | `/admin/orders` · `/admin/orders/{reference}` | `?status=` `?q=` — 25/page |
| PATCH | `/admin/orders/{reference}` | Status. **`refunded` is Admin-only** |
| GET | `/admin/bookings` | `?status=` `?type=` `?workshop_session_id=` |
| PATCH | `/admin/bookings/{id}` | Status, incl. waitlist promotion |
| GET/POST | `/admin/workshop-sessions` | `?upcoming=true` on index |
| PUT/DELETE | `/admin/workshop-sessions/{id}` | Capacity editing, guarded — see below |
| GET/POST | `/admin/blog` · GET/PUT/DELETE `/admin/blog/{id}` | Includes drafts. Body sanitised server-side |
| GET | `/admin/pages` · `/admin/pages/{id}` | |
| PUT | `/admin/pages/{id}` | Body sanitised. **No create or delete** — see below |
| GET | `/admin/site-settings` | Staff may read |
| PUT | `/admin/site-settings` | **Admin only** |
| GET | `/admin/newsletter` · `/admin/newsletter/export` | Read-only. Export streams CSV |
| GET | `/admin/categories` | Categories and collections together |
| POST/PUT/DELETE | `/admin/products` | Admin role only |

### Specified, not yet built

| Method | Path | Consumer | Stage |
|---|---|---|---|
| POST | `/webhooks/paystack` · `/webhooks/stripe` | gateways | 3 |
| GET | `/admin/dashboard/metrics` | admin app | 7 |

`POST /checkout/session` has its request and response already written out in a
comment at `frontend/components/checkout/PaymentStep.vue:13`:

```
POST /checkout/session { items, currency, shipping_address, delivery_method }
  → GHS: a Paystack authorization URL to redirect to
  → USD: a Stripe PaymentIntent client secret to confirm
  → gateway redirects back to /order-confirmation/{id}
```

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
  "items": [{ "inventory_item_id": 12, "quantity": 1 }],
  "currency": "GHS",
  "delivery_method": "standard",
  "shipping_address": {
    "full_name": "…", "email": "…", "phone": "…",
    "line1": "…", "city": "…", "region": "…", "postcode": "…", "country": "GH"
  }
}
```

Returns `201` with `{ data: <Order>, payment: { gateway, reference, authorization_url, client_secret } }`.
GHS gets an `authorization_url` to redirect to; USD gets a `client_secret` to
confirm. `country` is **required** — it routes the courier, so a missing one is
a validation error rather than an unpriced order.

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
| `409` | Sold out, or the product went inactive. Body carries `inventory_item_id` and `available`. Not `422` — the request was fine, the world changed |
| `422` | Validation, including a missing `country` |
| `503` | USD checkout with no FX rate to lock |

### Until gateway credentials exist

`PaymentGatewayFactory` resolves `FakeGateway` in every environment, logging
that it did. It shapes its response like the real thing so the storefront's
branching can be built now, but it never moves money and never confirms
anything — an order it opens stays `pending` until a webhook says otherwise,
which is exactly how Paystack and Stripe behave. Nothing downstream can come to
depend on a fake payment having "succeeded".

Delivery is the same shape: `YangoService` and `DhlService` implement the real
interface with static rate tables. Ghana standard is ₵25, free over ₵1,500 —
matching what the product page already promises — and express is ₵50.
International is ₵350 / ₵600. Swapping in live quotes changes only the body of
`quote()`.

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

## Admin: operations

Everything under `/admin/*` is Admin **and** Staff except where noted. Money in
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

**`refunded` is Admin-only.** The README names refunds alongside pricing and
site settings in the two-tier rule. It is enforced on the submitted *value*
rather than the route, because the same endpoint is legal for Staff right up
until they ask for a refund — and the 403 carries a sentence, not a raw blob.

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

The admin app calls 30 distinct endpoints. **Sixteen now exist**, and every
endpoint README Feature 9 specifies is built. What remains is entirely
unspecified surface:

**Not in the README at all, and with no model behind them** — these are screens
the admin app grew beyond the spec, the same way `ProductReviews.vue` did:

| Path | What it would need |
|---|---|
| `/admin/returns` | A returns/RMA model and a workflow nobody has specified |
| `/admin/shipments` | Derivable from orders once Feature 5 books real deliveries |
| `/admin/media` | A media library — also what product `images` needs to be uploadable at all |
| `/admin/inbox/threads` · `/messages` · `/templates` | A whole customer-messaging system |
| `/admin/activity` · `/admin/audit` | An audit log |
| `/admin/team` | Backed by `AdminUser`, but user management is unspecified |
| `/admin/customers` | Backed by `Customer`, read-only would be cheap |
| `/admin/workshop-types` | No model; sessions have no type today |
| `/admin/dashboard/charts` | Year-over-year series plus traffic by source/device/location — the traffic half needs Feature 11 analytics |
| `/admin/settings/*` (6 paths) | Sub-resources of `SiteSetting` |

**None of this is a bug.** Those screens fall back to fixtures and show the
demo-data chip. But it is unbudgeted work, and it should be a decision rather
than something discovered during a launch checklist.

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

**Seeded collection assignments are a guess.** The design fixture never carried
a collection, so `database/data/design-products.json` assigns one per product as
a first pass. Categories are derived from `product_type` and are safe; the
collections need a human to confirm.
