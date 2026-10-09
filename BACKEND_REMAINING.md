# Backend — what is left to make the whole app functional

**Generated 8 September 2026** from a read of `backend/`, `routes/api.php`,
`admin/fixtures/index.ts`, `frontend/`, `render.yaml` and `FOR_THE_TEAM.md`.

Baseline: `php artisan test` → **272 passed, 713 assertions, 4.8s**. Nothing is
broken. The list below is what is *absent*, not what is failing.

---

## Summary

The backend is much closer to done than the size of this list suggests. Of the
12 README features, the transactional core (catalogue, inventory reservations,
checkout, Paystack webhook, orders, bookings, CMS, admin API, capability-based
roles) is written and tested. What remains falls into four buckets:

| Bucket | Items | Can you start today? |
|---|---|---|
| A. Missing code, fully specified | 6 (**5 done**) | **Yes** — no decision or credential needed |
| B. Blocked on a credential | 5 | Partly — request access now, code later |
| C. Blocked on a human decision | 6 | No — needs an answer first |
| D. Production-readiness / cleanup | 6 | Yes |

**If you only do three things:** ~~A1 (admin product endpoints)~~ and
~~A2 (order notifications)~~ — **both done 8 Sep**. The one that matters most
now is **B1**: a self-serve Paystack test key, without which checkout still
cannot complete end to end.

---

## A. Missing code, fully specified — start now

### ~~A1. `GET /admin/products` and `GET /admin/products/{id}` do not exist~~ — **done 8 Sep**
**The only outright broken thing in this document.** `routes/api.php:310-315`
registers `POST`, `PUT` and `DELETE` for admin products but no read endpoints.
Three admin screens call them and are therefore stuck on fixtures:

- `admin/pages/products/index.vue:24` → `/admin/products`
- `admin/pages/products/[id].vue:19` → `/admin/products/{id}`
- `admin/pages/settings/seo.vue:21` → `/admin/products`

The public `GET /products` is not a substitute: it is scoped `active()` and
paginated at 12, so drafts and inactive products — exactly what an admin needs
to see — are invisible.

**Done.** Both endpoints exist on `products.view`, unscoped, with `?active=`,
`?q=`, `?category_id=` and `?per_page=`. A new `AdminProductResource` now backs
all five product endpoints — the writes previously returned the storefront
shape, whose bare-integer `base_price_ghs` the admin app's `Money` type could
not read. 8 new tests; suite at 280.

### ~~A2. Feature 8 — Notifications~~ — **done 8 Sep**
There is **no `app/Mail/` and no `app/Notifications/` directory at all.** Today
a customer completes payment and receives nothing: no email, no SMS. The
`OrderPaid` event exists (`app/Events/OrderPaid.php`) and already has one
listener (`FinalizeInventoryForPaidOrder`), so the hook is in place — the
listeners just were never written.

**Done.** Five triggers dispatch — order paid, order shipped, booking
submitted, booking confirmed, waitlist promoted — behind a `NotificationChannel`
contract with `MailChannel`, `FishAfricaSmsService` and a `LogSmsChannel`
fallback. Delivery failure is non-fatal per Feature 8's acceptance criteria.
22 new tests, including one unfaked webhook-to-email path and one that renders
all five Blade templates; suite at 302.

**Two of the five I originally listed here were dropped on purpose.** Workshop
reminders and return-resolution notices are stated in neither the README nor
the brand document, and §22 forbids inventing a policy the document does not
state — a reminder's lead time is a business decision. Raise them with the
owner alongside the bucket C items.

**Still open:** the Fish Africa request shape has never been run against the
live API (see B4), so expect to correct it when keys land.

### ~~A3. Server-side catalogue filtering, search and sort~~ — **done 8 Sep**
`ProductController::index` supports only `category_id` and `featured`
(`app/Http/Controllers/Api/V1/ProductController.php:16-27`). The storefront's
`/shop` filters **client-side over one page of 12**
(`frontend/pages/shop/index.vue:86-114`), and its own comment says "the API is
expected to filter server-side". Facets in the URL today: `type`, `color`,
`size`, `width`, plus `q` (free-text) and `sort`
(`newest` / `best-selling` / `top-rated`).

Results are silently wrong the moment the catalogue exceeds 12 products.

**Done.** `ProductFilter` applies `q`, `type`, `color`, `size`, `width`,
`category` (department), `category_id`, `collection_id`, `sale`, `featured` and
`sort`, against the query string the storefront already sends. 25 new tests;
suite at 327.

**`sort=top-rated` could not be built** — it needs ratings, which do not exist
anywhere in the system (open decision 24). It falls through to the default
rather than erroring, and is documented as unsupported.

Portability was verified rather than assumed: the suite was run against a
scratch Postgres database as well as SQLite. That caught a real defect in my
own first attempt (Postgres sorts NULLs first under `DESC`, so unsold products
would have led the best-sellers) and surfaced two pre-existing Postgres-only
test failures — see issue 36 in `FOR_THE_TEAM.md`.

### ~~A4. Customer password reset~~ — **done 8 Sep**
`CustomerAuthController` has register / login / logout / me and **no password
handling beyond login** (grep for `password` returns nothing else). The
groundwork is done — `passwords.customers` is configured and `Customer` is a
proper `Authenticatable` — so this is the standard forgot/reset pair against
the `web` guard, plus the two mailables (which depend on A2).
**Effort:** half a day.

### ~~A5. DIY reference-image upload~~ — **done 8 Sep**
`StoreBookingRequest` types `details.reference_image` as a **string**, so the
booking form records a *filename* and asks the customer to send the actual
photo over WhatsApp. There is a working upload pattern to copy:
`Admin/MediaController::store` (`$file->store('media/Y/m', 'public')`, Laravel
generates the stored name so a hostile original filename cannot escape).

**Done.** `POST /booking-uploads` returns a `path` the booking payload
references. 5MB cap, contents-checked allowlist, a `dimensions` decode so a
renamed script fails, a generated storage name, and 10/hour per IP.
`PruneOrphanedBookingUploads` sweeps unattached files daily — without it an
unauthenticated upload endpoint grows the disk without limit. 15 tests.

⚠️ **`DiyOrderForm.vue` still sends the filename**, so nothing changes for
customers until the form POSTs the file first. Validation stays permissive so
it keeps working meanwhile.

⚠️ **D3 now bites twice.** These files are on container-local disk and will not
survive a Render redeploy.

### A6. Product listing pagination metadata for admin screens
Minor, but bundle it with A1: admin lists want filtering by status and a larger
page size than the storefront's 12.

---

## B. Blocked on a credential — request access today

**13 of 15 third-party values in `backend/.env` are empty.** Paystack and
exchangerate.host are self-serve; Yango, DHL and Fish Africa need a human to
request business access, with lead times measured in days. Start those requests
before you need them.

### B1. `PAYSTACK_SECRET_KEY` — the highest-value single item ⚠️
Everything else for payments is code-complete and tested: `PaystackService`,
`POST /checkout/session`, `POST /webhooks/paystack` with
`processed_webhook_events` idempotency, `GET /orders/{reference}`, and the
`OrderPaid` → inventory-finalisation chain. **Checkout cannot complete end to
end until this key exists**, and it is self-serve test keys — about an hour of
somebody's time.

Also needed: point the Paystack dashboard webhook at
`POST {API}/api/v1/webhooks/paystack`, and set `STOREFRONT_URL` (where Paystack
returns the customer) to the storefront origin, not the API's.

One matching storefront fix travels with this: `PaymentStep.vue` still branches
on currency to choose between a redirect and a Stripe client secret. Since 28
Aug both currencies return an `authorization_url` and `client_secret` is always
null — redirect whenever `authorization_url` is present.

### B2. `EXCHANGERATE_HOST_KEY`
`RefreshFxRate` is written and scheduled hourly (`routes/console.php:16`), and
FX staleness handling is in place. Without the key the rate never moves off its
seeded value, so every USD price on the site is a fiction. Self-serve free tier.

### B3. Yango + DHL — Feature 5 is a static rate table, not a live quote
`YangoService` and `DhlService` implement the `DeliveryProvider` contract and
`DeliveryProviderFactory` routes on destination country (`GH` → Yango, else
DHL), and `CheckoutSessionService` already calls all of it. But the rates are
**hardcoded constants**: Yango ₵25 standard / ₵50 express with free standard
delivery above ₵1,500; DHL ₵350 / ₵600 flat.

The README permits this fallback until credentials exist, and the numbers were
chosen to match what the storefront already promises customers. When keys land,
**only the body of `quote()` changes** — the architecture absorbs it. Still
missing entirely: booking a shipment (as opposed to quoting one) and tracking
numbers flowing back to `GET /admin/shipments`.

### B4. Fish Africa SMS (`FISH_AFRICA_APP_ID` / `_SECRET`)
The SMS half of A2, **now the only part of it still outstanding.** The channel
is written and wired; without credentials it falls back to `LogSmsChannel`,
which logs each message instead of sending it. The request shape has never been
run against the live API, so expect to correct the token exchange and send
payload when keys arrive. README "Clarifications Needed" #3 flags that **Ghana network
delivery rates are unverified** — run a sandbox test before committing to SMS
as the primary channel for order confirmations.

### B5. A real mail transport
`MAIL_MAILER=log` in `.env.example`, and `sync: false` in `render.yaml` — so
production has no mail provider chosen yet. A2 is untestable end to end without
one.

---

## C. Blocked on a human decision — these are conversations, not tickets

These are lifted from the open-decisions table in `FOR_THE_TEAM.md` and are
listed here because each one blocks backend work that otherwise looks buildable.

| # | Decision needed | What it blocks |
|---|---|---|
| 33 | **Stripe: wanted or not?** README pairs Stripe with USD; `GOLD_COAST_TOKOTA.md` §13 names Paystack alone. Currently Paystack for both currencies; the Stripe config binding is retained but unrouted. | Whether `PaymentGatewayFactory` regains a currency split. One sentence from the owner closes it. |
| 34 | **Five email addresses.** §17 names Samuel Kumi-Gyau, Mary Seade, Isaac, Isaaka and Peter with roles, titles and tiers — everything a seeder needs except the field an account is keyed on. | The real team roster is unseeded; the only account on a fresh database is the test super admin. Inventing addresses for real colleagues is not a seeder's call. |
| 35 | **Three of six workshop experiences cannot be booked.** §15 runs Corporate Team Building, Cultural Craft and International Visitor "By Appointment" — no standing schedule, so no `workshop_session` for a booking to attach to. | An enquiry path. Whether it is a booking-without-session or a routed form is a business question. Half the published programme currently dead-ends at a WhatsApp link. |
| 28 | **Five admin endpoints with no data model:** inbox (×3), activity feed, audit log. In neither the README nor the brand document. The inbox could be WhatsApp history, a ticketing system or email — each yields a different schema; the audit log's retention and scope carry compliance weight. | Five endpoints. They fall back to fixtures with a demo-data chip, so nothing is visibly broken — but this is unbudgeted scope, and better decided than discovered on a launch checklist. |
| 24 | **Product reviews.** `ProductReviews.vue` is fully built — sort, star filter, rating distribution, fit meter — and **no README feature covers reviews at all.** Who writes them, are they moderated, does launch ship seeded ones? | A `product_reviews` table. Cheapest honest option if deferred: delete the section rather than leave it fixture-fed. |
| — | **WhatsApp Cloud API + webhook receiver**, if the admin inbox is to go live. | New scope, not configuration. Related to #28. |

---

## D. Production-readiness and cleanup

### D1. Production serves the API with `php artisan serve` ⚠️ (issue 30)
`backend/Dockerfile`'s CMD is Laravel's built-in **dev** server: single-threaded,
and explicitly not for production in Laravel's own documentation. It will carry
a demo and fall over under real traffic.

Fix: FrankenPHP is the smallest change (one base-image swap plus a Caddyfile);
php-fpm + nginx is the conventional one. Deliberately left out of the hardening
pass because it cannot be verified without an actual deploy. **Needs one deploy
to test** — do it well before launch day, not on it.

### D2. Tests run on SQLite, production is Postgres (issue 29) — *partly addressed*
`phpunit.xml` sets `DB_CONNECTION=sqlite`. Every `jsonb` column is plain JSON
under test, and Postgres-only SQL (`ILIKE`, JSON operators) passes or fails
differently across the two. **This has already bitten once** (27 Aug
admin-operations entry), and again on 8 Sep during A3 — where running the suite
against Postgres caught a NULL-ordering defect before it shipped and surfaced
two pre-existing Postgres-only test failures (issue 36). Fix: run tests against
Postgres in CI. Note that issue 36(a) has to be resolved first, or a Postgres
CI job starts red.

### D3. Uploaded media is on local disk, not object storage
`MediaController` writes to the `public` disk and the Dockerfile runs
`storage:link` on every boot. On Render, **container-local disk does not
survive a redeploy** — every uploaded product image disappears on the next
deploy. `AWS_*` variables exist in `.env.example` but are empty and unused.
Fix: switch `FILESYSTEM_DISK` to S3 (or Render persistent disk) before anyone
uploads media they care about. Blocks A5 too.

### D4. `SiteSetting.announcements` has no admin editor
It is a JSON array on the API and the storefront renders it, but the admin app
has no field for it — so it can only be changed by re-seeding. This is where
the brand's delivery and payment claims live, which makes "re-seed the database
to fix a typo" an uncomfortable position.

### D5. Leftover `users` table migration
`0001_01_01_000000_create_users_table.php` still ships, though the generic
Laravel `User` model was removed during scaffolding — the spec has two identity
models (`Customer` on `web`, `AdminUser` on `admin`) and no use for a third.
Harmless, but it creates a table nothing reads and invites a future
`auth:sanctum` mistake. Low priority.

### D6. Feature 11 — server-side analytics mirroring
`GA4_MEASUREMENT_ID` / `GA4_API_SECRET` are in `.env.example`; nothing on the
backend reads them. Server-side event mirroring for purchases is what makes
conversion tracking survive ad-blockers.

---

## Suggested order

1. ~~**A1**~~ — **done 8 Sep.**
2. **B1, B2** — self-serve keys, and B1 is what turns checkout from tested to
   actually working. Simultaneously: **start the Yango / DHL / Fish Africa
   access requests** so their lead time runs in the background.
3. ~~**A2**~~ — **done 8 Sep.**
4. ~~**A3**~~ — **done 8 Sep.**
5. **D1, D3** — both need a deploy to verify; do not leave them to launch week.
6. ~~**A4, A5**~~ — **done 8 Sep.** What they still need is frontend: reset
   pages, and `DiyOrderForm.vue` posting the file.
7. **C** — put all six decisions in front of the owner in one conversation
   rather than one at a time; several are a single sentence each.
8. **B3 (live delivery quotes), B4 (SMS), D2, D4, D6** — as credentials and
   time allow.

---

*Cross-references: `FOR_THE_TEAM.md` "What is left to do" and its open-decisions
table are the running source; `GOLD_COAST_TOKOTA.md` §13/§15/§17/§18 settle the
business rules behind C. Update `FOR_THE_TEAM.md` in the same change that
closes any item here.*