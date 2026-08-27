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
| GET | `/categories` `/collections` | |
| GET | `/fx-rate` | |
| GET | `/pages/{slug}` · `/site-settings` | |
| GET | `/blog-posts` | Paginated, 9/page. `?limit=` (clamped 1–24) |
| GET | `/blog-posts/{slug}` | |
| POST | `/newsletter` | |
| GET | `/workshop-sessions` · POST `/bookings` | |
| POST | `/admin/login` · `/admin/logout` · GET `/admin/me` | Sanctum cookie session, `admin` guard |
| POST/PUT/DELETE | `/admin/products` | Admin role only |

### Specified, not yet built

| Method | Path | Consumer | Stage |
|---|---|---|---|
| GET | `/products/{slug}/stock` | `composables/useInventoryPolling.ts` | 2 |
| POST | `/checkout/session` | `components/checkout/PaymentStep.vue` | 3 |
| GET | `/orders/{id}` | `pages/order-confirmation/[id].vue` | 3 |
| POST | `/webhooks/paystack` · `/webhooks/stripe` | gateways | 3 |
| POST | `/feedback` | `components/forms/FeedbackForm.vue` | 6 |
| GET | `/admin/inventory` · `/admin/dashboard/metrics` | admin app | 2, 7 |

`POST /checkout/session` has its request and response already written out in a
comment at `frontend/components/checkout/PaymentStep.vue:13`:

```
POST /checkout/session { items, currency, shipping_address, delivery_method }
  → GHS: a Paystack authorization URL to redirect to
  → USD: a Stripe PaymentIntent client secret to confirm
  → gateway redirects back to /order-confirmation/{id}
```

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
