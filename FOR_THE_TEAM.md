# For the Team

A running log of what has changed in this codebase and what is still
outstanding, so anyone picking the project up can get current without reading
the whole diff.

**Read `README.md` for the spec and `CLAUDE.md` for the architectural rules.**
This file is the *status* layer on top of those two — it does not restate them.

- **Last updated:** 2 October 2026, later (product-page and cart-drawer recommendations come from the database)
- **Last commit on `main`:** `e8ab4f1` — *Merge pull request #17 from eddie-kay0462/dev*
- **Working tree:** clean. Everything through the 2 Oct recommendations change is committed on `feat/backend` and pushed. The 30 Sep catalogue change is committed (`0fbe207`). The 28 Aug – 8 Sep backend work is committed on
  `feat/backend` (`029b4b7`) and pushed, and `feat/backend` now contains
  everything on `main`. Merging `feat/backend` into `main` is a separate
  decision.

---

## Where the project stands

| Area | Status |
|---|---|
| Storefront — Home | **Built** from Figma |
| Storefront — Shop listing + Product detail + Cart drawer | **Built** from Figma; restyled 27 Aug to the approved Template B mockup — sizes on cards, thumbnail-rail gallery, WhatsApp order handoff |
| Storefront — News & Events (listing + article) | **Built** from Figma *(uncommitted)* |
| Storefront — About | **Built** from Figma *(uncommitted)* |
| Storefront — Sustainability | **Built** from Figma *(uncommitted)* |
| Storefront — About (now incl. Sustainability) | **Built** from Figma; the two routes merged 27 Aug, `/sustainability` 301s to `/about#sustainability` |
| Storefront — Account, Legal, Help, Company, Commerce | **Built** 26 Aug — 17 new page files covering 22 routes. **Auth is no longer inert on the API side:** register/login/logout/me and order history all exist on the `web` guard, so `AUTH_ENABLED` in `composables/useAuth.ts` can be flipped. `POST /feedback` exists too |
| Storefront — Checkout | **Works end to end (1 Oct).** `PaymentStep` posts the cart to `POST /checkout/session` and redirects to the gateway; the order confirmation page waits for payment and empties the cart once the order is paid. Locally the fake gateway completes the payment; **real payments need `PAYSTACK_SECRET_KEY`, and production refuses checkout with a 503 until it is set** |
| Storefront — Order confirmation | **Built, and no longer waiting.** `GET /orders/{reference}` exists and satisfies `ApiOrder` in full. Note the key: **reference, not numeric id** — `/orders/1` is a 404 by design |
| Storefront — Booking | **Built** 27 Aug — real session list from `GET /workshop-sessions`, capacity chips, waitlist, both forms matched to `StoreBookingRequest`. Was scaffold stubs |
| Backend API | **Further along than this file used to claim.** `routes/api.php` serves products, categories, collections, fx-rate, workshop-sessions, bookings, blog-posts, newsletter, pages and site-settings, plus a working `AdminAuthController` (`POST /v1/admin/login`, `/logout`, `GET /me`). No customer auth, no checkout, no orders endpoint |
| Product API contract | **Closed 27 Aug.** `ProductResource` now emits every field `ApiProduct` declares except `rating`/`reviews`. Documented in `docs/api-contract.md` — update it in the same commit as any response-shape change |
| Database | Migrations for admin_users, customers, pages, site_settings, categories, products, inventory_items, fx_rates, collections, workshop_sessions, bookings, blog_posts, newsletter_subscribers, orders, order_items |
| Admin dashboard | **Built, and signed in for real as of 1 Oct** — 36 routes, dark/light/system theming, four-tier roles. Sanctum login, session restore and sign-out work, so **every screen except the dashboard's activity feed now reads live data**. 25 of its 30 API paths exist; the 5 that remain (inbox ×3, activity, audit) are business-owner questions — see open decision 28 |
| Tests | **397 passing** (2 Oct). Feature test files — admin auth, admin products, admin operations/CMS/platform, blog, bookings, FX rate + service, inventory reservation (incl. the concurrent-hold cases), newsletter, products, and as of 28 Aug the Paystack webhook (signature, replay, partial payment), the returns policy and the workshop programme |

Against the README's "Implementation Order": **Phase 3a is done** (Feature 1
core pages, now including every route the chrome links to), Feature 6 (WhatsApp)
is in place, and Feature 2's catalogue endpoints exist. What is still missing on
the backend is the transactional half — checkout sessions and payments
(Feature 4), delivery quotes (Feature 5), notifications (Feature 8) and customer
auth — which is why several storefront pages are complete but deliberately
inert at their last step.

---

## Recent changes

### 2 October 2026 (latest) — recommendations come from the database

"Recommended Products" on the product page and "Before You Go" in the cart
drawer both read a hardcoded copy of the catalogue (`utils/designCatalogue.ts`).
A deactivated, repriced or sold-out style kept being recommended.

- **`GET /products/recommendations?for=…&limit=4`** ranks active products:
  - shares a department with a `for` product (women's styles for a women's
    style)
  - then same category
  - then in stock
  - then featured
  - name breaks ties, so the server render and the browser agree

  `for` products are excluded. Sold-out styles only fill empty slots, so the
  row isn't blank in production before stock is entered.
- **Product page:** fetched during the server render with the product
  (`for` = the product).
- **Cart drawer:** fetched when the drawer opens or the cart's products change
  (`for` = everything in the cart), so it never suggests what's already in the
  cart.
- Both fall back to the design catalogue only if the API can't be reached,
  the same as the product page itself.
- 7 new tests. **397 passing.** Verified in a browser: with Asantewaa
  deactivated it dropped out of both places, and Ohene took its slot.

### 2 October 2026 — colour is a stock axis (issue 39)

Each photo shows a different colourway of a style, but stock was tracked by
size alone. A customer couldn't say which colour they wanted, and the shop
couldn't know which pair to send: the first real test order read just "42".

- **Stock rows are colour × size.** `variant_attributes` is now
  `{size, colour}`. Migration `2026_10_02_090000` assigns every existing
  size-only row to the product's primary colour (`products.color`) and
  deletes nothing, so entered counts and past orders keep pointing at real
  rows. The seeder then adds the other colours across the size range: **0 in
  production**, 5 locally.
- **Assumption, reversible:** the sheets give one size range per style, so
  every colour is seeded in every size. A colour that isn't made in some size
  just stays at 0 in admin.
- **`colour_images`** (new column, also in `products.json`) maps each colour to
  its photos, derived from the `<n>-<colour>.webp` filenames. All 28 styles
  were checked: every colour has a photo and every photo's colour is listed.
  It lives in its own column because the colour filter substring-searches
  `colors`, and image paths there would make "tan" match
  `/products/asan`**`ta`**`ewaa/…`.
- **API:** `variant_availability` (`{colour: {size: n}}`) on the product and
  stock endpoints; `size_availability` is still the all-colours sum.
- **Checkout** takes `colour` per line, case-insensitive. It's required when
  a size comes in several colours, and refused if the style isn't made in
  that colour. Order lines read `"42 | Tan"`.
- **Storefront:**
  - The product page gallery shows only the chosen colour's photos (picked on
    the page, so the server render matches).
  - Sizes are struck through per colour.
  - A colourway with nothing left gets a struck-through swatch.
  - The card's quick-add checks the pictured colour's stock.
  - Checkout sends the colour from the cart line.
- **Admin:** the inventory list already shows every variant attribute, so
  rows read "size 42 · colour Tan" with no change; stock is entered per
  colour. 12 new tests. **390 passing.**

Verified in a browser on local Postgres: the gallery swapped to the Blue photo,
Blue 43 (zeroed) was struck through for Blue only, Green (zeroed) showed out
of stock, and the order line read "Domfo Collection 42 | Blue". Stock came off
the Blue 42 row, not Tan.

**Locally run `php artisan migrate && php artisan db:seed --class=ProductSeeder`**
to pick this up. To see production-like behaviour locally, run all three of
`php artisan serve`, `php artisan queue:work` (stock finalising, emails) and
`php artisan schedule:work` (releases abandoned 15-minute holds).

**Still open:**
- **Domfo's women's version (issue 40)** — per-variant pricing is now
  possible, but needs the client's size range and a decision.
- **The admin product editor can't add a new colourway yet.** New colours need
  a seed-data change for now.

### 1 October 2026 — checkout works, and so do the storefront's forms

**A customer can now buy something.** Cart → details → shipping → payment →
gateway → confirmation runs end to end. Verified in a real browser against
Postgres: the order was created, stock reserved, then finalised once paid, and
the confirmation email went out.

**Every storefront form had been failing in real browsers.** Newsletter,
feedback, both booking forms, and now checkout all used bare `$fetch`. The
storefront's origin is a Sanctum stateful domain, so a browser POST from it
needs the CSRF handshake, and without it every one was a **419**. The API tests
never caught it because test requests carry no `Origin` header.
**`frontend/composables/useApi.ts`** does the handshake (`GET /sanctum/csrf-cookie`,
then `X-XSRF-TOKEN`) with credentials included. All five forms use it now.
**Use it for any new storefront write**; customer sign-in will need it too.

**Storefront:**

- **`PaymentStep.placeOrder()` is real.** It posts the cart and redirects to
  `authorization_url` for both currencies. Errors name the cart line at fault:
  sold out (409), size not made (422), payment unavailable (503), network. The
  Stripe wording is gone.
- **Order confirmation tells the truth about payment.** It used to say "Thank
  you — your order is in · We've emailed your confirmation" for any order,
  paid or not. Now:
  - while polling: "Confirming your payment…"
  - if polling ends unpaid: "Your payment didn't go through"
  - only once paid: the thank-you
- **The cart empties only once the order reads paid**, so someone who backs out
  of Paystack keeps their basket.
- The stale "endpoint hasn't been built" copy is gone from both pages.

**Backend:**

- **Checkout lines can be `{slug, size}`** as well as `{inventory_item_id}`.
  The cart had never held real stock-row ids; it keys lines `slug:size:colour`.
  Resolving on the server also keeps carts already in customers' cookies
  working, and extends to `{slug, size, colour}` when colour variants arrive
  (issue 39). A 409 now carries `line`, the cart index at fault.
- **Fake gateway completes locally.** Its URL, `/fake-gateway/{ref}`, had no
  route, so local checkout ended on a 404. `Dev\FakeGatewayController` marks
  the order paid, fires `OrderPaid` and redirects to the confirmation page;
  `?outcome=cancel` doesn't pay. **Never registered in production.**
- **Production without a Paystack key refuses checkout (503).** Before, it
  handed out the fake gateway's URL, so a customer would type everything in
  and land on a 404. Now nothing is reserved and nothing is created.
- 7 new tests. **382 passing.**

**Locally, `OrderPaid`'s work is queued**: stock finalising and the email
wait in `jobs` until you run `php artisan queue:work`. Production runs a
worker (`render.yaml`).

### 1 October 2026 — the admin dashboard signs in, and reads the real API

**The headline: the admin app had never once shown live data.** The API side
of admin login has existed since August, but the admin app's `login()` still
threw "not built yet", the app booted on a fake demo session, and
`adminFetch` fell back to fixtures on *any* error, including the 401 every
unauthenticated call got. So all 25 built admin endpoints were bypassed and
every screen showed invented numbers. Nobody could enter stock either (issue
43), and since production seeds every size at 0, nothing could be sold.

**Admin app (`admin/`):**

- **Real sign-in.** `useAuth().login()` → `GET /sanctum/csrf-cookie` →
  `POST /admin/login`; `restoreSession()` → `GET /admin/me` on boot;
  `logout()` → `POST /admin/logout`. New `middleware/auth.global.ts` sends
  signed-out visits to `/login?redirect=…`, with same-app paths only, so
  `?redirect=//evil.example` goes to `/`. The demo session survives only in
  `NUXT_PUBLIC_ADMIN_DATA=fixtures` mode, for offline review.
- **`adminFetch` sends `X-XSRF-TOKEN`** on writes, re-read from the cookie
  each time because Laravel rotates it at login. Without it every write was a
  419.
- **401/419 now redirects to `/login`** instead of quietly showing fixtures.
- **Writes never fall back to fixtures.** Before, a failed PATCH in `auto`
  mode swallowed the API's 422 and could look as if it had saved.
- **Inventory "Adjust" works** (issue 43): `components/inventory/AdjustModal.vue`.
  The page now asks for `per_page=500`, because a fixed 50 rows showed under a
  third of the real catalogue's ~170 size rows.
- **Three crashes that only live data could expose:**
  - `/` and `/analytics` crashed on the traffic charts. The API sends those
    as `null` on purpose (no analytics yet, Feature 11); they now render
    "Not measured yet".
  - `/blog` crashed because admin blog endpoints returned the storefront
    shape. See below.
  - `MetricCard`/`DropdownItem` used `resolveComponent('NuxtLink')` in the
    template, which renders an inert `<nuxtlink>`, so dashboard tiles looked
    clickable and went nowhere.

**Backend:**

- **`AdminBlogPostResource`**, the same move products got on 8 Sep. It adds
  `author_name`, `is_published`, `updated_at`, and three new nullable columns
  the post editor already has fields for: `excerpt`, `cover_image_alt`,
  `meta_description` (migration `2026_10_01_090000`).
- **`GET /admin/inventory?per_page=`**, default 50, max 500.
- **`GET /categories` lists only categories with an active product.** Seeding
  never deletes, so any database seeded before 30 Sep, production included,
  still lists the demo-era Sandals and Ahenema categories, which link to
  empty pages.
- **`POST /admin/login` honours `remember`.** The "Keep me signed in" box was
  being ignored.
- 7 new tests. **375 passing.**

**Verified in a real browser**, not just the test suite: a Playwright run
against `php artisan serve` + Postgres + `nuxt dev` covered:

- signed-out redirect
- bad password showing Laravel's message
- login landing on the `redirect` target
- all 17 sidebar screens loading live data with no page errors
- a stock adjustment saving
- session surviving a reload
- sign-out re-locking the app

The dashboard keeps its "Demo data" chip, correctly: `/admin/activity` is
still one of the five undecided endpoints.

**Local databases are probably behind.** Mine had 8 pending migrations and was
still serving the six demo products plus seven faker ones. Run
`php artisan migrate && php artisan db:seed`. The stale products are not
deleted by the seeder; deactivate them, or `migrate:fresh --seed` if you have
nothing to keep.

⚠️ **The post editor's Publish button still has no handler**, so blog posts
can be listed but not yet written from admin. The API side is complete.

### 30 September 2026 — the real catalogue replaces the six demo products

The client sent two Drive folders, "Slippers" (26 styles) and "Shoe" (2). Each
style has its photographs and a sheet giving price, size range, materials and
gender. Those 28 are now the catalogue; the six products the storefront was
mocked up with are gone from both the API seed and the storefront fallback.

- **`backend/database/data/products.json`** (renamed from `design-products.json`)
  holds the 28. Names, prices, sizes, materials and departments are transcribed
  from the client's sheets. Prices are the sheet's cedi figure × 100 — 500 on
  the sheet is `50000` pesewas.
- **63 photographs in `frontend/public/products/<slug>/`**, resized from
  3024×4032 iPhone JPEGs (~1.5 MB each) to 1200×1600 WebP — 2.3 MB for the lot.
  Committed to the storefront rather than uploaded through the media library
  because uploads sit on Render's container-local disk and do not survive a
  redeploy (issue D3). Filenames are `<n>-<colour>.webp`.
- **New `products.materials` column** (jsonb list), emitted by `ProductResource`
  and added to `ApiProduct`. **Nothing renders it yet** — that is a storefront
  job. It is not in `tags` because tags are card badges.
- **`ProductSeeder` no longer overwrites stock.** It used to reset every size to
  the fixture's number on each run; it now creates missing sizes and leaves
  existing quantities alone.
- **`DatabaseSeeder` no longer pads the catalogue with faker products** or
  creates the Sikapa / Obrempong / Slides collections and the Sandals / Ahenema
  categories. Categories are now `slippers` and `shoes`, the client's own split.
- **`frontend/utils/designCatalogue.ts` is the same 28**, generated from the
  same data. The cart drawer and the detail page's related row read that file
  directly, so leaving the demo six there would have recommended products that
  do not exist. `SIZE_GROUPS` gained 36 and 37 — the women's styles start at 36.
- **`ProductFactory` draws its images from the real photographs.**
- **`PATCH /admin/inventory/{id}` — stock can now actually be set.** The brand
  said counts will be entered from admin, and there was no endpoint to do it:
  `inventory.adjust` existed as a capability and as an "Adjust" button, with
  nothing behind either. Takes an absolute `quantity_available` and/or
  `low_stock_threshold`; refuses a count below what pending checkouts hold;
  never touches `quantity_reserved`. Staff and up. **The admin "Adjust" button
  in `admin/pages/inventory.vue` still has no click handler** — issue 43.
- 10 new tests (`ProductSeederTest`, plus five on the adjust endpoint), including one asserting every seeded image
  path exists under `frontend/public`. **368 passing.**

**An existing database keeps the six demo rows** — the seeder upserts by slug
and deletes nothing. Locally, `php artisan migrate:fresh --seed`. Anywhere with
real orders, deactivate them from admin instead.

**Confirmed by the client the same day:** prices are in cedis; "Bona", "Macro"
and the rest are materials and are stored exactly as written; stock counts will
be set from admin; basic colour names inferred from the photos are fine.

**Judgment calls, all reversible (issues 38–43):**

- **Stock is entered by the brand from admin, not seeded.** The sheets give no
  quantities. Production seeds every size at 0; local and test databases get 5
  per size (`DEV_STOCK_PER_SIZE`) purely so checkout can be exercised.
- **No descriptions, no was-prices, no cost breakdown.** None were supplied and
  none were written. The detail page hides those sections.
- **Colour names are basic colours read off the photographs** — Black, Brown,
  Tan, Blue, Navy, Green, Red, White, Purple — which the client agreed to on
  30 Sep. Two-tone pairs take their dominant colour. Each photo is a different colourway, not a different angle — so the card's
  hover cross-fade and the gallery currently move between colours.
- **Domfo is seeded as a men's product at GHS 500.** The sheet says unisex,
  "500 for male / 300 female", sizes 40–45. One product carries one price, and
  no women's size range was given, so the women's version is not seeded.
- **Opanyin has two photos, not three.** `IMG_4416` is left out: its clasp looks
  like another brand's interlocking-letter logo.
- **The five `is_featured` products are an arbitrary spread** (Abrantie, Domfo,
  Obaapa, Odeneho, Osram) so the home page has real tiles.
- **Krakye** is spelt as the folder has it; its sheet says "Kyrakye".

### 8 September 2026 — password reset, and DIY photos stop travelling by WhatsApp

Two smaller gaps closed, both of which had a built frontend waiting on them.

**Customer password reset.** `POST /forgot-password` and `POST /reset-password`
on the `web` guard. The groundwork was already done — `passwords.customers` was
wired to `password_reset_tokens` and `Customer` was a proper `Authenticatable`
— so this is the flow, not the plumbing.

- **`/forgot-password` answers identically whether or not the address has an
  account**, and validates format only. `exists:customers,email` would have
  been the obvious rule and would have leaked the same thing through a 422:
  either turns the endpoint into a way to test which of a list of addresses
  shops here.
- Throttled 6/min per IP on top of the broker's own per-email throttle. The
  endpoint sends mail to an address the *caller* chooses, so an unthrottled one
  is a way to use this API to spam a third party.
- A reset rotates `remember_token`, so "remember me" cookies issued before it
  stop working — somebody resetting a password may be doing it *because* an old
  session is not theirs.
- **The link points at the storefront, not the API.** Laravel's built-in
  notification builds its URL from `route('password.reset')`, a named web route
  a headless API has no reason to define, so
  `Customer::sendPasswordResetNotification` is overridden to assemble
  `{storefront}/account/reset-password?token=…&email=…` and send it through the
  Feature 8 dispatcher. Email only — its SMS body is deliberately null, because
  a reset link is a credential and SMS is forwarded, screenshotted and read off
  lock screens.
- New config: `app.storefront_url`, from `STOREFRONT_URL`, defaulting to the
  first entry in `FRONTEND_URLS`.

⚠️ **The storefront has no reset pages yet.** `frontend/pages/account/` has
login, register, index, orders and settings. The backend is complete and
tested; two screens (request a link, set a new password) are needed before a
customer can actually use it. Note also that *profile editing* — listed
alongside password reset in the older note about customer auth — is still
outstanding.

**DIY reference photos.** `POST /booking-uploads`, then send the returned
`path` as `details.reference_image`. Until now the form recorded the
customer's *filename* and asked them to send the photo over WhatsApp — which
worked, but put the one thing the workshop needs to make the sandal on a
different channel from the order it belongs to.

**This is the only unauthenticated write-to-disk endpoint on the API** — guest
bookings are supported, so it cannot sit behind a login — and it is treated
accordingly: 5MB cap, an allowlist checked against file contents, a
`dimensions` rule that forces every upload through an image decoder (so a
renamed script that satisfies the mime check still fails), a
Laravel-generated storage name so a hostile filename never reaches the
filesystem, and 10/hour per IP.

`PruneOrphanedBookingUploads` runs daily and deletes unattached uploads older
than 24 hours. **Without it an unauthenticated upload endpoint grows the disk
without limit**, and every customer who picks a photo then changes their mind
leaves a file behind for good. Attached uploads are never pruned regardless of
age — the workshop may not make the sandal for weeks.

`reference_image_url` on `AdminBookingResource` is null unless the field holds
a path this API issued: historical bookings carry a filename there, and turning
that into a URL would give every one of them a broken image. The same prefix
check stops an arbitrary string being rendered as a link.

⚠️ **`DiyOrderForm.vue` still sends the filename**, so nothing changes for
customers until it POSTs the file first and sends the returned path. Validation
stays permissive so the current form keeps working in the meantime.

⚠️ **Uploads land on container-local disk**, which on Render does not survive a
redeploy — so a reference photo will vanish on the next deploy. That is the
pre-existing storage problem the audit lists as D3, not something this change
introduced, but this endpoint is the second feature now depending on it.

Backend tests: **358 passing**, up from 327. Both new suites were run against
Postgres as well as SQLite.

### 8 September 2026 — `/shop`'s filters were quietly wrong past 12 products

`GET /products` understood `category_id` and `featured` and nothing else, so
`frontend/pages/shop/index.vue` filtered **client-side over a single page of
12**. Every facet was therefore correct only while the catalogue fitted on one
page, and wrong with no error to notice after that. The page's own comment
said "the API is expected to filter server-side"; now it does.

`ProductFilter` implements the query string the storefront already sends —
`?type=&color=&size=&width=&category=&q=&sale=&sort=` — unchanged, because the
page was written against it. Within one facet the match is OR, across facets
AND, which is what the sidebar's checkboxes mean.

**Four decisions inside it:**

- **`sort=top-rated` is not honoured, and cannot be.** There is no ratings data
  anywhere in the system — reviews are unplanned scope with a built UI and no
  model (open decision 24). Inventing a `rating` column to satisfy a sort would
  be building the whole reviews feature by the back door, so an unrecognised
  sort falls through to the default instead. A shopper clicking "Top Rated"
  gets products, not a 422 — but the API does not pretend to have sorted.
- **`best-selling` sums `quantity`, not order lines.** One order for nine pairs
  is nine sales. It counts only orders where money settled and stayed, so a
  product everybody sent back cannot lead the best-sellers.
- **Colour is a substring match against colourway names**, mirroring the
  storefront exactly — a facet value of `tan` has to find "Tan Leather", which
  JSON containment cannot express.
- **Search covers the same fields as the storefront's own local predicate.**
  The page falls back to a bundled catalogue when the API is unreachable, and a
  search that returns different things depending on whether the API answered is
  a miserable bug to chase.

**The default sort is now featured-first, then newest.** The storefront labels
the unsorted view "Featured", so that is what the word had to mean.

Backend tests: **327 passing**, up from 302. 25 new, several of which
deliberately create more than one page of products — the old bug was invisible
on a small fixture, so a test that cannot see it is not a test.

#### Both database engines were actually exercised this time

Every clause was checked to compile on Postgres *and* SQLite rather than
assumed: `whereJsonContains` becomes `@>` on one and a `json_each` subquery on
the other; `LOWER(CAST(col AS text)) LIKE ?` works on both; `ILIKE` is
deliberately absent. **The full suite was run against a scratch Postgres
database as well as the SQLite default** — which is the remedy issue 29 asks
for, and it immediately found two things.

One was mine, caught before it shipped: `best-selling` first used `withSum`
plus an ordered alias, and a product that has never sold aggregates to NULL —
**Postgres sorts NULLs first under `DESC`**, so the best-sellers list would
have opened with everything that had never sold. It is now a correlated
subquery with `COALESCE(..., 0)`, ordered on directly, because Postgres also
refuses an output alias inside an ORDER BY expression.

The other two are **pre-existing test-harness failures on Postgres, not
production bugs** — see the new issue 36 below.

### 8 September 2026 — Feature 8: customers finally hear back

**Until today a customer paid and heard nothing.** There was no `app/Mail/` and
no `app/Notifications/` at all — the `OrderPaid` event existed with one
listener on it, and the second half of what that event was created for had
never been written.

Five triggers now dispatch, four of them the ones README Feature 8 names and
one from the brand document:

| Trigger | Fires on | Source |
|---|---|---|
| `order_placed` | `OrderPaid`, from the Paystack webhook | README Feature 8 |
| `order_shipped` | admin order status → `shipped` | §"Domestic Shipping" |
| `booking_submitted` | `POST /bookings` | README Feature 8 |
| `booking_confirmed` | admin booking status → `confirmed` | README Feature 8 |
| `waitlist_promoted` | a waitlisted booking promoted into a free place | README Feature 8 |

**Shape.** `NotificationChannel` is the same contract shape as `PaymentGateway`
and `DeliveryProvider`, for the same reason: the concrete SMS implementation
needs a credential nobody has, so the interface is what lets the rest be built
and tested now. `MailChannel` always runs; SMS resolves to
`FishAfricaSmsService` when credentials exist and `LogSmsChannel` when they do
not — the FakeGateway pattern, so the feature is exercised end to end either
way and the log says which one ran. Copy for all five messages lives in one
class, `TransactionalMessages`, so a promise cannot end up worded two ways.

**Four decisions worth knowing:**

- **Order confirmation hangs off `OrderPaid`, never off checkout.** An open
  payment session is a customer looking at a payment page, not a sale.
- **Status triggers fire on the transition, not the status.** Re-saving an
  already-shipped order sends nothing. Two tests cover exactly this.
- **`NotificationDispatcher` never throws.** Feature 8's acceptance criteria
  make notification failure non-fatal, so each channel is tried independently
  and a failure is logged and stepped over. A bounced text must not fail an
  order whose money has been taken and whose stock has been decremented.
- **Every timeframe in the copy is quoted from the brand document** — 48-hour
  processing, 1–2 day domestic delivery, the 7-day return window (read from
  `ReturnPolicy::WINDOW_DAYS`, not retyped). §22.3 forbids changing any of
  them, and an email is exactly where an invented one becomes a promise to a
  customer. A test fails if the confirmation stops saying 48 hours.

**Deliberately not built: workshop reminders and return-resolution notices.**
I had listed both in `BACKEND_REMAINING.md`, then dropped them — neither
document states either one, and §22 says not to invent a policy the brand
document does not state. Scheduling a reminder means choosing how far ahead it
goes out, which is a business decision, not a technical default.

**Phone numbers are normalised to E.164.** Ghanaian numbers are written locally
as `024 123 4567`, and that leading zero has to become +233 or every domestic
text silently goes nowhere — the kind of failure nobody notices until a
customer complains. Eight cases are covered.

**Still unverified:** `FishAfricaSmsService`'s request shape is written from
the README's description of the provider and has never run against the live
API. Expect to correct the token exchange and the send payload when keys land;
that is why they are in one small class behind the interface. The README also
asks for a sandbox test first — Ghana network delivery rates are explicitly
unverified.

Backend tests: **302 passing**, up from 280. One of them is end-to-end and
unfaked: a signed Paystack webhook goes in and a confirmation email addressed
to the customer comes out, which is the only test that would catch the listener
being unregistered. Another renders all five Blade templates for real, since
`Mail::fake()` does not and a broken template would otherwise pass.

### 8 September 2026 — the admin catalogue can finally read itself

**`GET /admin/products` and `GET /admin/products/{id}` did not exist.**
`routes/api.php` registered `POST`, `PUT` and `DELETE` for admin products and
no reads at all, so three shipped admin screens — `products/index.vue`,
`products/[id].vue` and `settings/seo.vue` — were pinned to fixtures with no
way off them. The public `GET /products` was never a substitute: it is scoped
`active()` and pages at 12, so a draft product was invisible to the screen
whose job is to manage it.

Both endpoints now exist on `products.view`, which every tier down to Intern
holds — reading the catalogue is not the same as repricing it. Reads are
deliberately unscoped, and support `?active=` (tri-state: absent means
everything, which is what the All tab wants), `?q=` over name and SKU,
`?category_id=` and `?per_page=` (default 100, capped 200).

**One latent bug fixed on the way past.** The write endpoints returned the
storefront's `ProductResource`, whose `base_price_ghs` is a bare integer — but
the admin app types product prices as `Money` (`{ amount, currency }`), so
every successful save was handing the client a shape it could not read. All
five endpoints now return the new `AdminProductResource`. Two tests moved to
the new shape with it; that is a deliberate contract change on endpoints whose
only consumer is the admin app.

The rolled-up `low_stock` flag tests each variant against its *own* threshold
rather than comparing summed totals — a product with 60 units of size 42 and
none of size 39 needs a restock, and the summed comparison would say otherwise.

Backend tests: **280 passing**, up from 272.

Also added at the repo root: **`BACKEND_REMAINING.md`**, a full audit of what
is left on the backend to make the app functional — 23 items in four buckets
(buildable now / blocked on a credential / blocked on a decision / production
readiness), with a suggested order. It is a point-in-time audit, not a second
status log; this file stays the running one.

### 28 August 2026 — `GOLD_COAST_TOKOTA.md` answers six open questions

The brand document landed at the repo root, and it is the source of truth for
business rules — §22 says so explicitly: do not invent a policy it does not
state, and do not change one it does. **Six things that had been sitting as open
decisions turned out to be written down in it.** `CLAUDE.md` now names it, and
where it and the README disagree it wins.

Backend tests: **272 passing**, up from 235. Migrations verified against a
scratch Postgres database as well as the SQLite suite, because two of them do
things SQLite would not have caught.

#### The role model finally matches what the dashboard presents (closes #12)

This was the oldest of the three-way disagreements: README says two tiers,
`admin_users.role` said two, the admin app implements four. §17 names the real
roster against tiers and §18 defines them, and §22.14 makes the Super Admin /
Admin / Staff model binding.

**The two middlewares are gone.** `EnsureAdminRole` and
`EnsureStaffOrAdminRole` could only ask "admin or not", and §18 does not divide
that way — an Admin may change prices and issue refunds but *"cannot modify
system-level settings [or] payment credentials"*, while Staff may adjust stock
but not price it. Both of those sit on the same side of a two-value check, so
**neither was enforceable**. There is now one middleware, `capability:<name>`,
and every admin route names what it needs.

- `App\Support\AdminCapability` is the matrix, and it is the **server-side twin
  of `admin/utils/permissions.ts`** — the frontend had already derived the same
  table from the same paragraph. Change both or neither; a capability in one and
  not the other is either a button that 403s or an action nobody can reach.
- `admin_users.role` is a plain string now, with `access_expires_at`,
  `access_extensions`, `job_title`, `avatar` and `last_active_at` beside it.
  `job_title` because §17 gives one for every person; the rest because the
  dashboard's Roles & access screen was built against them.
- **`intern` is kept**, though the document does not mention it. It is the
  fourth tier the business asked for and it already ships in the dashboard; the
  document does not contradict it. A lapsed intern still authenticates — it has
  to, or nobody can see why they are locked out — but holds no capabilities.
- **Team management moved from Admin to Super Admin.** So did the payments
  settings panel. Both follow directly from §18's sentence, and both changed a
  test's expectation rather than adding one.
- The seeded account is `super_admin`, not `admin`. An `admin` seed would leave
  a fresh database with nobody able to create the first real account.
- **The five people §17 names are not seeded.** The document gives their roles
  and job titles but no email addresses, and inventing credentials for real
  colleagues is not a seeder's job. **Someone needs to supply five email
  addresses** before handover, or the roster stays theoretical.

#### Paystack is the only gateway now — and Kirk has a small change to make

§13 names one payment provider, settling in Ghana Cedis, accepting Visa,
Mastercard, Verve, MTN/Telecel/AirtelTigo mobile money and bank transfer. §22.12
repeats it in a line. **Stripe is not mentioned anywhere in the document**, and
a dollar card payment is a card payment.

`PaymentGatewayFactory` therefore routes both currencies to Paystack; the
GHS/USD split is gone.

- **`PaystackService` is written and untested against the live API**, because
  `PAYSTACK_SECRET_KEY` is still empty. Everything but the key exists —
  transaction initialisation, verification, signature checking. Getting a test
  key is still an hour of somebody's time and it is still the critical path.
- **`POST /webhooks/paystack` exists**, with `processed_webhook_events` behind
  it. A replayed delivery creates no second payment and no double decrement, and
  there is a test that proves it rather than a comment claiming it. Amount and
  currency are checked against the order — a partial payment is not a completed
  sale.
- `OrderPaid` fires from the webhook, never from checkout: an open payment
  session is a customer looking at a payment page, not a sale. Its queued
  listener finalises the stock hold.
- **Kirk:** a USD checkout no longer returns a `client_secret`; it returns an
  `authorization_url` exactly as a cedi checkout does. The currency branch in
  `PaymentStep.vue` has nothing on the other side of it now. Redirect whenever
  `authorization_url` is present — correct today, and still correct if a second
  gateway is ever added back.
- **Flag for the business owner:** the README specified Stripe for USD and the
  brand document does not. If dollar settlement through Stripe is actually
  wanted, this is a factory change and the config binding is still there — but
  it needs saying, because right now the document and the README disagree and
  the document wins.

#### Returns and workshop types: two of the "unguessable" admin endpoints (narrows #28)

Both were open decisions purely because the README covers neither, so their data
model could not be guessed from a screen. §9/§21 and §15 write both out in full.
That turned them from questions into transcription.

**Returns** (`ReturnPolicy`) — seven days from *receipt*, three accepted reasons
(all of them the seller's error), four non-returnable categories, refunds in
7–14 business days on the original method, shipping refundable only when the
error was ours.

- **No customer-facing endpoint.** §9 gives one route in for a return —
  "Return / Exchange Contact: WhatsApp" — so a request arrives as a message and
  staff record it. A self-service portal is new scope.
- Two schema facts it needed: `orders.delivered_at`, because the window runs
  from receipt and `status = 'delivered'` said *that* it arrived but not *when*;
  and `products.is_returnable`, because §9 excludes custom and personalised
  products outright and the alternatives were `product_type` (the listing facet)
  and `tags` (free-text merchandising copy) — keying a refund decision off a
  badge name would let a rename change what customers are owed.
- Eligibility is assessed once and frozen. It is a decision made against the
  policy as it stood that day, and staff can override it: §9's "damaged through
  misuse" is a judgement made on seeing the pair.
- The one interpretation, flagged: §9 excludes sale items "unless defective",
  and separately lists three accepted reasons that are *all* faults. Read as
  covering all three. A size exchange on a clearance item is not covered, which
  is the case the wording exists for.

**Workshop types** — §15's six experiences, each with its own recurrence, slot,
duration and capacity ceiling.

- **A session had no name until now.** It was a date, a time and a seat count,
  so the storefront's booking page could not tell a Saturday Sip & Paint from a
  Friday Be a Shoemaker for a Day, and the admin Workshops screen had nothing to
  group under. Every session belongs to a type, and a session may not exceed its
  type's published ceiling (§22.15).
- **`GET /workshop-types` is public as well as admin.** Three of the six run
  only by appointment and so never appear in the session list — without it, half
  the published programme is invisible to customers.
- Read-only over the API: the days, times and capacities are published
  commitments and §22.3 forbids changing them without instruction. What changes
  day to day is a session scheduled against a type.

#### Four published figures were wrong, and one contradicted itself

Small, and the kind of thing nobody finds until a customer quotes it back.

- **The admin commerce panel said the returns window was 30 days.** §9 says
  seven. That figure appears nowhere in the document; an admin reading the panel
  would have promised four times the real window.
- **The delivery panel's ETA bands were guesses**, and two understated §8's
  published table — West Africa read "5-9 working days" against a published
  5–10, and the domestic label read "2-4" against a published 1–2. All of them
  now come from `DeliveryEstimates`, which is §8 transcribed, and the order
  response carries the band for its own destination plus the 48-hour processing
  time — so the confirmation page, the policy page and admin cannot quote three
  different numbers.
- **`diy_turnaround_estimate` was seeded as "2-3 weeks"**, against §16's DIY
  sandal kit row of 1–2 business days. The storefront's DIY form quotes that
  string directly. The five per-type tiers were already right; the single string
  contradicted the table beside it.
- §12, §14 and §24 gave four brand facts nothing held — the Haatso address, the
  Mon–Sat 9–5 trading hours, the default greeting and the tagline. All four are
  `site_settings` columns now, so the provisional phone number §12 flags can be
  corrected without a deploy.


Newest first. Everything from 18 August onward, and all of it is committed and
pushed to `dev`.

### 26 September 2026 — `main` merged into `feat/backend`

Brings Kirk's WhatsApp channel work (`12ef9fb`, PR #17) onto the backend branch.
The only textual conflict was this file. **The dangerous overlap merged without a
conflict:** both branches added columns to `site_settings`. Kirk's
`2026_08_27_000200` migration adds `whatsapp_greeting` and `business_hours`.
The backend's `2026_08_28_090400` added `business_hours` again, plus
`greeting_message`, which holds the same §14 greeting under another name. On a
fresh database `migrate` would have stopped on "column already exists".

Resolved in favour of Kirk's names, because the storefront and the admin
WhatsApp screen already read them (`whatsappGreeting`, `businessHours`).
`2026_08_28_090400` now adds only `address` and `tagline`. `greeting_message`
no longer exists, and `UpdateSiteSettingRequest` validates `whatsapp_greeting`
(max 2000). Before this, the admin screen's greeting field was silently dropped
on save. The seeded `business_hours` is Kirk's short form
(`Mon–Sat · 9am–5pm GMT`), because it fills the announcement bar. It gives the
same hours as §14. All 358 backend tests pass.

### 27 August 2026 — hardening

Stage 8. FX staleness, a baseline rate limit, and **four bugs in the deploy
config that would each have broken something in production.**

#### The deploy blueprint was wrong in four ways

None of these show up locally, which is exactly why they were still there.

1. **Nothing ran the scheduler.** `RefreshFxRate` (hourly) and
   `ReleaseExpiredReservations` (every five minutes) are registered in
   `routes/console.php`, but Laravel's scheduler needs something to call
   `schedule:run` every minute and `render.yaml` had no cron service. Neither
   job would ever have been dispatched — the FX rate would have sat on its
   seeded placeholder forever, and **expired checkout reservations would have
   held stock permanently.** Added as a `cron` service on `* * * * *`.
2. **Nothing ran the queue.** `QUEUE_CONNECTION=database`, and both scheduled
   jobs are dispatched with `Schedule::job()` — so even with a scheduler they
   would have piled up in the `jobs` table untouched. Added a `worker` service
   running `queue:work --tries=3 --max-time=3600`.
3. **The FX key had the wrong name.** `render.yaml` set `FX_RATE_API_KEY`;
   the code reads `EXCHANGERATE_HOST_KEY`. Nothing would have matched, so the
   FX provider would have stayed unauthenticated in production. Fixed, along
   with **twelve other env vars the code reads that the blueprint never set** —
   both webhook secrets, both publishable keys, the courier base URLs, the mail
   config, and the queue/cache/session drivers.
4. **`storage:link` was never run.** `public/storage` is gitignored, so every
   image uploaded through the admin media library would have 404'd. Now in the
   container's boot command, idempotent.

The three backend services share an `envVarGroups` entry rather than
triplicating thirty variables; DB credentials stay per-service, since
`fromDatabase` is only valid there. Blueprint verified as parsing.

#### FX staleness now affects what we charge, not just what we say

`isStale()` existed and `FxRateResource` reported it, but **nothing acted on
it** — `ProductResource` priced USD off a rate of any age, and checkout locked
one.

New `getUsableRate()`: the rate, but only if it is safe to charge money
against. `getCachedRate()` still serves the last known value however old,
because a provider outage must never break product reads (Feature 2 edge case).
That is the right answer for *displaying* a price and the wrong one for
*charging* it.

- **Products** now drop `price_usd`/`compare_at_usd` when the rate is stale.
  The storefront already falls back to cedis when they are null, so an outage
  degrades to "priced in GHS" rather than quoting a dollar figure checkout
  would then refuse — the worse failure, because the customer only finds out at
  the payment step.
- **USD checkout** is refused on a stale rate, with the same 503 as no rate at
  all. GHS checkout is unaffected; it never touches the rate.
- **`/fx-rate` still serves a stale rate flagged `is_stale: true`** — that is
  what the endpoint is for.

#### Rate limiting

A baseline `throttle:api` on every API route, 120/minute keyed by user then IP
so an office NAT does not share one budget. Routes with tighter needs keep their
own — checkout 20/min, feedback 10/min, login its per-email+IP lockout. There is
a test asserting the storefront's inventory polling stays comfortably inside it.

### 27 August 2026 — DIY turnaround tiers

`GET/PUT /admin/settings/diy-turnaround`. **23 of the admin app's 30 paths are
now real**, and the 7 that remain are all genuine questions for the business
owner rather than work anyone can just do.

I had called this one a five-minute routing job. It was not: the admin Workshops
screen asks for a **list of five turnaround tiers** keyed by order type, not the
single `diy_turnaround_estimate` string that already existed. Still plainly
buildable — the shape is fully determined by the screen — just not trivial.

- **Stored as `jsonb` on `site_settings`, following `announcements`.** Five lines
  of editorial copy the brand rewrites; never queried, never joined.
- **It lives on the SiteSetting controller, not SettingsController.** The other
  `/admin/settings/*` endpoints are read-only reflections of `.env`; this one is
  content, and it is writable. It is routed under the settings prefix only
  because that is where the admin screen looks for it.
- **Admin writes, Staff reads** — the README lists the DIY turnaround estimate
  under Site Settings, which is Admin-tier.
- **`estimate` is free text.** "1-2 business days" and "1-3 weeks (depending on
  quantity)" are both real answers; forcing a number would make the second
  unsayable.
- **The single `diy_turnaround_estimate` string stays.** `DiyOrderForm.vue`
  quotes one figure and has no order-type selector to pick a tier with. Both are
  on the public `/site-settings` response now, so **Kirk can switch the form to
  per-type with no further API work** whenever the form grows a selector.

Seeded with the five tiers the screen was designed against, so a fresh database
shows real content rather than an empty list.

### 27 August 2026 — customers, team, shipments, charts, media, settings

Six endpoint groups that had no README spec but an obvious shape. **22 of the
admin app's 30 paths are now real.** Building these was cheaper than deciding
whether to; only the eight whose data model genuinely cannot be guessed from a
screen are still open (see decision 28, now much narrower).

- **Team management is Admin-only, and it is not optional.** The README requires
  two-tier roles but never says who creates a Staff account. Without this the
  seeded admin is the only account that can ever exist, and there is no way to
  onboard a staff member after handover without a developer and a database
  console. Three guards: you cannot change your own role, cannot delete your own
  account, and the last remaining admin cannot be deleted.
- **Shipments are a view over orders, not a second table.** A shipment *is* an
  order that has been paid for and has somewhere to go; a parallel table would
  be two records that can disagree about the same parcel. A shipments table
  earns its place the day Feature 5 needs part-shipments, not before.
- **Customers are read-only.** Their details are theirs to change; deletion is a
  data-protection policy question, not a button.
- **Chart revenue is GHS only, and traffic series are `null` not `[]`.** One
  line cannot honestly mix two currencies, and an empty traffic array renders as
  a chart showing no traffic — a different and false claim from "we are not
  measuring this". Feature 11 is unbuilt. **Kirk: those three need to tolerate
  null.**
- **The media library is what makes `products.images` fillable at all.** Before
  it, a product photo could only be added by editing seed data. Uploads are
  validated on file *contents* (mime + a `dimensions` rule that forces the file
  through an image decoder), not the extension or the client's Content-Type, so
  a renamed script fails twice. The stored path is generated by Laravel, so a
  hostile filename never reaches the filesystem.
- **The settings panels are read-only reflections of config, and mask secrets.**
  Publishable keys show their last four characters; secret keys are reported
  only as present or absent. An admin panel that displays a live Stripe secret
  turns a session compromise into a payments compromise. `*_enabled` means a key
  is genuinely configured, so the panel cannot claim payments work when they do
  not — both gateways report `false` today, truthfully.

**Two things found while building it:**

- **`config/services.php` had no entries for Paystack, Stripe, Yango, DHL or
  Fish Africa.** `.env.example` has carried those names since scaffolding but
  nothing read them, so `config('services.paystack.secret')` was always null —
  including inside `PaymentGatewayFactory`, which was therefore falling through
  to `FakeGateway` for the right reason **by accident**. All five are bound now,
  so that check is real and the settings panel can report honestly.
- **`public/storage` did not exist.** `php artisan storage:link` had never been
  run, and the symlink is gitignored. Every uploaded image would have 404'd.
  Created locally — **this must run on deploy**, and it belongs in the Render
  build command.

### 27 August 2026 — the native CMS

Blog, pages, site settings, newsletter and catalogue taxonomy. **Every endpoint
README Feature 9 specifies now exists** — 16 of the admin app's 30 paths.

**Stored XSS is closed.** Every rich-text body submitted to `/admin/blog` or
`/admin/pages` passes through `HtmlSanitizer` before storage, so what is in the
database is already safe to render. Server-side is the point: the editor may
strip scripts in the browser, but the browser is not the trust boundary —
anything that can POST to the endpoint can post anything.

HTMLPurifier rather than a hand-rolled allowlist, deliberately. Getting this
right means handling malformed markup, nested encodings, `javascript:` URLs,
CSS expressions and mutation-XSS, and hand-rolled sanitisers are a known source
of bypasses. Verified against `<script>`, `onclick`, `onerror`, `javascript:`
hrefs, `<iframe>`, `<style>` and `<svg/onload>` — all neutralised, formatting
preserved. `target` is not in the allowlist at all, which sidesteps
`window.opener` rather than mitigating it.

Other decisions worth not re-litigating:

- **Pages can be edited but not created or deleted.** Page slugs are storefront
  routes, so inventing one publishes a page nothing links to and deleting one
  breaks a live URL. `POST` and `DELETE` return 405. New pages stay a code
  change.
- **A blog slug is derived from the title once and never silently re-derived.**
  A published post's slug is a URL somebody may already have linked to.
- **The admin blog index lists drafts and scheduled posts**; the public one
  still shows only what is live. An editor has to be able to find the thing
  they have not published yet.
- **Site Settings: Admin writes, Staff reads.** Writing is named in the
  README's two-tier rule; reading is open because the WhatsApp number and
  contact details appear on screens Staff work in.
- **`whatsapp_number` must be 6-15 digits with no punctuation.** It is
  interpolated straight into a `wa.me` URL site-wide, and a number with spaces
  or a leading `+` produces a link that silently goes nowhere. There is a test
  for the full round trip the README asks for: change it in admin, see it on
  the public endpoint, no deploy.
- **The newsletter CSV export is streamed and chunked**, not built in memory.

**Two bugs fixed on the way past, both pre-existing:**

- **A GET returned 201.** `SiteSetting::current()` lazily creates the single
  settings row, and Laravel's `JsonResource` answers 201 whenever its model
  `wasRecentlyCreated` — so the very first read of `/site-settings` after a
  fresh deploy answered `201 Created`. This hit the **public** endpoint too,
  not just admin. Both now set 200 explicitly.
- **11 security advisories in `composer.lock`**, on `guzzlehttp/guzzle` (5, one
  high — a host-check bypass) and `league/commonmark` (6). Both are transitive
  Laravel dependencies, neither came from this change. Resolved by a
  within-constraint update — guzzle 7.14.2 → 7.15.5, commonmark 2.8.3 → 2.10.0,
  no majors, no removals. `composer audit` is clean. Worth a periodic re-run;
  this repo already rejected Laravel 11 over exactly this class of thing.

**New dependency:** `ezyang/htmlpurifier`. Its definition cache lives in
`storage/app/htmlpurifier`, which is already gitignored.

### 27 August 2026 — the admin operations API

Orders, bookings, workshop capacity and dashboard metrics. Eleven of the admin
app's thirty API paths now exist.

- **Admin gets its own order resource, and the difference is real.** Money is
  `{ amount, currency }` here rather than a bare integer — the admin app's
  `Money` type makes the pair inseparable — and `payment_reference` is present.
  It is withheld from customers because it is the gateway's internal id, but it
  is exactly what someone reconciles against a Paystack dashboard.
- **Order search covers the shipping address, not just the customer record.**
  Guests have no Customer row, so searching only `customers` would make half
  the orders unfindable by name.
- **`refunded` is Admin-only, enforced on the submitted value rather than the
  route.** The same endpoint is legal for Staff right up until they ask for a
  refund. The 403 carries a sentence rather than a raw blob, which the README
  asks for explicitly.
- **Promoting a waitlisted booking re-checks capacity under a row lock.** It is
  the one status change that can oversell — every other transition moves a seat
  the booking already holds. Two staff promoting from the same waitlist at once
  must not both land in the last seat.
- **Two guards on sessions**, both returning 422 with the number of people
  affected: capacity cannot be cut below the seats already taken, and a session
  with bookings cannot be deleted. Either would leave someone turning up to a
  seat that no longer exists.
- **Revenue on the dashboard is split by currency and never summed.** Adding
  GHS and USD needs a rate, and that rate would then disagree with every
  order's own locked `fx_rate_applied`.
- **`unreadMessages` and `openReturns` come back `null`, not `0`.** There is no
  inbox and no returns table. A confident zero reads as "no open returns"
  rather than "returns do not exist" — and this app's own rule is that invented
  numbers must look invented. **Kirk: the type says `number`, so those two need
  to tolerate null.**

**Caught while writing it:** the order search was first written with Postgres's
`ILIKE`, which does not exist in SQLite — and `phpunit.xml` runs the suite on
`sqlite`. It would have passed in production and thrown in CI, or the reverse
had it gone the other way. Now `LOWER(...) LIKE`, with a small helper for the
JSON path since Postgres wants `col->>'key'` and SQLite wants `json_extract`.
Verified against both. **Worth knowing generally: the test suite does not
exercise the production database.** Every `jsonb` column is plain JSON under
test. Nothing is wrong today, but that gap is where this class of bug lives.

**The bigger finding, and it needs a decision.** The admin app calls thirty
distinct endpoints. Only five of the nineteen still missing are specified in
README Feature 9 (blog, pages, site-settings, newsletter, categories). The rest
— returns, shipments, media, a three-endpoint inbox, an audit log, team
management, customers, workshop types, dashboard charts and six settings
sub-resources — **have no model and appear nowhere in the README.** This is the
same pattern as `ProductReviews.vue`: a screen was built past the spec and now
implies backend scope nobody has budgeted. Full table in
`docs/api-contract.md`. **Awaiting a decision on which of those are launch
scope.**

### 27 August 2026 — customer accounts and feedback

`POST /register`, `POST /login`, `POST /logout`, `GET /me`, `GET /orders`, and
`POST /feedback` with its admin read side. Sanctum SPA cookie sessions on the
`web` guard, mirroring what `AdminAuthController` already does on `admin`.

`composables/useAuth.ts` spelled out exactly what the backend needed and every
claim in it checked out — `Customer` extends Authenticatable with hashed
casting, `web` is wired to the `customers` provider, `passwords.customers` is
configured. Only the controller and routes were missing.

- **Customer routes are guarded with `auth:web`, not `auth:sanctum`.**
  `config/sanctum.php` has `'guard' => ['web', 'admin']`, so `auth:sanctum`
  would have let an **admin** session satisfy a customer route — and
  `$request->user()` would then be an `AdminUser`, whose id could collide with
  a real customer's and hand back someone else's order history. There is a test
  that signs in as an admin and asserts `/me` and `/orders` both 401.
- **`GET /orders` scopes to the session and takes no customer parameter.** The
  only safe answer to "whose orders?" is "the session's".
- **Registration deliberately does not adopt an existing passwordless row.**
  Nothing creates them today — guest checkout leaves `customer_id` null rather
  than writing a Customer. If post-purchase account creation ever does, letting
  registration claim one would hand anyone who knows a customer's email their
  whole order history. Any future claiming flow needs an emailed confirmation
  link, and there is a comment in `RegisterCustomerRequest` saying so.
- **Login is throttled per email + IP**, not per email, so nobody can lock a
  real customer out by hammering a known address from elsewhere.
- **Feedback is unauthenticated, so `message` is capped at 5,000 characters**
  and the route is throttled. A `customer_id` in the body is ignored; it comes
  from the session or not at all. The admin side is read-only — feedback is a
  record of what someone said, and a panel that can quietly edit it is worse
  than one that cannot.

**Fixed on the way past:** `OrderFactory` and `OrderItemFactory` were left out
of sync by yesterday's checkout migration — `reference` and `product_name` are
both NOT NULL now, and neither factory set them. No test had used them yet, so
the suite stayed green while `Order::factory()->create()` would have thrown for
the next person to try it. Both now set the columns they need.

**Kirk — this is the one you have been waiting on.** `useAuth.ts` lists the four
steps to turn accounts on; all of them are now unblocked. `AUTH_ENABLED` → true,
swap the three `simulate()` bodies for `GET /sanctum/csrf-cookie` then
`POST /login | /register | /logout` with `credentials: 'include'`, and add the
route middleware to `/account/orders` and `/account/settings`. `GET /orders`
gives that first page real data. Full shapes in `docs/api-contract.md`.

### 27 August 2026 — checkout and orders

`POST /checkout/session` and `GET /orders/{reference}`. The transactional half
of Feature 4, up to the point where a real gateway would take money.

A session re-prices every line from the database, quotes shipping *before*
payment, locks the FX rate for USD orders, reserves stock through the existing
`InventoryReservationService`, creates the order, and only then opens a gateway
session — all in one transaction, so a failure at any step rolls the order away
and releases what it had already held.

- **No prices are accepted from the request.** The client sends an inventory
  item and a quantity; everything about money is read server-side. There is a
  test that posts a `unit_price` of 1 against a ₵600 product and asserts the
  order still says ₵600.
- **`GET /orders/{reference}` is addressed by reference, never by id, and
  `/orders/1` is a 404.** Guest checkout means the endpoint cannot sit behind
  auth, and an order carries a name, an email and a home address — a sequential
  id would let anyone walk the order table by counting. References are `GCT-`
  plus 12 characters from an alphabet with no O/0 or I/1, since customers read
  them aloud over WhatsApp. The route is throttled on top. **This is the one
  decision in here I would not quietly change later.**
- **`payment_reference` is not in the response.** It is the gateway's own id
  and it is internal.
- **Order lines snapshot `product_name` and `variant_label`.** `product_id` is
  `nullOnDelete`, so a hard-deleted product must not take the name off a
  historical receipt. `slug` and `image` still come from the live product and
  go null when it is gone — those are presentation, not record.
- **The concurrency criterion is now a test, not a claim.** Two checkouts for
  the last unit: one order, one 409, and the loser is refused before any
  charge. Also tested — a two-line order where the second line is short leaves
  neither an orphan order nor a stuck reservation.
- **USD with no cached FX rate is a 503, not a guessed rate.** Pricing an order
  on a fallback rate loses real money on every unit sold.

**What is deliberately fake, and loudly so:** `PaymentGatewayFactory` resolves
`FakeGateway` in every environment and logs that it did, because every Paystack
and Stripe key in `.env` is still empty. It shapes its response like the real
thing — authorization URL for GHS, client secret for USD — so the storefront's
branching can be built now, but it never moves money and never confirms
anything. An order it opens stays `pending` until a webhook says otherwise,
which is exactly how the real gateways behave. Nothing downstream can come to
depend on a fake payment having "succeeded".

`YangoService` and `DhlService` are the same shape: the real interface, static
rate tables inside. Ghana standard ₵25 and free over ₵1,500 — **matched to what
the product page already promises**, because a checkout that contradicts the
product page is worse than one that is merely approximate. Express ₵50.
International ₵350 / ₵600. Live quotes change only the body of `quote()`.

**Kirk — two things are yours now.** `PaymentStep.placeOrder()` can stop
simulating and post the body it already documents; the response gives you
`payment.authorization_url` (GHS) or `payment.client_secret` (USD), and the
order's `reference` is what `/order-confirmation/{...}` should be handed, not
the id. Full request/response in `docs/api-contract.md`.

### 27 August 2026 — Feature 3's read endpoints

`GET /products/{slug}/stock` and `GET /admin/inventory`. Both are exposure
rather than construction: `InventoryReservationService` already did the hard
part (`SELECT … FOR UPDATE`, correct concurrent-hold semantics) and
`ReleaseExpiredReservations` was already scheduled. `useInventoryPolling` had
been polling into the void since it was written.

- **The stock endpoint reports *sellable* stock, not the raw column.** A unit
  held by someone else's in-progress payment is not purchasable, and offering
  it is worse than showing it as gone. It keeps the key name the composable
  already reads (`quantity_available`) — the storefront's sense of "available"
  has always meant "available to buy".
- **It also returns `size_availability`, which nothing reads yet.**
  `ProductPurchasePanel` takes a `liveStock` prop as a per-size map, and
  `[slug].vue` doesn't pass it — the composable only surfaces the aggregate.
  Sending the map now means wiring those together is a frontend change with no
  second API round trip. **Kirk: that connection is yours and it is small.**
- **`/admin/inventory` deliberately reports the opposite number.** Its
  `?low_stock=true` filter compares raw `quantity_available <=
  low_stock_threshold`, ignoring reservations, because it answers "what do we
  need to make more of" — a reserved unit is still on the shelf. Rows carry
  both counts plus `sellable_quantity`; "12 in stock, 11 spoken for" is a very
  different operational picture from "12 in stock". Tests pin both readings so
  nobody later "fixes" one into the other.
- **Admin *and* Staff can read it.** Restocking is day-to-day operations, not a
  pricing decision — `staff_or_admin`, not `admin`.

**Fixed on the way past, and it predates this change:** an unauthenticated
request to any `/api/*` route returned **500, not 401**, unless it sent
`Accept: application/json`. Laravel's `Authenticate` middleware was redirecting
guests to a `login` route this app has no reason to define — both frontends own
their own login screens — and the lookup threw. The admin SPA always sends the
header, so nobody had seen it; anything else hitting an API URL got an HTML
stack trace where a 401 belonged. `redirectGuestsTo` now returns null for
`api/*`. This affected the already-shipped `/admin/me` and every admin route,
not just the new one.

### 27 August 2026 — the product API contract closed

The storefront's `ApiProduct` type declared **thirteen fields that
`ProductResource` never sent**. Every page built against them had been falling
back to `DESIGN_PRODUCTS` for months, and nobody found out, because every
storefront fetch ends in `.catch(() => null)` — a broken endpoint and a working
one looked identical in the browser.

Eleven of the thirteen now exist end to end: `compare_at_ghs`, `product_type`,
`departments`, `widths`, `tags`, `color`, `colors`, `is_pre_order`,
`description_heading`, `model_note`, `cost_breakdown`. Migration, model casts,
`ProductResource`, factory, seeder and tests, plus a new
**`docs/api-contract.md`** that is now the shared source of truth. Change a
response shape, change that file in the same commit.

Things in it that are not obvious:

- **Two whole sections of the product page were about to vanish.** `[slug].vue`
  guards its reviews block and its Transparent Pricing block with `v-if`, so
  the moment a real API response replaced the fixture, both would have
  disappeared. `cost_breakdown` is now sent; reviews are deliberately not — see
  below.
- **`sizes` was returning integers.** PHP silently casts numeric array keys to
  ints, and the listing's size facet compares against strings, so
  `['39','40'].includes(39)` is false — the size filter would have matched
  nothing, silently, forever. `getSizesAttribute` now casts back.
- **`compare_at_usd` is derived on the same `FxRate` read as `price_usd`.** Two
  separate reads could straddle an hourly refresh and show a "sale" where the
  was-price converted lower than the selling price.
- **`ProductFactory` seeded `'images' => []`.** That is why every seeded
  product's detail page was a bare grey frame: `ProductGallery` has no
  placeholder fallback, unlike `ProductCard`, so the omission was invisible on
  the listing and total on the detail page. The factory now seeds a real photo,
  and there is a test that fails if that regresses.
- **The six designed products are now seeded for real.** `ProductSeeder` reads
  `backend/database/data/design-products.json` (since replaced by `products.json`, 30 Sep) — copy, photography, pricing and
  per-size stock, generated from the frontend fixture rather than
  hand-transcribed, so the two cannot drift. A seeded database now looks like
  the approved mockup instead of like faker ("Molestiae Hic Veritatis").
  Faker products are still created on top so pagination and the filters have
  more than six rows to work against.
- **The design's deliberate zeroes are seeded too**, which is what makes the
  struck-through sizes and the OUT OF STOCK badge actually appear locally.

Also fixed, both found while tracing the above:

- **`frontend/pages/index.vue` was fetching `/posts?limit=5`.** The route is
  `/blog-posts`. It had been 404ing into the fixture fallback since it was
  written.
- **`/blog-posts` ignored `limit` entirely**, so fixing the path alone would
  have handed the home page nine posts for a five-post strip. It now honours
  `?limit=`, clamped to 1–24.

**Left as is:** the listing filters still run client-side over a 12-per-page
API response, so they only ever see the first page. Harmless at six products,
a correctness bug at sixty. Recorded in `docs/api-contract.md` under "Known
gaps"; server-side filtering is the fix.

### 27 August 2026 — the WhatsApp channel, built out

Feature 6 was implemented but shallow. An audit of all sixteen call sites found
**eleven sending the same generic message** ("Hi! I have a question about your
sandals.") — from the cart, the stores page, an order enquiry — so the business
opened every one of them not knowing what it was about; **six places telling the
customer to use WhatsApp with nothing to click**; and **no WhatsApp at all on the
order-confirmation success screen**, the highest-intent moment on the site.

This pass makes the channel match what the three source documents actually
specify.

**One message catalogue — `frontend/utils/whatsapp.ts`.** README Feature 6 says
the number and default message must come from `SiteSetting`, "not hardcoded in
multiple places". That was true of the number and false of the messages. There
is now one typed builder per intent, and the intents are the five the brand's
own WhatsApp auto-reply enumerates: shop sandals · Sandal Sip & Paint · school
or group tour · partnerships and bulk orders · sustainability. Arriving already
in one of those lanes saves the first two messages of every conversation.

One thing to know if you add a product: `withArticle()` exists because half the
catalogue is "The Kentehene Collection" and half is "Flavourful Cross Slippers",
and a fixed `the ` prefix produced "order the The Kentehene Collection".

**`useWhatsApp` had a real bug.** It interpolated the stored number raw. The
settings field asks for "international format, including the country code" and
the admin app's own fixture stores `+233257534297`, so a perfectly reasonable
saved value produced a URL containing a literal `+` and spaces — **and it still
rendered as a working button**. That is exactly the edge case Feature 6 calls
out. The number is normalised to digits now, and anything with too few digits
left resolves to `null`, which every caller already treats as "render nothing".
Also: no more bare trailing `?text=` when there is no message, and an
empty-string message falls through to the configured default instead of
suppressing it.

**One affordance component — `components/common/WhatsAppLink.vue`.** There were
five labels and three button treatments for the same action. This owns the
`v-if` guard, the 44px target, `rel="noopener noreferrer"`, the glyph and the
analytics click, in four variants (`outlined` / `solid` / `gold` / `quiet`).
`gold` is the approved mockup's gold-on-black About button.

**Analytics.** `whatsapp_click` fires with a `source` naming the affordance.
Deliberately outside Feature 11's four standard e-commerce events: WhatsApp is
the only path that can complete an order while payment is inert, and it was the
one conversion route with no measurement at all. The admin dashboard already
renders a "WhatsApp" traffic-channel tile with nothing behind it — this is what
can eventually feed it.

**Settings caught up with the admin app.** `admin/types/settings.ts` already
declared `whatsappGreeting` and `businessHours`, and the admin WhatsApp screen
already bound inputs to both — the API simply never returned them. Both columns
exist now, seeded from the brand guidelines: the greeting is the full "Default
Greeting Message", and the announcement bar's business hours moved out of a
hardcoded constant into `SiteSetting` (its own comment had asked for this). The
greeting is WhatsApp Business profile copy — stored so the owner manages one
copy of it, never rendered on the storefront.

**`runtimeConfig.public.whatsappNumber` is no longer dead.** It was declared,
documented in `.env.example`, and read nowhere. It is now the fallback when
`GET /site-settings` is unreachable — without it a single failed request removes
the only working ordering channel from every page at once. The database value
always wins, so the "no deploy" criterion holds.

**New surfaces**, each answering a specific line in the specs:

| Surface | Why |
|---|---|
| About — gold **Chat on WhatsApp** on the dark band | The approved mockup. The band was Instagram-only, so the page's closing CTA pointed at a feed rather than at the channel that can take an order. |
| Order confirmation — **Track this order on WhatsApp**, with the order number | Brand guidelines: "shipping updates via email or WhatsApp once dispatched". |
| `/help/returns`, `/help/shipping`, `/help/bulk-orders` — slug-aware CTA | Guidelines: a return or exchange is *initiated* on WhatsApp. All three articles said "message us" and gave nothing to tap. |
| Booking — a **By appointment** block | Three of the six experiences in the guidelines (Corporate Team Building, Cultural Craft, International Visitor) run by appointment, so they have no `WorkshopSession` and never will. WhatsApp was their only possible route and they had none. |
| Size guide, shop empty state, accessibility statement | Sizing is the commonest pre-purchase question; much of the catalogue is made to order, so "no results" is often "not right now"; and asking someone who is struggling with the page to fill in a form is the wrong way round. |

**Six dead ends closed** — `SessionPicker` (no sessions), `OrderSummary`
(discount codes), both booking-form error states, and the returns and
accessibility articles. `CommonInlineNotice` gained an `action` slot for this:
a notice that explains a dead end can now offer the way out of it.

**A z-index collision, found on the way.** The product page's phone-only sticky
Add-to-Cart bar and the floating WhatsApp button are both fixed to the bottom of
a phone viewport and were both at `z-40`, overlapping in the right corner. The
first fix put the bar on top and hid the button completely — it is a full-width
white band. The button sits above it now (`z-45`, added to the scale in
`tailwind.config.ts`) and the bar carries right padding so its own button never
runs under the circle. The stacking order in `layouts/default.vue` documents it.

**Verified:** with no number configured, **zero** `wa.me` links render on `/`,
`/shop`, `/about`, `/booking` or `/help/returns`. With `+233 25 753 4297` set,
every link on twelve routes resolves to `https://wa.me/233257534297` — the
normaliser and the env fallback both work. Messages were read back from the DOM
on each surface: the size clause appears only once a size is picked, the cart and
checkout messages list each line and quote the subtotal in cedis, and each
by-appointment card names its own experience. `whatsapp_click` fires with
`{ source: "product-card" }` against a `gtag` stub. Build and
`check:responsive` clean.

### 27 August 2026 — the category row collapses on scroll

The header's third row (Best Sellers · Sandals · Ahenema · Sale) now folds
upward as soon as the page starts moving and drops back down at the top. The
header keeps its identity and its controls the whole way down a page without
holding 159px of viewport to do it — measured, it is **159px at the top and
103px once scrolling**.

This is the answer to the "worth a look" note in the sticky-header entry below.
The announcement strip stays put; it is two short lines and it carries the
currency toggle.

Four things in it that are not obvious:

- **Two thresholds, not one** (collapse above 16px, expand at or below 4px).
  With a single value the row flickers whenever a scroll settles right on it,
  and iOS rubber-banding reports negative offsets at the top of a document.
- **The state is derived on mount, not assumed.** A restored scroll position or
  a `#hash` landing starts mid-page, where the row should already be closed.
- **Focus re-opens it.** A collapsed row is 0px tall, so it cannot be tabbed
  into — a keyboard user halfway down a page would lose the category nav
  outright. `focusin`/`focusout` hold it open; the pointer path never reaches
  this because there is no height to hover.
- **An open mega menu is closed when the row collapses**, or the panel would
  hang off a row that is no longer there.

`max-height` rather than `height`, since the row is a single non-wrapping line
at a stable 56px and max-height animates without measuring at runtime.
`overflow-hidden` is what actually clips it. The transition is behind
`motion-safe:`, so a reduced-motion visitor gets the same collapse without the
travel.

**Left as is:** `scroll-padding-top` is still 11.5rem at `md`, which clears the
*expanded* header. An anchor now lands about 80px below the collapsed header
rather than 25px. Generous, but never hidden — and tightening it to the
collapsed height would put a heading underneath the header for any anchor near
the top of a document, where the row is still open. Whitespace over overlap.

### 27 August 2026 — the header is sticky

`Header.vue` is `sticky top-0` rather than `relative`, matching the approved
mockup, which wraps the announcement strip *and* the nav rows in one sticky
block. The whole chrome travels: the announcement bar, the logo row and the
category row. Currency, search and the cart are reachable from anywhere on a
page now.

**Anchors are handled once, in `main.css`, not per target.** A sticky header
covers the top of the viewport for *every* in-page anchor on the site, so the
offset is `scroll-padding-top` on `html` — 7rem below `md`, 11.5rem above it,
which is the header's own height at each breakpoint plus a little air. Putting
`scroll-mt` on individual targets would mean remembering it on every new anchor,
and forgetting is silent: the heading just lands under the header. Verified —
`/about#progress` lands 144px down against a 91px header on a phone, and 216px
against a 159px header at 1440.

**The mobile drawer gained `max-h-[calc(100dvh-7rem)] overflow-y-auto`.** It
lives inside the header, and a sticky element taller than the viewport cannot
be scrolled to the bottom of — with three categories expanded it would have
trapped its own last links.

**Worth a look:** the header is 159px at desktop — announcement strip, logo row,
category row. That is a lot of permanently-occupied viewport, and it is the
price of the earlier decision to keep both nav rows. Dropping the announcement
strip out of the sticky region (letting it scroll away while the nav stays) is
a two-line change if it proves too heavy in use.

The checkout has its own header (`layouts/checkout.vue`) and is deliberately
**not** sticky — Shopify's checkout header is not, and a checkout with fewer
fixed distractions is the whole point of that layout.

### 27 August 2026 — checkout rebuilt as a Shopify-style checkout

`/checkout` now looks like a standard Shopify checkout: stripped chrome, a
`Cart › Information › Shipping › Payment` breadcrumb, the form in a measured
column on the left, and the order summary in a tinted panel bleeding to the
right edge of the viewport. Below `lg` the summary collapses into the "Show
order summary" bar Shopify puts above everything.

**New `layouts/checkout.vue`.** A single centred logo and a row of policy links,
nothing else. Shopify strips its checkout for the reason every checkout is
stripped — a nav bar at the payment step is a row of exits — and the mega menu,
search panel and footer columns are all exits. Two things are kept that Shopify
has no equivalent for:

- **`CartDrawer`**, because "Return to cart" has to open something and this
  app's cart is a drawer, not a page.
- **`WhatsAppButton`**, because README Feature 6's acceptance criteria say it is
  visible on *every* storefront route, and it is currently the only way to
  actually complete an order while payment is inert.

**It stays three steps** rather than becoming Shopify's newer one-page checkout.
The step machinery here is real — Information validates before it will advance —
and collapsing it would mean either throwing that away or validating a whole
page at once.

**`CartSummary` → `OrderSummary`**, rebuilt to the Shopify pattern: square
thumbnails with the quantity on a badge on the corner, a discount-code row, then
subtotal / savings / shipping / total with the currency code set small against
the total.

**`CheckoutForm`** is split into Contact and Delivery in Shopify's field order —
country first, since it routes the courier and changes the fields under it —
and its action row is Shopify's: the way back as a quiet link on the left, the
way forward as the only button on the right. The shipping step gained the
review block Shopify shows above the method choice ("Contact … Change",
"Ship to … Change"), which is the step's only reassurance that it is quoting
for the right address.

**The Shipping row never shows a number.** It says "Calculated at the next step",
then "Yango · quoted at payment". The real figure comes from the courier quote
at checkout-session creation, which is not built, and putting a guess in the
Total is how someone ends up surprised at the payment screen. Shopify shows
"Calculated at next step" for exactly this reason.

**The discount code field is inert by design**, following the same shape as the
payment step and the account pages: it waits ~600ms and then explains that there
is no discounts endpoint, rather than firing a request that would 404. It is in
because a Shopify checkout without one is not recognisable — but it is the
third inert control on this page, so if that starts to feel like too much dead
UI, this is the one to drop.

**A layout note worth keeping.** The tinted column stretches to the footer via
`flex-1` the whole way up from `layouts/checkout`, not `min-h-full`: a
percentage height against a flex-sized ancestor does not reliably resolve, and
the first attempt left the tint stopping mid-page on the short payment step.

**Verified** at 1440 and 390 across all three steps, with a seeded cart.

### 27 August 2026 — product detail: panel width and price treatment

**The purchase panel is wider.** It was `md:340 / lg:384`; it is now
`md:340 / lg:400 / xl:440`. `md` is untouched deliberately — at 768 the row is
already close to an even split (340 panel against a 324 gallery), and widening
there would have squeezed the main image to about 210px. The extra room goes
where there is room to give: 440px at 1440 against 384px before, which is what
makes the description and the fit/model rows read as paragraphs rather than as
a narrow column.

**Discounted prices read as one price and one qualifier now**, not two
competing figures. `PriceDisplay` used to render the was-price *first*, at the
same size as the live price, faded to 50% opacity — so the eye landed on the
crossed-out number and had to work out which of two equally sized figures it
was actually paying.

It now leads with the live price, which turns **`sale` red** when it is a
discount, followed by the was-price at `0.8em` in muted grey with a 1px rule.
Three notes on that:

- `sale` (`#D0021B`) is what `tailwind.config.ts` documents the token for —
  "the Sale nav item **and sale pricing**" — and it is what the product card's
  own discount chip already uses. The price was the one place not honouring it.
- `0.8em`, not a fixed size, so it scales with whatever type the parent sets:
  12px on a card, 24px in the purchase panel, without a second set of values.
- `items-baseline` keeps the two on one line despite the size gap.

`ProductPurchasePanel`'s `[&_s]:opacity-50` override is gone — that is the
component's job now, and an arbitrary variant reaching into another component's
markup was the wrong lever.

Every consumer inherits this: the product card, the cart drawer and line items,
the checkout summary and the payment step.

**Not changed, and worth knowing:** the size row still wraps to an orphan on a
product with nine sizes (eight then one). It wrapped before too — at 384px it
split 7/2 — so this is not a regression, and the fix would be shrinking the
46×44 box the mockup specifies. Left alone.

### 27 August 2026 — the footer cut back to the mockup

Four columns of eighteen links became the approved mockup's two: **Shop** (Best
Sellers, Sandals, Ahenema, Bookings) and **House** (Stories, About, Shipping &
Returns, Privacy Policy), with the brand column and the newsletter either side
on the mockup's `1.4fr 1fr 1fr 1.4fr` tracks. The seven-item legal row is gone.

What came out and why:

- **Facebook and Twitter pointed at `/`.** Placeholders for accounts that are
  not modelled anywhere. Only Instagram is, and it moved to the social row
  beside the newsletter where the mockup puts it, next to WhatsApp.
- **Two identical "Sitemap" links**, both resolving to `/sitemap.xml`. That was
  **open issue #18** — removing both closes it.
- **Log In / Sign Up** — reachable from the header's account icon, which is
  where people look, and both pages are inert until customer auth exists.
- **The Company column** duplicated About's own section nav (About,
  Environmental Initiatives, Factories), which is now one merged page anyway.

**The address is Haatso, not the mockup's "Osu."** Haatso is what the brand
guidelines give, and `/stores` deliberately publishes no street address because
inventing one is worse than omitting it. It links to `/stores`, which also keeps
that page reachable now the Connect column is gone.

**Height.** With four links a column the band read as a strip stuck to the
bottom of the page, so its vertical rhythm now matches `.section-y`
(12 / 16 / 90) — the same ladder every full-width section above it sits on.
Measured: 387px at 1440, against roughly 400px in the mockup.

**Seven routes lost their only inbound link** and are now reachable by URL and
sitemap only: `/gift-cards`, `/international`, `/accessibility`, `/affiliates`,
`/legal/do-not-sell`, `/legal/supply-chain`, `/legal/vendor-code`. Three of
those are placeholder-by-design (gift cards and affiliates announce programmes
that do not exist; the three legal drafts are unreviewed — issue #14). The ones
worth a second look are **`/accessibility`** and **`/international`**, which are
real content. Nothing else was orphaned: `/stores` has the address link,
`/careers` is a card in About's More to Explore, and `/legal/terms` is linked
from the register page's consent line.

### 27 August 2026 — Sustainability merged into About

`/sustainability` no longer exists. About and Sustainability were telling one
story across two routes, so they are now one page with the section nav moving
between the parts of it — which is what that tab row was always for. Before the
merge, four of its seven tabs left the page entirely.

`/sustainability` is a **301** to `/about#sustainability` (`nuxt.config.ts`),
not a 302: the old URL was indexable and linked from the footer, the home page
and the About tab row, so its ranking should transfer rather than be split.
Every link that pointed at it — footer "Environmental Initiatives", the home
editorial pair and sustainability banner — now points at the anchor.

**Page order** is About's own story first (hero → CMS statement → Our Factories
→ Our Quality → Our Prices), then everything that came across from
Sustainability (mission → slogan ticker → Our Progress → The Latest), then More
to Explore and the social CTA.

**Components moved** from `components/sustainability/` into `components/about/`,
so the auto-import prefix matches the page they serve: `ArticleGrid` →
`AboutStoriesGrid`, plus `AboutProgressGrid`, `AboutSloganTicker`,
`AboutSocialCta`. The directory is gone.

**Two things did not come across verbatim:**

- The old masthead's `Gold Coast Tokota` wordmark at `display-brand`. A page has
  one masthead and About already has one; a second brand-sized wordmark two
  thirds of the way down read as the start of a different page. Its mission copy
  ("We're on a mission to clean up a dirty industry") survives as the
  sustainability section's heading. **This retires open issue #9**, which was
  about that token's very airy leading at the mobile floor.
- "The Latest" was two rows of six with a "Load More Articles" button paging
  through the programme feed. On a merged page that is a second, filtered copy
  of `/blog` — and `/blog` gained its own category filter earlier the same day.
  It is one row of three with a link to `/blog?category=Sustainability`.

**New: `AboutSustainabilitySection.vue`** carries the mission heading and, under
it, the **mission and vision statements verbatim from the brand guidelines**.
Those had no home anywhere on the storefront before, and the merged page is
where a reader is already asking what the company is for.

**An anchor changed.** The "Designed to last" feature section carried
`id="sustainability"` — a leftover that would now collide with the real
sustainability block. It is `id="quality"`. Nothing linked to the old one.

**The section nav is six tabs**, trimmed at the customer's request: About · Our
Workshop · Designed to Last · Sustainability · The Latest · Partnerships.
Removed: Radical Transparency, Our Progress, Our Carbon Commitment, Annual
Impact Report. "Cleaner Manufacturing" and "Workshop" merged into **Our
Workshop** — they pointed at the same subject from two directions, and booking
is a top-level header item, so nothing is unreachable.

The sections the first two named are **still on the page** and still have their
anchors (`#prices`, `#progress`) — a section nav is a shortcut list, not a table
of contents. If the intent was to remove those sections too, say so; note that
`#prices` is the one carrying the Everlane price-breakdown bitmap (issue #2).

`placeholder: true` now has **no users left** in `utils/navigation.ts` — the
Annual Impact Report was the last one.

### 27 August 2026 — the approved "Template B" design pass

The customer reviewed a second design mockup (`Gold Coast Tokota.html` at the
repo root — a self-contained Design Canvas bundle) and chose **variant B**. That
file's A|B|C toggle only swaps the *home hero*; everything else in it — the gold
accent, the announcement bar, the product cards, the product gallery, the
bookings flow, the footer, the WhatsApp handoff — is shared across all three. So
"Template B" means that design language, with the split hero.

**It is a layer on the existing design, not a replacement.** The customer was
explicit that the app's current look should stay. Confirmed before any code was
written:

| Decision | Choice |
|---|---|
| Typography | **Unchanged.** No Cormorant Garamond, no Work Sans, no webfont. The mockup's serif was declined; the Helvetica Neue stack and the fluid display ladder stand. |
| Gold | Brand-PDF `#D4AF37` as the named token, with the mockup's `#8A6A1C` / `#E8D9AD` as the readable text tints. |
| Header | Dark chrome treatment, **both nav rows and the mega menus kept**. |
| Announcement bar | Rotating, but seeded with brand-safe copy and made admin-editable. |
| Stories | The article page simplified; filtering added to the index rather than removed. |
| WhatsApp | Product page, product card, cart drawer, and the existing checkout one. |

**Design tokens** (`frontend/tailwind.config.ts`). Four new colours, annotated
as a separate block because they do *not* come from the Figma file the rest of
the palette was transcribed from: `gold` `#D4AF37`, `gold-deep` `#8A6A1C` (gold
as text on light), `gold-soft` `#E8D9AD` (gold as text on dark), `chrome`
`#111111` (the header/footer ground — not a redefinition of `ink`, which stays
pure black and which the whole app leans on). Also `sale-on-dark` `#FF6B7A`:
`sale` measures about 2.2:1 against `chrome`, so the header's Sale item needed
its own value rather than a low-contrast exception.

`main.css` gains `.chrome-dark` / `.on-light`. The base `:focus-visible` ring is
graphite and is invisible on the new dark chrome; `.chrome-dark` flips it white,
and `.on-light` flips it back inside the light panels nested in it (mega menu,
search band, mobile accordion).

**Header** (`Header.vue`, new `AnnouncementBar.vue`, `CurrencyToggle.vue`,
`utils/navigation.ts`). Dark ground, white logo, gold cart bubble.

The announcement strip rotates a list of messages with a cross-fade, sourced
from the new admin-editable `SiteSetting.announcements`, and carries a **second
line** — support hours plus a WhatsApp link. The hours are the ones published in
the brand guidelines and sit in a constant with that noted; unlike the rotating
line they are a service fact, not a commercial claim. The link is hidden below
`sm`, where the floating WhatsApp button is already on screen.

Both lines are centred **against the bar**, not against the space left over
beside the flag and currency cluster. From `sm` the row is the same
`grid-cols-[1fr_auto_1fr]` the logo row uses: two equal flanks, content in the
middle. Below `sm` the flank collapses and the marquee takes the width — going
back to absolute positioning for the cluster is what caused the 41px overlap in
closed issue #1.

**The nav items are the mockup's.** Row 2 is now Shop · Bookings · Stories ·
About · Sustainability; row 3 is Best Sellers · Sandals · Ahenema · Sale. That
retires the seven department placeholders (Mens, Womens, Kids, New Arrivals,
Best-Sellers, Merchandise, Custom Shoes) — they filtered on `?category=`, a
`departments` field only the design catalogue carries and the API has never
returned, so every one of those tabs would have shown the whole catalogue on
real data. `?type=` is a facet the shop genuinely filters, and it is verified:
`?type=sandals` → 1 product, `?type=slippers` → 3, unfiltered → 6.

The mega menus are kept, as agreed, but their contents were rebuilt for the same
reason — every link used to point at `?collection=gift-guide` and friends, which
nothing filters on, so the panel looked complete while every link in it silently
returned the unfiltered catalogue. They now use `type` / `sort` / `sale` only,
which is why the `MegaMenu.placeholder` flag could be deleted outright.

`Shop` stays in row 2 even though the mockup has no plain "Shop" item: row 3
only offers filtered entries, and dropping the one unfiltered way into the
catalogue would be a usability regression rather than a design decision. `Sale`
stays in row 3 for the mirror-image reason — sale pricing is fully built, and
the `sale` token exists for that one item.

Renaming `News & Events` to `Stories` in the nav made the page it opens
disagree with it, so `/blog`'s heading and SEO title, and the home carousel's
heading, moved to "Stories" too. The currency control is the mockup's **GHS|USD
segmented pair** — `CurrencyToggle.vue` already implemented that shape and was
sitting unused, so it was restyled and adopted rather than a third toggle being
written. The old single button showed only the *current* currency, so a visitor
could not see the other one existed without clicking.

Structure is untouched: three rows, mega menus, mobile drawer, search panel,
country flag, the hover-close timing, all of it. Only the surface changed. The
`sm`-and-below marquee stays — it was a measured fix for a real 41px overlap
(closed issue #1), not decoration.

**This closes issue #17.** The bar used to say "Sign Up For Texts" and link to
an email form. That copy is gone.

**Footer** (`Footer.vue`, `NewsletterForm.vue`). Moved to the chrome ground with
the mockup's arrangement: brand column (white logo, one-line description,
contact), the four existing link sets beside it, and the newsletter as an inline
bordered field with a gold "Join" (`NewsletterForm` gained a `tone` prop). Every
existing link set was kept — they are real routes the mockup's two columns do
not cover. Bottom bar behind a hairline, copyright left, domain right.

**Product card** (`ProductCard.vue`, new `SizeSelector.vue`). The big one. It
now carries a hover cross-fade to a second image, a stock badge driven by
`merchandising_badge` (which the API already returned and nothing rendered), the
**size picker**, an Add to cart, and an Order-on-WhatsApp button.

`SizeSelector.vue` is shared with the detail panel, which had its own inline
copy. Three states, and the third is the point: a size that is made but not
currently sellable is *drawn*, struck through with a diagonal rule, not hidden —
so a customer can see the product runs in their size and ask about a restock
instead of concluding it was never made for them.

New `composables/useAddToCart.ts` — the card and the detail page now both add to
the cart, and the synthetic `inventoryItemId` has to match between them or the
same pair added from the grid and from the detail page would sit in the basket
as two separate lines.

**Product detail** (`ProductGallery.vue`, `ProductPurchasePanel.vue`). The
gallery is the mockup's thumbnail rail — a column of 4:5 thumbs beside one large
4:5 frame, active thumb outlined, hover previewing the next shot. It replaces a
flat 2-up grid that put every photo on screen at half width each. Below `sm` the
rail is a horizontal row. The panel keeps everything it had (colour swatches,
service promises, description, fit, the phone-only sticky CTA) and gains the
`{Collection} Collection` eyebrow and the "Prefer to order via WhatsApp?" button.

**Cart drawer.** A whole-basket WhatsApp handoff beside Checkout, composing every
line into one message. Not in the mockup — the mockup can only hand off a single
product — but it is what a customer with three pairs in the basket actually
needs. Quoted in cedis regardless of display currency, because the conversation
ends in a real order and USD on the storefront is a derived display figure.

**Bookings** — rebuilt, and this fixes three defects, not just the styling.
`BookingCalendar.vue` is gone (it was named for a calendar it never had) and is
replaced by `SessionPicker.vue`, the mockup's selectable session cards with
green "N spots left" / red "Full" capacity chips.

- `GET /workshop-sessions` **had never been called from the frontend** — the form
  passed a hardcoded `[]`, so the list was always empty no matter what was seeded.
  It is wired now.
- The old prop type expected `booked_count`; the API returns `remaining_capacity`.
  Capacity would have rendered `NaN` the moment real sessions arrived.
- **Both forms would have failed validation on every submit.** They sent `name`,
  `email` and `phone` at the top level and `details.measurements` /
  `details.pickup_or_delivery`; `StoreBookingRequest` validates contact details
  *inside* `details`, and expects `details.size`, `details.foot_length` and
  `details.fulfilment`. The forms now match the request — which also means the
  DIY form finally has the EU-size and foot-length inputs the mockup designs.
- `attendee_count` and `pickup_or_delivery` both sat in form state with no UI
  control, submitted as silent defaults. Both are now rendered.
- `WaitlistBanner` emitted a `joinWaitlist` event nothing listened for, so the
  button was inert. It is an explanatory panel now: the backend already files a
  booking as `waitlisted` when capacity is gone, so joining the waitlist *is*
  submitting the form, and the submit button relabels itself to say so.
- Both submits gained loading and error states; neither had any.

**Stories.** `/blog` was already the plain grid the mockup shows — the
complexity was in the article. `BlogPost.vue` replaces a 691px full-bleed hero
with a gradient scrim, a 14px solid rule and a floating share rail 148px from
the lede with one narrow centred column: meta line, title, 16:9 cover, body. The
prose scale came down from 24px to 20px at 1440 to suit the narrower measure.
The "Shop Our Products" grid is gone from `/blog/[slug]` — it rendered four
unrelated design-catalogue products and was the heaviest block on the page. The
related-posts rail now actually matches on category, and says "Related stories"
rather than "More Stories". Per the customer's steer, filtering was *added* to
the index: category chips with the state in the URL, the way `/shop` does facets.

**Three enabling fixes**, without which the above would have been broken or
misleading:

1. **Per-size stock from the API.** `ProductResource` returned no `sizes` and no
   `size_availability`; the data was in `inventory_items.variant_attributes` but
   only ever aggregated into an `in_stock` boolean. `Product` gained
   `size_availability` and `sizes` accessors and the resource exposes both.
   Without this the size pickers would have gone blank the day real products
   landed.
2. **The FX rate was never fetched.** Nothing in the frontend called
   `GET /fx-rate`, so `fxRate` stayed `0` and **every USD price rendered as
   `$0`** the moment anyone used the currency toggle. New `plugins/fx-rate.ts`
   fetches it; `currency.displayCurrency` falls back to GHS when no rate is
   available, so a missing rate now shows cedis rather than a confident wrong
   number. `PriceDisplay` and `TransparentPricing` both use it.
3. **The currency choice now persists** in a `gct_currency` cookie, same pattern
   as the cart. It still does *not* infer a currency from the visitor's country —
   that is the commercial decision still sitting open below.

**What was deliberately not carried over from the mockup:** its A|B|C home-layout
switcher, its 2|3|4|6 column-count buttons on the shop toolbar, its hardcoded
`rate: 0.08`, its placeholder session dates, and its invented sub-collections.

**Not done, and why:** `ProductPurchasePanel` still does not receive `liveStock`.
The plan called for wiring it, but `useInventoryPolling` targets
`/products/{id}/stock`, which does not exist in `routes/api.php` — passing it
would 404 on a timer. The panel reads `size_availability` from the product
payload instead, which is now real. Wire the polling when the endpoint lands.

**Verified:** `npm run build` clean (the six postcss lexical warnings are
pre-existing — same count before and after). `npm run check:responsive` reports
no horizontal overflow and no sub-44px tap targets. Dev server walked across
`/`, `/shop`, `/shop/[slug]`, `/booking`, `/blog`, `/blog/[slug]`, `/checkout`,
`/about` — all 200, no new router warnings (the two `/sitemap.xml` ones are
issue #18, still open). **The backend changes are syntax-checked only**: there is
no local `.env` or Postgres in this working copy, so the migration and seeder
have not been run. Do that first.

### 26 August 2026 — the 22 missing storefront routes

The header and footer linked to 22 routes that did not exist. Every one logged a
router warning in dev and would have 404'd in production, and five of them
(`/size-guide`, `/contact`, `/returns`, `/shipping`, `/community/submit`) sit on
the live product-detail and home pages rather than in a footer corner. All 22 now
resolve, plus `/checkout` and `/order-confirmation/[id]` are built out.

**Two things this file used to say that were wrong, now corrected above:** the
backend is much further along than "almost nothing exists yet" (see the status
table), and the missing-routes list omitted `/account`, `/size-guide`,
`/contact`, `/returns`, `/shipping` and `/community/submit`.

**New pages — 17 files, 22 routes**

- **Account (5)** — `/account` (hub + guest order lookup), `/account/login`,
  `/account/register`, `/account/orders`, `/account/settings`.
- **Legal (5)** and **Help (3+1)** — one shared `[slug]` template each, plus the
  `/help` hub. `/accessibility` is a thin wrapper on the same template.
- **Company + commerce (8)** — `/careers`, `/international`, `/affiliates`,
  `/stores`, `/gift-cards`, `/size-guide`, `/contact`, `/community/submit`.

**The split rule, worth knowing before you add a page:** prose owned by a lawyer
or a support lead goes through the CMS `[slug]` template
(`components/content/PolicyArticle.vue`); anything with structured UI — a table,
grid, form or directory — gets its own `.vue` file. That is why `/help` itself is
bespoke: it is a directory of topics, not an article.

**Inert by design — now a named convention, not a one-off.** `admin/pages/login.vue`
established it; customer sign-in, sign-up, gift-card redemption, order lookup,
photo submission and the checkout payment step all follow it now. The shape is:
one function, a ~600ms simulated wait so the loading state is real, a
`<CommonInlineNotice>` naming the endpoint that does not exist, and a header
comment listing exactly what to change when it does. **No `$fetch` in any of
them** — verify with the Network tab before you call one of these done.

`composables/useAuth.ts` exports `AUTH_ENABLED = false`. Grep it to find
everything that changes when customer auth goes live. No route middleware is
registered, deliberately: nothing can authenticate, so a guard would make
`/account/orders` and `/account/settings` permanently unreachable.

**Checkout** is a 3-step flow (Details → Delivery → Payment) with the summary as
a sticky rail from `md` and a collapsed disclosure below it. `CheckoutForm` now
has real validation and **an email field, which it did not have at all** —
that is where the receipt goes. `PaymentStep` was 10 lines and mounted nowhere;
it is now mounted and shows the currency-routed gateway. A "Continue on WhatsApp"
CTA sits alongside, because that is the one route that completes an order today.

**Order confirmation** was 16 lines on raw `text-2xl` with `(order as any)` casts
and no `.catch()` — a failed fetch threw, and this app has no `error.vue`, so
that was a blank page on an SPA route. It now renders all six fields Feature 4
requires (number, items, total, currency, FX rate applied, delivery method) from
a typed `ApiOrder`, has loading / found / unavailable states, and polls while
status is `pending` to handle the webhook-race edge case Feature 4 calls out.

**Backend: `pages.is_draft`.** Seeding placeholder legal copy would otherwise have
*suppressed* the draft banner — the fetch would succeed, `.catch()` would never
fire, and the site would publish unreviewed policy text with no warning. The
column defaults to `true`, `PageResource` exposes it, and `usePageContent` is the
single place the draft decision is made. New `PageSeeder` seeds 10 flat slugs
(`privacy`, not `legal/privacy` — the URL prefix is frontend IA) with null bodies,
so the owner writes the real text in admin.

**Dead links fixed**

- `/#newsletter` — the header linked to an id that existed nowhere. `Footer.vue`'s
  newsletter wrapper now carries `id="newsletter"`, and the header links use
  `:to="{ hash }"` so a reader is scrolled to it instead of sent to the home page.
- `/about#dei` — that anchor never existed. Repointed to `/careers#dei`, which is
  a real section on a real page. **Open decision:** if the brand wants DEI copy on
  About instead, that needs brand-written text, not a code change.
- `/blog/holiday-gift-picks` — slug was in no fallback set, so the home tile 404'd
  with the API down. Added to `DESIGN_POSTS` as listing metadata only (title and
  artwork transcribed from `EditorialPair.vue`, not invented), so the article page
  renders its honest "not published in full yet" state.
- `/shop/the-original-ahenema` — same problem, but adding the product would mean
  inventing a price and stock, and repointing would misattribute a real customer's
  review. The link now renders as plain text unless the slug resolves, and lights
  up on its own when the SKU exists.
- `/returns` and `/shipping` — the PDP used these while the footer used
  `/help/*`. `/help/*` is canonical; the short URLs are 301s so printed inserts
  keep working.

**Housekeeping** — `/account/**` is `ssr: false` and carries `X-Robots-Tag` as an
HTTP header, because a `noindex` meta tag on an SPA route only appears after
hydration and a non-JS crawler never sees it. `robots.txt` disallowed nothing and
now disallows the three private paths. The sitemap had no config at all, so it
was publishing `/account` and `/checkout`; it now excludes them and picks up the
`[slug]` articles from `server/api/__sitemap__/urls.ts`. `check-responsive.mjs`
went from 9 routes to 28 — it also takes `ROUTES=/one,/two` now, because the full
sweep is a few minutes.

**Still to do here:** blog posts and products are missing from the sitemap for
the same `[slug]` reason and need the same handler treatment.

---

### 24 August 2026 — all three dev servers start clean

Three separate startup failures, none of them code bugs. Fixed in one pass.

**`frontend/` and `admin/`: missing `@phosphor-icons/vue`.** Both apps died on
`Failed to resolve import "@phosphor-icons/vue"` — 14 components in `frontend/`,
plus `pages/index.vue` and `components/ui/MetricCard.vue` in `admin/`. The
package was declared at `^2.2.1` in both `package.json` files *and* present in
both `package-lock.json` files; it was simply absent from `node_modules/`. An
incomplete install, nothing more. `npm install` in each app fixed it (3 new
packages in `frontend/`, 50 in `admin/`). **No source file was touched** — every
import was already correct.

Verified after install: storefront `/`, `/shop`, `/about`, `/booking` all 200
with icons rendering; admin `/`, `/login`, `/products`, `/orders` all 200, zero
errors in either startup log.

> If you hit this on a fresh clone, run `npm install` in **all three** app
> directories before anything else. `backend/` needs `composer install`, not npm.

**`backend/`: `npm run dev` no longer pretends to be a Vite app**

`cd backend && npm run dev` failed with `sh: vite: command not found`. The cause
was stock Laravel scaffolding, not a broken install: `backend/package.json`
shipped the default `"dev": "vite"` script plus Vite/Tailwind devDependencies,
but this backend is a **headless API** — the only `@vite(...)` reference in the
whole app is the untouched `resources/views/welcome.blade.php`, and
`node_modules/` was never installed there. Nothing built, nothing needed to.

`backend/package.json` now has no devDependencies, and its scripts point at the
real dev server:

- `npm run dev` → `php artisan serve` (port 8000)
- `npm run build` → prints "headless API, nothing to build" and exits 0, so a CI
  step that blindly runs `npm run build` per package does not fail

Verified: `php artisan serve` comes up and `GET /api/v1/site-settings` returns
200 (PHP 8.5.6 / Laravel 12.64.0).

**Left alone deliberately:** `backend/vite.config.js`, `resources/css/app.css`,
`resources/js/*` and the `GET /` route returning `welcome.blade.php`. That route
throws a Vite-manifest exception if you hit `http://localhost:8000/` in a
browser — it did so before this change too, since no build ever ran. Harmless
for an API, but if it bothers anyone, make `/` return a small JSON identity
response and delete the four leftover asset files.

---

### 21 August 2026 — admin dashboard rebuilt

The admin app was a scaffold: 30 files, every page under 30 lines, `pages/index.vue`
a bare `<h1>Dashboard</h1>`, `pages/login.vue` with no form, `tailwind.config.ts`
with `colors: {}`, and `main.css` holding three `@tailwind` lines. It is now a
working dashboard of **36 routes**, built against the Dashboard UI Kit
(Figma `c11bIgiFJmUEcpOR2J9FYL`) in Gold Coast Tokota's identity.

#### One CSS file owns colour, and dark mode costs nothing

`admin/assets/css/main.css` declares every colour once as a space-separated RGB
triple; `.dark` redefines **only those same variable names**. `tailwind.config.ts`
exposes them as `rgb(var(--x) / <alpha-value>)`, which is what makes
`bg-accent/10` work against a custom property — without the alpha placeholder,
opacity utilities silently no-op.

The result is worth stating plainly: **the compiled CSS contains zero
`dark:`-scoped colour utilities.** The whole theme is one variable swap. Verified
against the build output, not asserted.

Palette is the brand PDF's — Gold Coast Gold `#D4AF37`, Craft Brown `#7A5A3A`,
Sustainability Green `#2F6F4F`, Warm Sand `#EADFC8` — over the storefront's
existing neutral ramp, so the two apps stay one design system per README
"Styling Requirements". Type is a **dense fixed scale** (`text-metric-lg` →
`text-micro`), deliberately not the storefront's fluid display tier: a 1440px
table and a 2560px table want the same 14px row.

Theme is light / dark / **system**, cookie-persisted, tracking the OS live via
`matchMedia`, with an inline boot script in `nuxt.config.ts` so a dark-mode
reload does not flash white. No `@nuxtjs/color-mode` dependency.

#### The data layer, because the admin API does not exist

`backend/routes/api.php` still defines two public routes. All seven `/admin/*`
calls the old scaffold made returned 404, and the rebuild needs ~21.

`composables/useAdminApi.ts` tries the real endpoint, unwraps the
`{ data, meta, errors }` envelope, and falls back to bundled fixtures on
404/unreachable. `NUXT_PUBLIC_ADMIN_DATA` forces `live` or `fixtures`; default is
`auto`. **As each endpoint lands, its screen starts showing real data with no
code change.** The fallback is never silent — a "Demo data" chip sits in the
header whenever fixtures are serving.

Fixtures are seeded from the brand PDF rather than lorem ipsum, because a
dashboard full of "Sample Product" teaches an operator nothing: the real staff
roster, the six real workshops with their actual capacities, the four policy
pages with published copy, and ten WhatsApp threads that are scenarios this
business handles. All deterministic (xorshift32 from a fixed seed) against a
fixed `NOW`, so screenshots are stable and "2 hours ago" stays 2 hours ago.

#### Four role tiers, and interns expire

README and the `admin_users` enum say two tiers. The brand PDF's "Admin and Staff
User Roles" table names three. The business asked for a fourth — `intern`, whose
access is **time-boxed and extendable**.

`utils/permissions.ts` holds a 38-capability map; templates ask
`can('orders.refund')`, never `role === 'admin'`. That is why adding the fourth
tier was one table entry instead of a codebase sweep. A lapsed intern keeps its
role label but is filtered down to `.view` capabilities, with a countdown banner
and an **Extend access** action on `/team` (+7/+30/custom). Extending a *lapsed*
account runs from today, not from the old expiry — getting that wrong silently is
how someone stays locked out after being told they were extended.

The profile modal carries a **"View as"** switcher through all four tiers. It
exists because permission gating is otherwise unreviewable with no login and one
account. It only ever narrows the UI; it cannot widen server access.

Two design flaws the role testing exposed, both fixed:

- Gating `/` on `analytics.view` removed Overview from Staff and Intern sidebars
  while the page still rendered — reachable by URL, invisible in the nav. Split
  out `analytics.revenue`: everyone gets the operational dashboard, only Admins
  see the money.
- The mobile nav scrim sat at `z-50` above the sidebar at `z-40`, so every link
  in the drawer was unclickable. Z-ladder reordered and the ordering constraint
  documented in `tailwind.config.ts`.

#### Login is built and deliberately inactive

Full split-layout form — brand panel, validation, show-password, remember-me.
Submit runs the real loading state, then explains that the API endpoint has not
been built rather than throwing a 404. **No route middleware is registered
anywhere**, so every page stays reachable for review. When Feature 9 lands, the
submit body becomes a `sanctum/csrf-cookie` call plus a POST and nothing else
changes.

#### Screens

Shell (sidebar / header / right rail / `⌘K` palette) transcribed from Figma node
`10:2521` — 212px, 8px item padding at 12px radius, 20px icons, collapsing to the
68px icon rail the Calendar frame shows, and an off-canvas drawer below `lg`.

| Figma frame | Route |
|---|---|
| Dashboard `1:24956` | `/` — tiles, two-series revenue chart, traffic panels |
| — | `/analytics` — order-derived sales reporting, GA4-independent |
| Products `10:3761` | `/products`, `/inventory`, `/customers`, `/newsletter` |
| Deals `19:945` | `/orders/board` — fulfilment kanban, per-column totals |
| Calendar `14:6138` | `/bookings/calendar` |
| Blog `26:1297` | `/blog/[id]` — Tiptap editor, 0/8000 counter, SEO rail |
| Team `23:1972` | `/team` |
| Chats `29:8158` | `/inbox` |

`/analytics` is built on order records rather than GA4 on purpose: README
Feature 11 requires dashboard figures to stay accurate when a customer blocks
the tracking script, so revenue, order counts and the currency split are
computed server-side and only the traffic panels come from the analytics feed.
The page labels which is which, because an operator needs to know which numbers
survive an ad-blocker.

Charts are hand-rolled inline SVG (`components/charts/`) rather than a library:
the kit's charts are flat and hairline, so a catmull-rom path does in fifty lines
what would otherwise cost ~150KB — and the strokes are theme tokens, so dark mode
needs no configuration.

Things the PDF specifies that README does not, now modelled: the **six workshop
types** with their own recurrence/duration/capacity (a session is an instance of
one, which is what lets the owner schedule another Sip & Paint without re-entering
that it seats 20); the **DIY turnaround matrix** of five order types, replacing
README's single `diy_turnaround_estimate` string; the **four policy pages**
alongside `about` as one `Page` resource; and Ghanaian **payment methods**
(MTN MoMo, Telecel Cash, AirtelTigo Money).

#### The WhatsApp inbox is a simulation, and says so on every screen

**Scope deviation worth a decision.** README Feature 6 scopes WhatsApp as a deep
link only — `wa.me/<number>`, explicitly "no API integration required". A two-way
inbox needs the Business Cloud API, a verified WABA, approved templates and a
webhook receiver on the Laravel side. None of that exists or has been costed.

So `/inbox` is built against the real Cloud API's shape — message direction,
delivery receipts, the **24-hour free-form reply window**, template approval
states — and served entirely from fixtures. A non-dismissible banner reads
*"Simulated — the WhatsApp Business Cloud API is not connected."* Sending appends
locally and stamps the message `simulated`. `/settings/whatsapp` holds the real
config shape (phone number ID, WABA ID, webhook URL, verify token) disabled, so
wiring it later is mechanical.

The deep link itself is real and owner-editable on that same page. Note it warns
that the number on file (`+233 25 753 4297`) came from the PDF annotated
"update with official number" — worth confirming before launch, since a wrong
number silently breaks the main ordering channel.

#### Dependencies and removals

Added `@phosphor-icons/vue` (already a `frontend/` dependency, so no new vendor)
and `@tiptap/vue-3` + `starter-kit` (the old `PageEditor.vue` was a `<textarea>`
labelled "Rich text editor", which the CMS acceptance criteria cannot be met
with). `npm audit` reports 7 vulnerabilities — all pre-existing in Nuxt's own
tree, none from these.

Removed all ten root-level scaffold components (`DataTable`, `StatusBadge`,
`MetricCard`, …) once nothing referenced them; superseded by `components/ui/`.
`pages/about.vue` is superseded by `/pages/about`.

**Routing bug fixed:** `pages/orders.vue` alongside `pages/orders/` makes the
former a *parent* route needing `<NuxtPage/>`, so `/orders/board` rendered the
orders list under a "Fulfilment" breadcrumb. Seven such pairs converted to
`index.vue`.

#### Accessibility pass — measured, not eyeballed

Three real defects, all found by measuring rather than looking:

- **Secondary text failed WCAG AA.** `--fg-faint` was `#A3A3A3`, which is
  **2.5:1 on white** against a 4.5 requirement — and that tier carries
  timestamps, stock counts and form hints across every screen, so it is content,
  not decoration. The Figma kit renders it as `rgba(28,28,28,0.4)`, which has
  the same problem. Both foreground ramps were rebuilt contrast-first: light is
  now 13.6 / 8.6 / 6.4 / 4.8, dark 15.7 / 10.5 / 7.1 / 5.3 — four
  distinguishable steps that all clear AA. `--warning` was darkened for the
  same reason. A checker samples every text node on eight routes in both
  themes, resolves the true painted background by walking ancestors, and
  applies the large-text exemption; it reports **0 failures**.

- **Chart colours failed on white, and the donut was unreadable.** Brand gold
  `#D4AF37` is **2.1:1 on white** — under the 3:1 WCAG 1.4.11 wants for
  graphical objects — and sand and grey failed too. Worse for a donut: gold,
  sand and brown are all warm yellows, so adjacent slices merged into one
  smear. The series ramp is now hue-separated (gold, green, terracotta, slate,
  brown, stone), tuned to a different luminance per theme so the same series
  keeps its identity when the theme flips. Verified: every colour ≥3:1 on its
  own ground, and the closest pair is **ΔE 21 in CIELAB**, well above the ~10
  where two fills stop being distinguishable. The donut also gained a hairline
  gap between segments, so colour is not the only thing carrying the boundary.

- **`.sr-only` inside a scrolling table broke the page layout.** The permission
  matrix put visually-hidden "allowed"/"not allowed" text in each cell.
  Tailwind's `.sr-only` is `position: absolute`, so that text escaped the
  horizontal scroll container and extended the **document's** scroll region —
  the whole page gained 139px of sideways scroll at 390px. The state moved to
  the cell's `aria-label`, which reads identically to a screen reader and
  occupies no layout. The trap is documented next to the table primitives in
  `main.css`.

A keyboard pass over Overview, Products and the blog editor confirms every tab
stop is reachable with a visible focus ring, the profile dialog moves focus in,
traps it, closes on Escape and restores focus to its trigger, and the command
palette is fully operable by keyboard. Collapsed-sidebar icons carry real
tooltips shown on focus as well as hover, rather than a bare `title`.

#### Two bugs the verification found that reading would not have

Pointed the app at a stub API serving `site-settings` live and 404ing the rest:
**every live field arrived `undefined`.** Laravel API Resources emit snake_case
(`PageResource` and `SiteSettingResource`, the two that exist, both do); the
TypeScript models are camelCase. `useAdminApi` now normalises response keys in
one place, so the models stay idiomatic and the call sites do not have to
remember. Found before the real endpoints landed rather than after.

And the Playwright sweep caught **no `<h1>` on all eight settings routes**.
Nuxt de-duplicates repeated path segments, so `components/settings/SettingsShell.vue`
auto-imports as `<SettingsShell>`, not `<SettingsSettingsShell>`. The tag was
unresolved, so Vue rendered it as an unknown element — slot content appeared,
the shell's heading and section nav silently did not. Renamed to
`components/settings/Shell.vue` so directory + filename compose unambiguously,
and the sweep now asserts the settings nav is present, not just that the page
loaded.

#### Verified

`tsc --noEmit` clean (proved to cover the new files by planting a deliberate
error first), `npm run build` clean, and a Playwright sweep of all 36 routes ×
both themes asserting no page errors, an `<h1>` present, the correct themed body
background, and no horizontal overflow. Interactions checked in a browser:
drawer, `⌘K` palette, theme cycle + persistence, 68px sidebar collapse, and the
role switcher changing the nav (Super Admin/Admin 14 entries · Staff 13 · Intern 12).

### 21 August 2026 (later)

#### Full responsive pass — a real breakpoint ladder, fluid type, 44px targets

The storefront was transcribed from a 1440px Figma frame and had never had a
responsive pass. One number explained most of it: **119 `lg:` classes, 18 `sm:`,
and zero `md:`, `xl:` or `2xl:`**. So 768–1023px (iPad portrait, a half-screen
laptop window) rendered the *phone* layout at tablet width, and 1024px flipped
everything at once into the 1440 design compressed into 1024 — 217px-wide
product cards still carrying 392px image heights, 244×508 gallery frames.

It is now **23 `sm:` / 71 `md:` / 66 `lg:` / 5 `xl:` / 2 `2xl:`**.

**Measure first.** `frontend/scripts/check-responsive.mjs` (`npm run
check:responsive`, needs a `npm run build` first) drives headless Chrome over
every route at ten widths from 320 to 2560 and asserts three things: the
document does not scroll sideways, no element's box passes the right edge, and
at ≤768px every control clears the 44px tap floor. It respects clipping
ancestors (a marquee inside `overflow-hidden` is not an overflow), the WCAG
2.5.5 inline-link exemption, and label-wrapped inputs. Baseline was **551
findings**; it is **0** now. `playwright-core` is the only new dependency — it
uses the system Chrome, so there is no browser download.

**What the script corrected in our own analysis:** the static audit predicted
five horizontal overflows at 320–375px. Measured, **four were wrong** — the
44-character footer legal link renders 243px against 280px available, and the
header row's min-content fits 320px. Only the announcement-bar collision was
real (the sign-up link overlapped the currency cluster by 41px). The other four
were hardened anyway as latent risks, but nothing was chasing a live bug.

- **Fluid display type.** Every display token in `tailwind.config.ts` is now
  `clamp(mobileMin, intercept + slope·vw, figmaMax)`, solved so each renders its
  **exact Figma pixel value at 1440px** (verified: 96/70/64/54/46/40/40/38/32/24)
  while scaling smoothly below. Line heights became unitless ratios. That
  retired every `text-x lg:text-y` pair — including a 32px→96px jump on the
  Sustainability masthead — and the hand-rolled `@media (min-width: 1024px)`
  block in `BlogPost.vue`. New `lede` token (16→24px) for hero subtitles.
- **Announcement bar** — the flag/currency cluster was `absolute`, so it
  reserved no width. It is a normal flex child now, and below `sm` the message
  runs through the new `components/common/Marquee.vue`, extracted from
  `SloganTicker` (which also had a real bug: one run was ~1540px, so the loop
  exposed blank space above that width — it now renders two copies per run).
  **Closes known issue #1.**
- **Tablet tier** — the desktop nav, mega menu, footer columns, About story
  splits, `EditorialPair`, `ValueProps`, testimonials, the shop sidebar and
  every grid now step at `md`. `ProgressGrid` and `RelatedPosts` hold exactly
  three items, so `sm:grid-cols-2` left a permanent orphan; both go 3-up at `md`.
- **Aspect ratios replace fixed image heights.** Nothing used `aspect-[]`; every
  image was a pixel height on a fluid width, so the crop changed at every
  viewport and was right only at 1440. `ProductGrid`'s skeleton was a flat
  `392px` that matched only `lg` and so *caused* the shift its comment claimed
  to prevent — it now shares the card's ratio.
- **Above 1440** — the shop listing and PDP had no `max-w` at all. Capped, along
  with a dozen sections, using the existing content+2×60px convention.
- **44px tap targets** — the cart ± steppers were **12×12px** (the padding was
  on the wrapper, not the buttons), carousel dots **7px**, footer links ~16px.
  All fixed by growing the *button* while the drawn dot/swatch keeps its size.
- **Interaction rebuilds** — `Modal.vue` had no max-height or scroll container,
  so content taller than the viewport was unreachable; the shop filter was an
  inline accordion pushing ~1000px above the grid and is now a proper sheet with
  a scrim, an active-filter count and a "Show N results" close; the PDP buy panel
  is sticky at `md`+ with a phone-only sticky add-to-cart (the inline CTA sits
  ~2000px down for a 4-image product); `FormField` was rebuilt on the design
  tokens at ≥44px with a textarea variant (DIY measurements were going into a
  single-line input).
- **New `composables/useBodyScrollLock.ts`** — reference-counted, and uses the
  `position: fixed` treatment because `body { overflow: hidden }` alone does not
  hold on iOS Safari. The cart drawer, nav drawer (which had no lock at all),
  modal and filter sheet all share it.
- **`useScrollRail` paging was wrong below `lg`.** It scrolled by one container
  width, which only tiles when slides are 25%/20%; elsewhere it landed mid-slide
  and the dots lit a page the user never scrolled to. It now measures real slide
  offsets, and a `MutationObserver` re-measures when an async post list resolves
  (a `ResizeObserver` does not fire when only `scrollWidth` changes).
- **z-index scale** documented in `layouts/default.vue`. The WhatsApp button was
  `z-50`, tying with the header and modal; it is `z-40`, has a real icon instead
  of the literal text "WA", respects `env(safe-area-inset-bottom)` (which needed
  `viewport-fit=cover` in `nuxt.config.ts`), and the footer reserves its corner.
  Layout switched to `min-h-dvh`.

#### `ValueProps` — the three home-page blocks were not centred on a phone

Follow-up to the pass above, reported by Kirk. `components/home/ValueProps.vue`
had `flex-col items-start` on its section. In a **column** flex container the
cross axis is horizontal, so `items-start` sized each of the three blocks to its
own content width and pinned it left; each block's inner `items-center` /
`text-center` then centred its contents *within itself*, which is why the three
icons drifted apart instead of lining up on the page.

Fixed with `items-stretch sm:items-start` — stretched, each block is full width
and its existing centring does the rest. From `sm` the row is horizontal, where
`items-start` correctly means top-aligned columns, so that behaviour is
unchanged.

Icon centre positions, before → after:

| viewport | before | after | page centre |
|---|---|---|---|
| 320px | 160 / 160 / 160 | 160 / 160 / 160 | 160 |
| 375px | **188 / 175 / 168** | 188 / 188 / 188 | 188 |
| 414px | **207 / 175 / 168** | 207 / 207 / 207 | 207 |
| 640px+ | unchanged (3-up row) | unchanged | — |

Worth knowing because it is easy to miss and easy to reintroduce: at 320px all
three blocks hit the container cap at 280px, so they already lined up. The
misalignment only appeared from ~360px, where the longest line ("Gold Coast
Tokota, E A Amartei, Haatso") still fitted but the shorter headings did not
stretch to match. **`npm run check:responsive` does not catch this** — nothing
overflows and no tap target is small; it is an alignment defect, which is
exactly the class of thing the script's docstring warns still needs a human eye.

#### One page gutter, one vertical rhythm — `.page-gutter` / `.section-y`

The blog article's related-stories grid ran flush to both viewport edges on
desktop and sat directly on the footer. The wrapper at `pages/blog/[slug].vue`
carried `lg:px-0 lg:pb-0`, zeroing both, while every neighbouring section on the
page sat at 60px. That was the visible symptom of a structural gap: there was no
shared gutter anywhere — no `theme.container`, nothing in `main.css`, no padding
on `layouts/default.vue` — so each section invented its own value. Fourteen
different desktop gutters were in play, plus a `px-2` carousel track, two
`px-0` rows, and a doubled ~96px gutter in `UgcGallery`.

- `assets/css/main.css` — new `@layer components` with **`.page-gutter`**
  (`px-5 lg:px-[60px]`, the Figma section gutter) and **`.section-y`**
  (`py-12 lg:py-[90px]`). Both are commented in place with the reasoning.
- Every full-width section across Home, About, Sustainability, Shop, Blog and
  the checkout/booking/order-confirmation stubs now uses them.
- **Large gutters that were really content measures became max-widths.** The
  article prose (`lg:px-[228px]`), `EditorialPair` (185px), `ValueProps` and the
  testimonial rail (77px), `ProductReviews` and shop recommendations (196px),
  `ExploreGrid` (200px) and `StatementSection` (258px) now carry the standard
  gutter plus `mx-auto max-w-[Npx]`, where N reproduces the Figma width at the
  1440px frame. They no longer over-stretch above 1440px either.
- `components/blog/RelatedPosts.vue` now owns its own section chrome (gutter,
  rhythm, and a **visible "More Stories" heading** matching "Shop Our Products"
  above it) instead of depending on a wrapper to supply padding; the wrapper in
  `pages/blog/[slug].vue` is gone.
- Deliberately left full-bleed: `SloganTicker`, the `FeatureSection` image half,
  and the wide `<img>` bands on the About page.
- Deliberately unchanged: the app chrome — see open decision #8 below.
- `npm run build` passes.

### 20 August 2026

#### Announcement-bar flag now reflects the visitor's country

The static US flag SVG in `components/layout/Header.vue` was a design export,
not a signal. It is now resolved per visitor, server-side, and deliberately
awkward to move with a VPN.

- `server/utils/geo.ts` — the resolver. Trust order: CDN geo header
  (`cf-ipcountry`, `x-vercel-ip-country`, …) → IP geolocation → browser
  timezone. The timezone only ever *corrects* the first two, never stands in
  for them.
- `server/utils/timezone-countries.ts` — 468-entry IANA zone → ISO country
  table, **generated from the system tzdb `zone.tab`**. Regenerate it when
  tzdb ships new zones; do not hand-edit.
- `server/api/geo.get.ts` — the one endpoint. Nothing in its request shape lets
  a caller name a country outright.
- `composables/useVisitorCountry.ts` — resolves during SSR (so the first paint
  is already right for most visitors), then re-asks on mount with the browser
  timezone attached.
- `utils/geo.ts` — shared types plus `flagUrl()` / `countryName()`.
- Flags come from the `flag-icons` package, served straight out of
  `node_modules` via `nitro.publicAssets` at `/flags/{cc}.svg`. Nothing is
  vendored into `public/`, and a visitor downloads exactly one 300-byte SVG.

**Why a VPN has trouble with it.** A commercial VPN rewrites the exit IP but
not the operating system clock. So when the IP sits on a hosting/VPN ASN (org
name matched against a keyword list) *and* its country disagrees with the
browser timezone, the timezone wins — that is the honest signal. Two further
locks: the browser's claimed IANA zone is cross-checked against its own
`getTimezoneOffset()`, so editing the zone string alone gets the hint
discarded; and the per-visitor cache cookie is HMAC-signed, since an unsigned
one would have been a free country override.

**What it does not stop**, and nobody should claim otherwise: a VPN user who
also changes their OS timezone, or a residential-proxy exit on an ASN that
reads as consumer broadband. Both come back as `confidence: 'low'` with
`vpnSuspected: true` on the API response, so anything built on top of this can
decide for itself how much to trust the answer.

**One deployment caveat.** The flag is now per-visitor content inside SSR'd
HTML. `/api/geo` sends `cache-control: private, no-store`, but if a CDN is ever
put in front of the storefront with full-page caching enabled, the first
visitor's flag would be served to everyone. Either vary the page cache on the
edge country header or render the flag client-only at that point.

Config is all optional — see the new geo block in `frontend/.env.example`.
`NUXT_GEO_SECRET` should be a stable random value in production; without it the
cookie signing key changes on every restart and every visitor is re-looked-up.


#### Sustainability page — new route `/sustainability`

Figma node `10:1163`.

- `pages/sustainability.vue` + `components/sustainability/` — `HeroSection`,
  `ArticleGrid`, `SloganTicker`, `ProgressGrid`, `SocialCta`.
- Extracted `components/blog/FeatureCard.vue` — the large editorial card is the
  same component in Figma as the article page's "More stories" card, so
  `RelatedPosts` and the Sustainability listing now share one implementation.
- `BrandButton` gained a `shape` prop (`block` | `soft`) for the design's
  soft-cornered mixed-case buttons, instead of a second button component.
- Three tokens added to `tailwind.config.ts`: `display-brand`,
  `display-heading`, `action`.
- **The slogan ticker is live text, not the exported bitmap.** Figma exports it
  as a 2654px image clipped at both edges — a marquee. As an image it could not
  scroll, could not be read aloud, and would crop unpredictably. It is now real
  text with a CSS marquee that respects `prefers-reduced-motion`.
- **Load More is functional**, paging six at a time. Until the CMS exists the
  fallback pool is the six designed stories plus existing news posts in the same
  programme categories, so the button has something to do.
- Five of the eight Figma exports were **already in the repo under different
  names** — matched by checksum, not filename. Only two new image files.
- **Bug fixed:** `pages/blog/[slug].vue` resolved fallback posts from
  `DESIGN_POSTS` + the related-posts trio, so three of the new Sustainability
  slugs would have 404'd from their own cards. It now resolves from
  `SUSTAINABILITY_POSTS`; `RELATED_POSTS` became dead and was removed.
- **Routing:** the header's "Sustainability" tab, the footer's "Environmental
  Initiatives", the home sustainability banner CTA and the "Cleaner Footwear"
  editorial card all pointed at `/about#sustainability` as a stand-in. They now
  point at the real page.

#### About page — `/about` rebuilt

Figma node `6:726`. Previously a bare CMS-body dump.

- `pages/about.vue` + `components/about/` — `SectionNav`, `HeroSection`,
  `StatementSection`, `FeatureSection`, `ExploreGrid`.
- `FeatureSection` is one reusable mirrored block covering all three story
  sections via `reverse` / `tinted` / `contain` / `height` props.
- The CMS hook is preserved: `GET /pages/about` still runs, and its `body`
  overrides the opening manifesto when the admin has written one.
- Sections carry `id="factories"` and `id="sustainability"`, which the footer
  and header already deep-link to.
- Four tokens added to `tailwind.config.ts`: `timberwolf`, `display-xl`,
  `display-statement`, `display-section`. Eight images committed to
  `public/design/`; two 8MB exports downscaled to under 1MB.
- `aboutSectionNav` added to `utils/navigation.ts` for the page's own tab row.
- **Bug fixed:** `Header.isActive()` stripped the hash before comparing, so
  `/about` and `/about#sustainability` both matched path `/about` and *both*
  tabs underlined. It now compares the anchor, gated behind a mounted flag —
  the hash never reaches the server, so an ungated check would cause a
  hydration mismatch.

#### Documentation

- This file added, and a "Team status log" section added to `CLAUDE.md` so it
  gets updated as part of each change rather than retroactively.

### 19 August 2026

#### Header search panel (01:06–01:08) — uncommitted

- `components/layout/SearchPanel.vue` — the search band under the nav rows
  (Figma `6:552`), with four "Popular Categories" tiles that resolve to
  filtered shop listings rather than bespoke landing pages.
- `pages/shop/index.vue` now honours a `?q=` term, matching against name,
  colour, product type and tags, and forwards it to the API.

#### News & Events (00:06–00:08) — uncommitted

- Listing (`pages/blog/index.vue`, `BlogCard`, `BlogList`) and article page
  (`pages/blog/[slug].vue`, `BlogPost`) built from Figma `10:906` / `10:1405`.
- `BlogShare.vue` — X / Facebook / LinkedIn share rail, resolving the canonical
  absolute URL via `useRequestURL` so it is correct under both SSR and client
  navigation.
- `utils/newsPosts.ts` holds the designed content. Only the "Tyred of Waste"
  article is drawn in full, so it is the only entry with body blocks; the
  others carry listing metadata and their detail pages say so rather than
  inventing an article.
- Article body styling is scoped CSS on the rich-text container, not utility
  classes, since the CMS will supply raw HTML.

#### Product detail + cart (00:00–00:02) — commits `24bd702`, `393413a`

- Product detail page: `ProductGallery`, `ProductPurchasePanel`,
  `ProductReviews`, `TransparentPricing`, `BenefitIcon`, `StarRating`.
- Slide-out cart: `CartDrawer`, `CartLineItem`, `CartRecommendations`, backed by
  an expanded `stores/cart.ts` and a `cart-hydrate` plugin.
- `utils/designCatalogue.ts` (515 lines) — the full designed product catalogue
  standing in for Feature 2.
- Cost-breakdown, gift, return and shipping icons added under
  `public/design/icons/`.
- `393413a` then re-centred the brand logo in the header.

### 18 August 2026

#### Shop listing, mega menu, brand assets (23:39) — commit `2752742`

- Shop listing (`pages/shop/index.vue`, 271 lines) with `ProductFilter`,
  `ProductCard`, `ProductGrid`.
- `MegaMenuPanel` + a heavily reworked `Header`; `utils/navigation.ts`
  established the nav model, including the `placeholder` flag convention.
- Brand assets (logo variants, favicons, `og-image`) added for both the
  storefront and admin apps; `nuxt.config.ts` head config for icons.
- `utils/catalog.ts`, `PriceDisplay` and `formatters` for GHS/USD display.

#### Base homepage (20:19) — commit `d27cccb`

- All eight home sections: `HeroSection`, `FeaturedCollection`,
  `SustainabilityBanner`, `NewsCarousel`, `TestimonialCarousel`,
  `EditorialPair`, `UgcGallery`, `ValueProps`.
- `Header` and `Footer` filled out; `BrandButton`, `CarouselArrow`,
  `CarouselDots`, `NewsletterForm`.
- `useScrollRail` composable for the carousels.
- `tailwind.config.ts` seeded with the Figma design tokens — the colour,
  type-scale and naming conventions everything since has followed.

> **Two commit subjects are offset from their contents.** `2752742` ("added
> single product page") actually delivered the *shop listing*, filters and mega
> menu; `24bd702` ("added product listing, single product and side cart views")
> delivered the *product detail page* and cart. Go by the file list, not the
> subject line, when reading `git log`.

> **Uncommitted work.** Everything from 19 Aug 00:06 onward — News & Events, the
> search panel, About, Sustainability — is still in the working tree. Commit it
> before branching, or the "last commit" reference above will mislead you.

### Before this window

`6409e46` (15 July) — initial scaffold: storefront, admin dashboard and Laravel
API, per the folder structure in `README.md`.

## What is left to do

### Backend — the critical path

This section used to open "Almost nothing exists yet." That has not been true
for a while. Feature 2 is done and its contract is closed (27 Aug); Feature 3's
hard part — reservations with row-level locking — is written and tested; the
scheduler runs `RefreshFxRate` hourly and `ReleaseExpiredReservations` every
five minutes. What is missing is the transactional half.

**Every third-party credential in `backend/.env` is empty** (13 of 15 values).
Paystack and Stripe test keys are self-serve; Fish Africa, Yango, DHL and
exchangerate.host need a human to request business access. Those requests have
lead times measured in days, and they gate items 3–6 below — start them now,
not when you reach the stage that needs them.

In dependency order:

1. ~~**Feature 2 — Catalogue & pricing.**~~ — **closed 27 Aug.** Tables,
   `ProductController`, `FxRateService` and the full storefront contract all
   exist; see `docs/api-contract.md`. The FX provider is chosen
   (exchangerate.host) and `RefreshFxRate` is scheduled — only the access key
   is missing. ~~*What is left: server-side listing filters.*~~ — **filters,
   search and sorting closed 8 Sep**; `sort=top-rated` is the one part that
   cannot be built, because it needs the reviews model open decision 24 is
   waiting on.
2. ~~**Feature 3 — Inventory.**~~ — **closed 27 Aug.** Reservations with
   row-level locking, the scheduled release job, `GET /products/{slug}/stock`
   and `GET /admin/inventory` with the low-stock filter all exist and are
   tested. *What is left is on the storefront:* `[slug].vue` doesn't pass
   `liveStock` to `ProductPurchasePanel`, so the polled per-size map the API
   now sends goes nowhere. Small frontend wiring, no API work.
3. **Feature 4 — Checkout & payments.** *Code-complete, waiting on one key.*
   `POST /checkout/session`, `GET /orders/{reference}`, `PaystackService`,
   `POST /webhooks/paystack` with `processed_webhook_events` idempotency, and
   the `OrderPaid` event that finalises inventory all exist and are tested
   (28 Aug). §13 settled the gateway question — Paystack for both currencies,
   no Stripe. *What is left is `PAYSTACK_SECRET_KEY`*, which is self-serve and
   an hour of somebody's time. The notification listener landed on `OrderPaid`
   on 8 Sep, so a paid order now emails the customer.
4. **Feature 5 — Delivery.** Yango (domestic) and DHL (international) quote/booking.
5. **Feature 7 — Bookings.** Workshop sessions with capacity + waitlist and the
   unlimited DIY queue exist; the programme behind them landed 28 Aug (§15's six
   experiences, `GET /workshop-types` public and admin). ~~*What is left:* an
   upload endpoint for DIY reference images.~~ — **the upload endpoint landed
   8 Sep** (`POST /booking-uploads`, with a daily prune of abandoned files);
   the storefront form still has to use it. *What is left is the
   by-appointment enquiry path*, since three of the six experiences have no
   bookable date to attach a booking to — open decision 35, a business
   question.
6. ~~**Feature 8 — Notifications.**~~ — **closed 8 Sep.** All five triggers
   dispatch behind a swappable `NotificationChannel`; email needs no
   credential (`MAIL_MAILER=log`), and SMS falls back to `LogSmsChannel`
   until Fish Africa keys exist. *What is left is the credential and a
   sandbox test — Ghana network delivery rates unverified (README
   "Clarifications Needed" #3) — plus correcting `FishAfricaSmsService`'s
   request shape against the live API, which has never been run.*
7. ~~**Feature 9 — CMS + admin API.**~~ — **the specified half closed 27 Aug, and role enforcement 28 Aug.**
   Products, inventory, feedback, orders, bookings, workshop sessions,
   dashboard metrics, blog, pages, site settings, newsletter and taxonomy all
   exist, and `PageEditor` submissions are sanitised server-side. Returns and the workshop programme
   followed on 28 Aug once the brand document supplied their rules, and the
   four role tiers are enforced server-side for the first time. *What is left is
   the 5 admin paths that are in neither document and have no model* — see open
   decision 28. That is a scope conversation, not a task.

8. **Customer accounts.** ~~Register/login/logout/me, order history.~~ —
   **closed 27 Aug.**

### Storefront

- ~~**Booking page** (`/booking`) — the four components are stubs.~~ — **closed
  27 Aug.** Sessions are fetched, capacity and waitlist render, and both forms
  now send the payload `StoreBookingRequest` actually validates. What is left is
  backend: an upload endpoint for DIY reference images (the form records the
  file *name* and asks the customer to send the photo over WhatsApp, because
  `details.reference_image` is typed as a string and nothing accepts a binary).
- ~~**Drop the USD branch at the payment step.**~~ — **closed 1 Oct** with the
  checkout wiring. `PaymentStep` redirects to `authorization_url` for both
  currencies and no longer mentions Stripe.
- **Name the workshop on the booking page.** Sessions now carry
  `workshop_type` (`GET /workshop-sessions`), and `GET /workshop-types` lists
  all six experiences including the three that run by appointment and never
  appear in the session list.
- **Wire `useInventoryPolling`** into `ProductPurchasePanel`'s `liveStock` prop
  once `GET /products/{id}/stock` exists. Per-size stock is real in the product
  payload as of 27 Aug, so the panel is correct without polling — it just will
  not update while someone is looking at the page.
- **Checkout + order confirmation** — both built out (26 Aug), but inert at the
  payment boundary until `POST /checkout/session` and `GET /orders/{id}` exist.
- **Wire the pages to real endpoints** as each API lands, and delete the
  corresponding design fallback.
- ~~**Routes referenced by the footer/nav that do not exist yet**~~ — **closed
  26 Aug.** All 22 are built. A router warning in dev is now a regression, not
  something to expect; that is the fastest check that a nav change is sound.
- **Customer auth is inert.** The pages are complete and validate, but nothing
  authenticates until the storefront is wired up. The backend side is now done
  except profile editing: register / login / logout / me landed 27 Aug and
  **password reset on 8 Sep** — though the storefront has no
  `account/reset-password` page for the emailed link to land on yet. The model side is
  already done: `Customer` is an `Authenticatable` with `HasApiTokens` and hashed
  passwords, the `web` guard is configured, `passwords.customers` is wired, and
  Sanctum's `guard` array lists `web`. Grep `AUTH_ENABLED` for the frontend side.
- **`stores/auth.ts` types `user.id` as `string`**, but `customers.id` is a
  Postgres bigint. Fix when the real session shape lands.
- **The legal and help pages are unreviewed placeholder copy.** See the open
  decisions table — this is the highest-risk item on this list before launch.
- **The announcement bar needs an admin editor.** `SiteSetting.announcements`
  is a JSON array on the API and the storefront renders it, but the admin app
  has no field for it, so today it can only be changed by re-seeding. It is the
  place the brand's delivery and payment claims live — see the open decision
  about the mockup's copy.
- **Sitemap misses blog posts and products** — `@nuxtjs/sitemap` can't enumerate
  `[slug]` params. `server/api/__sitemap__/urls.ts` does this for legal and help;
  extend it.
- **Currency toggle does not follow the flag.** The header knows the visitor's
  country but `stores/currency.ts` still defaults everyone to GHS. The cookie
  half of this is done (27 Aug — `gct_currency`, and an explicit choice already
  wins), but the *geo default* is deliberately still not wired: deciding
  GH → GHS / everyone else → USD is a commercial decision, not a technical one.
- **`public/design/flag.svg`** is now unused; kept only as the original Figma
  export. Delete it once nobody wants the reference.
- ~~**Placeholder nav targets** are flagged `placeholder: true`~~ — **closed
  27 Aug.** Nothing in `utils/navigation.ts` carries the flag any more: the
  seven mega-menu categories went with the Template B nav, the menus now link
  only to filters `/shop` implements, and the Annual Impact Report tab was
  removed with the About section-nav trim. The flag is gone from the types too,
  so re-add it deliberately if a stand-in destination ever comes back.

### Admin dashboard

The frontend is built. What it needs from the backend, in the order the screens
will light up:

1. **The `/api/v1/admin/*` surface** — ~21 paths. The exact list is the routing
   table in `admin/fixtures/index.ts`; each key there is an endpoint the UI
   already calls, and the fixture next to it is the response shape it expects.
   **27 of 30 now exist** — the product reads closed on 8 Sep; what is left is
   the inbox (×3), activity and audit, which is open decision 28, not a task.
2. ~~**Admin login**~~ — **closed 1 Oct.** `pages/login.vue` signs in through
   `GET /sanctum/csrf-cookie` → `POST /admin/login`, `plugins/session.client.ts`
   restores the session from `GET /admin/me`, and `middleware/auth.global.ts`
   gates every page.
3. **Widen `admin_users.role`** from `enum('admin','staff')` to four values
   (`super_admin`, `admin`, `staff`, `intern`) and add a nullable
   `access_expires_at` timestamp plus an extensions audit trail. The middleware
   aliases need a matching third/fourth tier.
4. **Extra `Page` slugs** — `shipping-and-delivery`, `returns-and-exchanges`,
   `privacy-policy`, `terms-of-service` alongside `about`.
5. **A `diy_turnaround_tiers` structure**, replacing `SiteSetting`'s single
   `diy_turnaround_estimate` string.
6. **Server-side HTML sanitisation** on every CMS save. The editor emits HTML;
   README Feature 9 names stored XSS explicitly and no client-side escaping
   substitutes for it.
7. **WhatsApp Cloud API + a webhook receiver**, *if* the inbox is to go live —
   see the scope note in the 21 Aug entry. This is new scope, not configuration.

### Cross-cutting

- **Feature 10 — SEO.** `@nuxtjs/sitemap` is installed and `useSeoMeta` is on
  every built page; structured data and social cards still to do.
- **Feature 11 — Analytics.** GA4 snippet + server-side event mirroring.
  `useAnalytics` is a stub.
- **Feature 14 — Testing.** No test infrastructure on either side yet.
- **GSAP motion.** Only `GsapHeroIntro` and `GsapScrollReveal` exist. Phase 3d
  calls for a fuller motion pass on Home/About.

---

## Known issues and open decisions

| # | Issue | Notes |
|---|---|---|
| 37 | ~~**Seeded stock is a placeholder, not a count**~~ | **Closed 30 Sep.** The brand will enter counts from the admin inventory screen. Production seeds 0 per size; only local/test databases get the nominal 5. **Until counts are entered, every product in production reads OUT OF STOCK.** |
| 43 | ~~**The admin "Adjust" stock button does nothing**~~ | **Closed 1 Oct.** Opens `InventoryAdjustModal`, which PATCHes an absolute count and threshold and shows the API's refusal verbatim when checkouts hold more than the new count. **Stock still has to actually be entered in production** — every size there is 0 until somebody does. |
| 38 | **The real products have no copy** | No descriptions, no was-prices, no cost breakdown were supplied, and none were invented. The "Transparent Pricing" panel and description block are hidden for every product until the brand writes them. |
| 39 | ~~**Colourways are photos without variants**~~ | **Closed 2 Oct** — stock rows are colour × size; see the 2 Oct entry. Original note: | Each of the 63 photos is a different colour of a style, but `colors` is a swatch list with no link to an image or to stock, and sizes are the only inventory axis. A customer cannot currently say *which colour* they are buying. This is the "colour has become a variant" moment the 27 Aug migration comment anticipated — needs a `colour` on `variant_attributes` and per-colour images. **Build before launch.** |
| 40 | **Domfo's women's price cannot be represented** | Sheet: unisex, 500 male / 300 female, sizes 40–45. Seeded as men's at 500. Needs the women's size range from the client, then either a second product or per-variant pricing. |
| 41 | **Two things on the sheets still need the client to confirm** | (a) "Krakye" (folder) vs "Kyrakye" (sheet). (b) Whether the 28 styles should be grouped into merchandising collections — `FeaturedCollection.vue`'s fallback tiles still name Sikapa, Obrempong, Kentehene and others that do not exist in the data. *Settled 30 Sep: prices are cedis; the materials lists are stored verbatim.* |
| 42 | **One Opanyin photo is held back** | `IMG_4416` (beige woven upper) has a clasp resembling another brand's logo. Not published until the client confirms it is theirs to use. |
| 33 | **The brand document and the README disagree about Stripe** | README Feature 4 pairs Stripe with USD; `GOLD_COAST_TOKOTA.md` §13 names Paystack alone as the payment gateway, settles in GHS, and lists Visa/Mastercard/Verve under it. §22 says the document is the source of truth and not to change what it states, so **both currencies now route to Paystack** and `client_secret` is always null. The Stripe config binding is retained but unrouted, so restoring it is a factory change. **Needs a sentence from the business owner**: is dollar settlement through Stripe actually wanted, or was the README's split an assumption? |
| 34 | **Nobody has supplied email addresses for the five people §17 names** | The document gives Samuel Kumi-Gyau, Mary Seade, Isaac, Isaaka and Peter with their roles, job titles and tiers — everything a seeder needs except the one field an account is keyed on. Inventing addresses for real colleagues is not something a seeder should do, so the roster is unseeded and the only account on a fresh database is the test super admin. **Five email addresses closes it.** |
| 35 | **Three of the six workshop experiences cannot actually be booked** | §15 runs Corporate Team Building, Cultural Craft and International Visitor "By Appointment" — no standing schedule, so no `workshop_session` for a booking to attach to. `GET /workshop-types` advertises them and `requires_appointment` flags them, but a customer who picks one has nowhere to go except the WhatsApp link. An enquiry path (a booking with no session, or a routed form) is the fix; **whether it is a booking or an enquiry is a business question**, so it is not guessed. |
| 36 | **Two tests fail on Postgres but pass on SQLite — neither is a production bug** | Found by running the whole suite against a scratch Postgres database on 8 Sep, which is what issue 29 recommends. (a) `PaystackPaymentTest > a replayed webhook…` fails with `SQLSTATE[25P02] current transaction is aborted`. The webhook's idempotency catches a unique-constraint violation from `ProcessedWebhookEvent::create()`; on Postgres a failed statement poisons the surrounding transaction, and under `RefreshDatabase` **the test itself is that transaction**. In production there is no wrapping transaction, so the catch works and the endpoint is correct. It is still worth hardening — if anyone ever wraps that handler in a transaction, idempotency breaks on Postgres and only on Postgres; `insertOrIgnore` or a savepoint would remove the trap. (b) `FeedbackTest > feedback is listed newest first` passes `created_at` to `Feedback::create()`, but `created_at` is not in the model's `$fillable`, so it is silently dropped and both rows get the same timestamp — the ordering is then a coin flip that SQLite happens to win. A test bug, not an API bug. **Neither was touched on 8 Sep**: both are outside the catalogue-filter change, and quietly editing the payment path while doing something else is how a money bug gets in. |
| 30 | **Production serves the API with `php artisan serve`** | The Dockerfile's CMD is Laravel's built-in dev server: single-threaded, explicitly not for production in Laravel's own docs. It will work for a demo and fall over under real traffic. The fix is a proper process manager — FrankenPHP is the smallest change (one base-image swap plus a Caddyfile), php-fpm + nginx the conventional one. **Not done in the hardening pass deliberately:** it is a container change that cannot be verified without an actual deploy, and shipping an unverified web server swap is worse than a documented known issue. Needs one deploy to test. |
| 28 | **Five admin endpoints have a data model nobody can guess** | The admin app calls 30 paths; **27 now exist** (the two product reads landed 8 Sep). What is left is inbox (×3), activity and audit. **Returns and workshop-types came off this list on 28 Aug**: they were unguessable only because the README covers neither, and §9/§21 and §15 of the brand document write both out in full — which made them transcription rather than invention. The rest of the previously-listed set was built on 27 Aug once it was clear their shape was obvious. What is left — a 3-endpoint inbox, an activity feed and an audit log — **is in neither the README nor the brand document, and has no model.** Same pattern as the reviews UI: the inbox could be WhatsApp thread history, a ticketing system or email, and each produces a different schema; the audit log's retention and scope are policy questions with compliance weight. They fall back to fixtures with the demo-data chip, so nothing is broken; but this is unbudgeted scope and should be a decision, not a launch-checklist surprise. **Awaiting a decision on launch scope.** |
| 29 | **The test suite runs on SQLite; production is Postgres** | `phpunit.xml` sets `DB_CONNECTION=sqlite`. Every `jsonb` column is plain JSON under test, and Postgres-only SQL (`ILIKE`, JSON operators) passes or fails differently in the two. Already bit once — see the 27 Aug admin-operations entry. Nothing is wrong today; the fix is either running tests against Postgres in CI or keeping queries strictly portable. |
| 24 | **Product reviews are unplanned scope, and fully built** | `ProductReviews.vue` renders sort, a star filter, a rating distribution and a fit meter — and **no README feature covers reviews at all**. `rating` and `reviews` are the only two `ApiProduct` fields the API does not send; the section hides itself via `v-if` until it does. Before a `product_reviews` table gets built someone needs to decide **who writes reviews, whether they are moderated, and whether launch ships seeded ones**. Cheapest honest option if it is deferred: drop the section rather than leave it fixture-fed. **Awaiting a decision.** |
| 25 | ~~**Seeded collection assignments are a guess**~~ | **Closed 30 Sep.** The six demo products and their guessed collections are gone; the real catalogue is seeded with no collection at all. Whether the brand wants merchandising groupings above its 28 styles is issue 41. |
| 26 | **Listing filters only ever see the first page** | The shop page sends `type`, `color`, `size`, `width`, `category`, `q`, `sale` and `sort` as query params, `ProductController::index` ignores every one of them, and `matchesFilters()` filters client-side over a 12-per-page response. Invisible at six products; wrong at sixty. Server-side filtering is the fix — see `docs/api-contract.md`, "Known gaps". |
| 27 | **The third-party credentials are still empty** | Everything in `backend/.env` for Paystack, Yango, DHL, Fish Africa and exchangerate.host. **Paystack is now the whole of the payments story** (§13) and its test key is self-serve — an hour of somebody's time, and the only thing between `PaystackService` and a working checkout. The rest need business accounts requested by a human, with real lead times — **they gate Features 4, 5 and 8, so request them now**, not on arrival at the stage. Stripe's keys are no longer needed unless the business overrules §13. |
| 1 | ~~**Site-wide horizontal overflow below ~500px**~~ | **Closed 21 Aug 2026.** Measured rather than estimated: the document never actually scrolled sideways, but the sign-up link did overlap the currency cluster by 41px at 320px and 375px. The cluster is a normal flex child now, and the message runs through a marquee below `sm` — the treatment Kirk chose. |
| 14 | **Every legal and help page is unreviewed placeholder copy** | `/legal/**`, `/help/**` and `/accessibility` render drafts from `utils/policyContent.ts` behind a "Draft — awaiting review" banner. Plausible and Ghana-specific (Act 843, Yango/DHL split, WCAG 2.1 AA), but written to give the pages shape — **not** reviewed, and not a statement of policy. A lawyer needs to write the real privacy policy and terms; a support lead needs returns and shipping. Publish from admin and `is_draft` flips off. **Must not ship to production as-is.** |
| 15 | **DEI has no link anywhere** | Was "`/about#dei` repointed to `/careers#dei`". The footer link that raised this went with the 27 Aug footer trim, so nothing now links to `/careers#dei` at all — the section exists and its copy is still a placeholder. The decision is no longer *where* DEI lives but **whether it needs a home**; if it does, it needs brand-written text and a link. **Awaiting a decision.** |
| 16 | **The testimonial names a product that doesn't exist** | Aseye Bakah's review credits "The Original Ahenema"; that slug is in no fallback set and no fixture. The link renders as plain text until it resolves. Confirm the real SKU with the brand. |
| 17 | ~~**"Sign Up For Texts" links to an email form**~~ | **Closed 27 Aug 2026.** The announcement bar was rebuilt as the approved mockup's rotating strip and that copy no longer exists. |
| 21 | **The approved mockup asserts two things the project cannot back up** | Template B's announcement bar reads "Free delivery in Accra" and "Order online, pick up in Osu". Checkout charges GH₵25 for Accra delivery, and the address on file in the brand PDF is Haatso, not Osu. Neither line shipped. The bar renders "Handcrafted in Ghana / Pay with MoMo or card / We ship worldwide" instead, and `SiteSetting.announcements` makes the real copy a settings change rather than a deploy. **Confirm both claims with the brand**, then set them. |
| 22 | **DIY reference images are not uploaded** | The booking form has the mockup's dropzone, but `StoreBookingRequest` types `details.reference_image` as a string and no endpoint accepts a binary. The form records the file *name* and tells the customer, in place, to send the photo over WhatsApp with a prefilled link. Honest, but it is a stopgap — an upload endpoint (and storage) is the real fix. |
| 18 | ~~**The footer lists two identical sitemap links**~~ | **Closed 27 Aug 2026.** Both went when the footer was cut back to the approved mockup's link set. A product sitemap is still worth emitting — see the sitemap item under *What is left to do*. |
| 23 | **Seven routes now have no inbound link** | Cutting the footer back to the mockup orphaned `/gift-cards`, `/international`, `/accessibility`, `/affiliates` and three `/legal/**` drafts. They resolve and stay in the sitemap. Four are placeholder-by-design, but **`/accessibility` and `/international` are real content** — decide whether they earn a link somewhere (a slim utility row in the bottom bar would hold both) or stay unadvertised until launch. |
| 19 | **Gift cards are announced but don't exist** | `/gift-cards` explains the programme and both forms are inert. Making it real is backend scope: a `GiftCard` model with a code and balance, issuance on purchase, and a redemption step in the checkout session. |
| 20 | **`/size-guide` conversions are the standard ladder, not measured lasts** | The EU/UK/US table is the generic conversion, not Gold Coast Tokota's own lasts. A chart wrong by half a size causes returns — confirm against production lasts before launch. |
| 2 | **About price-breakdown artwork is Everlane's** | The Figma export (`about-price-breakdown.png`) has "Everlane T-shirt vs Traditional Retail" and USD figures baked into the bitmap. Needs real Gold Coast Tokota cost data. Cannot be fixed in code. |
| 3 | **About "Designed to last" copy was rewritten** | Figma's text names Everlane, cashmere sweaters and Peruvian Pima tees. Adapted to the brand. All other copy is verbatim from the design. |
| 4 | **"Our Carbon Commitment" is tagged `Style`** | Straight from Figma `10:958`; looks like a design slip. Transcribed faithfully — flag if it should read Sustainability. |
| 5 | **FX provider chosen, key missing** | No longer blocks Feature 2 — that shipped 27 Aug. README "Clarifications Needed" #2. The *frontend* half is no longer blocked as of 27 Aug — `plugins/fx-rate.ts` consumes `GET /fx-rate`, and prices fall back to cedis when no rate is available rather than rendering `$0`. What is still missing is a real provider behind `FxRateService`; it currently serves a `seed-placeholder` rate. |
| 6 | **Fish Africa coverage unverified** | Blocks confidence in Feature 8. README "Clarifications Needed" #3. |
| 7 | **Engagement end date unconfirmed** | README "Clarifications Needed" #1. |
| 8 | **App chrome is 8–12px out of alignment with page content** | Content now sits at a 60px desktop gutter everywhere (`.page-gutter`). `Header.vue` is still at 68px, `Footer.vue` at 72px, `MegaMenuPanel.vue` at 140px and `SearchPanel.vue` at 156/326px — all Figma-exact. Moving them to 60px would line the nav and footer edges up with the content below, but it visibly changes brand chrome. **Awaiting a decision.** |
| 9 | ~~**Marquee line-height on the Sustainability masthead**~~ | **Closed 27 Aug 2026.** The masthead was the only user of `display-brand`, whose 176/96 Figma leading measured 66px at the 36px mobile floor. It went with the About/Sustainability merge — About already had a hero, and a second brand-sized wordmark mid-page read as a different page starting. The token is still defined and now unused; delete it if nothing claims it. |
| 11 | **The WhatsApp inbox implies scope README does not cover** | README Feature 6 specifies a `wa.me` deep link and states "no API integration required". `/inbox` needs the Business Cloud API, a verified WABA, approved templates and a webhook receiver. It ships as a clearly-labelled simulation; **whether to fund the real integration is awaiting a decision.** |
| 12 | ~~**Role model disagrees across sources**~~ | **Closed 28 Aug 2026.** `GOLD_COAST_TOKOTA.md` §17/§18/§22.14 settled it. The enum is a string, `super_admin` exists, and the two role middlewares were replaced by one capability check mirroring `admin/utils/permissions.ts` — because §18's line about Admins not touching system settings or payment credentials cannot be expressed as "admin or not". `intern` was kept: not in the document, but not contradicted by it, and already shipped. **Still open, and small: nobody has supplied email addresses for the five people §17 names**, so the roster cannot be seeded. |
| 13 | **WhatsApp number is provisional** | §12 gives `+233 25 753 4297` annotated "(update with official number)". Seeded into site settings and surfaced with a warning on `/settings/whatsapp`. As of 28 Aug the address, trading hours, greeting and tagline sit beside it as editable columns, so correcting the number is a settings change rather than a deploy. **Still needs confirming before launch.** |
| 10 | **`Toast.vue` has no placement, and no consumer** | It renders in normal flow with no shared region in `layouts/default.vue`, so every caller would invent its own positioning. Nothing mounts it yet, so where toasts appear, how they stack, and whether they clear the fixed WhatsApp button is undecided. Width is bounded; placement is **awaiting a decision** when something first needs it. |

---

## Conventions worth knowing before you edit

- **Design fallback pattern.** Every page fetches its API endpoint, catches the
  failure, and falls back to hardcoded content transcribed from Figma
  (`utils/designCatalogue.ts`, `utils/newsPosts.ts`). This is deliberate: the
  pages render their designed state before the backend exists. When an endpoint
  lands, wire it up *and remove the fallback*.
- **Design tokens live in `frontend/tailwind.config.ts`**, each annotated with
  its Figma style name. Add tokens there rather than using arbitrary values, so
  design and code stay reconcilable. The gold/chrome block at the bottom of the
  colour list is annotated separately on purpose — it comes from the approved
  Template B mockup and the brand PDF, not from the Figma file, and trying to
  reconcile it against a Figma style name will waste your afternoon.
- **Dark chrome needs `.chrome-dark`, and light panels inside it need
  `.on-light`.** The base `:focus-visible` ring is graphite and vanishes on the
  header and footer ground; a white one vanishes on the mega menu. Both classes
  are in `main.css`. If you add a light panel inside the header, mark it.
- **One size picker: `components/shop/SizeSelector.vue`.** The card and the
  detail panel both use it, at `sm` and `lg`. Do not write a third — the
  three-state treatment (and especially the struck-through unavailable state) is
  the part customers read, and two versions of it drift.
- **Adding to the cart goes through `useAddToCart()`.** The synthetic
  `inventoryItemId` has to match everywhere or the same pair added from two
  places becomes two cart lines.
- **Breakpoints are a ladder, not a switch.** base = phone · `sm` (640) large
  phone, 2-up grids · `md` (768) tablet, side-by-side splits begin · `lg` (1024)
  full desktop structure · `xl`/`2xl` reclaim width. **Never jump base → `lg`** —
  that is the bug this codebase started with, and it puts a phone layout on every
  tablet. Tailwind defaults; no `screens` override.
- **Display type is fluid, not stepped.** The display tokens in
  `tailwind.config.ts` are `clamp()` values that hit their exact Figma pixel size
  at 1440px. Name one token; do not write `text-a lg:text-b`.
- **Images get `aspect-[w/h]`, not a pixel height.** A fixed height on a fluid
  width re-crops the photo at every viewport.
- **44px minimum tap target.** Grow the *button*, keep the drawn dot or swatch at
  its design size (see `CarouselDots.vue`, `ProductPurchasePanel.vue`). Inline
  links inside a sentence are exempt (WCAG 2.5.5).
- **`items-start` on a `flex-col` is a horizontal instruction.** It collapses
  each child to its content width and left-aligns it. That is usually harmless —
  most of our stacked sections are left-aligned anyway, or their children carry
  `w-full` — but it silently breaks any child that centres its *own* contents,
  because each one then centres inside a different width. If a stacked block is
  meant to look centred, use `items-stretch` and put the row alignment behind the
  breakpoint that makes it a row: `items-stretch sm:items-start`. This bit
  `ValueProps`; a sweep of all seven routes at 375px found no other instance,
  and the remaining `flex-col items-start` hits (footer columns, category pills,
  the blog share rail, `BrandButton`) are all correctly left-aligned or
  intrinsically sized.
- **Run `npm run check:responsive` before you push layout changes.** It needs a
  `npm run build` first, and it is a diagnostic, not a snapshot suite — "no
  overflow" is not "reads well", so still look at 768 and 1440 yourself.
- **Overlays use `useBodyScrollLock`**, and the z-scale in `layouts/default.vue`.
- **Section padding is `.page-gutter` + `.section-y`, never ad hoc.** Defined in
  `frontend/assets/css/main.css`. A full-width section takes both. If a design
  calls for a narrower column, keep the gutter and constrain the *content* with
  `mx-auto max-w-[Npx]` — outer padding is not a measure. The only exceptions
  are deliberate full-bleed elements (marquee, edge-to-edge imagery) and the app
  chrome in `components/layout/`.
- **Components are auto-imported by directory prefix** — `components/about/HeroSection.vue`
  is `<AboutHeroSection />`, `components/common/BrandButton.vue` is
  `<CommonBrandButton />`.
- **GSAP is client-only, always.** Wrap in `<ClientOnly>` / `onMounted` and
  never touch `window`/`document` during SSR. Content must be readable before
  any animation runs.
- **Prose vs structured UI decides where a page lives.** Content owned by a
  lawyer or a support lead goes through the CMS `[slug]` template
  (`components/content/PolicyArticle.vue`, backed by `usePageContent`); anything
  with a table, grid, form or directory gets its own `.vue` file. `/help` is
  bespoke for exactly this reason — it is a directory of topics, not an article.
- **"Inert by design" has one shape — follow it.** When the UI is ready and the
  endpoint is not: one function, a ~600ms simulated wait so the loading state is
  real, a `<CommonInlineNotice>` naming the missing endpoint, and a header
  comment listing what to change when it lands. **No `$fetch` in that path** —
  a raw 404 is worse than an honest explanation. See `pages/account/login.vue`
  and `components/checkout/PaymentStep.vue`.
- **`usePageContent` is the only place the draft decision is made.** Do not
  re-derive "is this approved?" in a page, and do not remove `<ContentDraftNotice>`
  to make a page look finished. A page showing unreviewed legal text without that
  banner is the failure mode the `pages.is_draft` column exists to prevent.
- **`<CommonInlineNotice>`, not `Toast.vue`.** The toast still has no placement,
  no region in the layout and no consumer (issue #10). An in-flow notice needs
  none of that resolved.
- **`placeholder: true`** in `utils/navigation.ts` marks a link whose
  destination is a stand-in, not a real page. Grep it before assuming a route
  exists.
- **Commits carry no AI attribution** — no `Co-Authored-By` trailers for Claude,
  Cursor or any other assistant. See `CLAUDE.md`.

---

## Keeping this file current

Update it in the same change that alters the code — not afterwards. Each time:

1. Bump **Last updated** and **Last commit on `main`**.
2. Add a dated entry at the top of **Recent changes**: what was built, what was
   fixed, and any judgment call a teammate would otherwise have to reverse-engineer.
3. Move anything finished out of **What is left to do**.
4. Add or clear rows in **Known issues and open decisions** — especially
   anything waiting on a human decision.

Keep entries specific enough to act on. "Updated the header" helps nobody;
"header `isActive()` ignored the hash, so two tabs underlined at once" does.
