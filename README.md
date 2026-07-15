# Project Overview

Gold Coast Tokota is a multi-currency (GHS/USD) e-commerce and digital storefront platform for a Ghana-based sandals/accessories brand. The platform supports product sales, custom order bookings, workshop bookings, content publishing (blog/brand story), delivery integration (local via Yango, international via DHL), and an internal admin dashboard for operations, content management, and analytics.

The system is built as a **Nuxt 3 (Vue 3) frontend** with hybrid rendering, consuming a **Laravel 11 REST API** backend, persisted to **PostgreSQL**, hosted on **Render**. The engagement is scoped as an 8-week delivery (Jul 01 - Aug 31, 2026) across 6 phases: Requirements & Planning, Design, Core Development (4 sub-phases), Testing & QA, and Deployment & Handover.

This document translates the plain-language delivery plan into an unambiguous technical specification for an AI coding agent to implement sequentially, phase by phase. All open questions from the original plan have been resolved through stakeholder discussion; decisions are reflected throughout and summarized in **Clarifications Needed** (only limited items remain open).

> Everything below this point is the full product/technical spec. If you just
> want to run the app locally, see **Getting Started** immediately below —
> come back to the rest as reference while you build.

# Getting Started

There are three apps in this repo (see Implementation Deviations for why
it's three, not two): `backend/` (Laravel API), `frontend/` (storefront,
Nuxt), `admin/` (dashboard, Nuxt). You generally need `backend/` running for
the other two to show real data — both degrade gracefully (empty states,
not crashes) if it's not running, so pure UI work doesn't strictly require
it, but anything touching real content/products/bookings does.

**Prerequisites:**
- Node.js 20+ and npm
- PHP 8.2+ and Composer
- PostgreSQL 16 (running locally, or point at a remote instance)

**1. Backend (Laravel API) — do this first:**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
createdb gold_coast_tokota          # skip if the database already exists
# edit .env: set DB_USERNAME/DB_PASSWORD to match your local Postgres role
php artisan migrate --seed          # creates tables + a test admin user,
                                     # default site settings, and an About page
php artisan serve                   # runs on http://localhost:8000
```

**2. Storefront (Nuxt), in a second terminal:**
```bash
cd frontend
npm install
npm run dev                         # runs on http://localhost:3000
```

**3. Admin dashboard (Nuxt), in a third terminal:**
```bash
cd admin
npm install
npm run dev                         # runs on http://localhost:3001
```

Then open `http://localhost:3000` for the storefront or
`http://localhost:3001` for the admin dashboard. The seeded admin user is
`admin@goldcoasttokota.store` (see `backend/database/seeders/DatabaseSeeder.php`
— note there's no password login flow wired up yet, that's still to be
built as part of Feature 9).

Each app's `.env.example` documents the environment variables it reads;
copy it to `.env` in `frontend/` and `admin/` too if you need to point at
something other than `http://localhost:8000/api/v1` (e.g. a shared staging
API). Full command reference (migrations, seeding, per-app dev ports) is
also in `CLAUDE.md`.

To view the database visually, connect a Postgres GUI (TablePlus, DBeaver,
etc.) to `127.0.0.1:5432`, database `gold_coast_tokota`, using your local
Postgres role — no password needed for a default local install.

# Goals

- Launch a fully functional, secure, mobile-responsive, SEO-optimized e-commerce storefront at `goldcoasttokota.store`.
- Support dual-currency commerce (GHS for local Ghana customers, USD for international/diaspora customers, derived via live/periodic FX rate) with a single checkout flow.
- Provide inventory tracking (via polling) synchronized across storefront, checkout, and admin dashboard, with server-side correctness guarantees regardless of UI refresh timing.
- Integrate two independent delivery providers (Yango for domestic, DHL for international) selected conditionally based on customer shipping destination.
- Support two custom booking flows in addition to standard product checkout: (1) capacity-limited, waitlist-enabled in-person workshop bookings, (2) unlimited/queue-based DIY custom sandal order bookings.
- Provide a fully native, admin-editable CMS -- no third-party headless CMS -- covering blog/brand story, About page, and site-wide settings, so the business owner never depends on a developer for routine content changes.
- Provide a role-gated (Admin/Staff) admin dashboard exposing orders, inventory, bookings, content, traffic, and key business metrics in real time.
- Preserve WhatsApp as a first-class, site-wide ordering/contact channel alongside the formal checkout.
- Prioritize SEO via server-side rendering (Nuxt 3) for all public, crawlable content.
- Deliver expressive, brand-appropriate motion design (GSAP) without compromising SSR/crawlability.
- Meet baseline (best-effort) accessibility, analytics, security (SSL), performance, and cross-device compatibility requirements prior to launch.
- Deliver full documentation, credential handover, and a 30-day post-launch support window, with an admin experience simple enough for a non-technical owner to operate independently.

# Technical Requirements

| Layer | Technology | Notes |
|---|---|---|
| Frontend framework | **Nuxt 3** (Vue 3, Composition API) | Hybrid rendering: SSR for public/SEO-critical routes, SPA mode for Checkout/Cart and Admin |
| Styling | Tailwind CSS | Utility-first, mobile-first |
| Animation | **GSAP + ScrollTrigger** | Client-only; must not execute during SSR pass |
| State management | Pinia (`@pinia/nuxt`) | Compatible with Nuxt SSR |
| Backend framework | Laravel 11 | API-only mode (no Blade views for storefront) |
| API layer | Laravel REST API | JSON:API-style responses, versioned (`/api/v1/...`) |
| Database | **PostgreSQL** (Render Managed Postgres) | Switched from MySQL; UTF8 |
| Auth | Laravel Sanctum | SPA token/cookie-based auth for customers and admin; two-tier roles (Admin/Staff) |
| Payments | Paystack (GHS) + Stripe (USD) | Dual gateway, currency-routed |
| Currency conversion | Live/periodic FX rate service | USD auto-derived from GHS base price; provider TBD (see Clarifications Needed) |
| Delivery | Yango API (domestic), DHL API (international) | Routed by shipping country |
| Messaging | WhatsApp Business deep link | `https://wa.me/<number>` site-wide |
| SMS | **Fish Africa** (`api.letsfish.africa`) | CPaaS; App ID/App Secret Bearer auth; order and booking confirmations |
| Admin UI | Separate Nuxt 3 app (SPA-only) + Laravel API, role-gated routes | **Deviation:** built as its own deployable (`admin/`) at `admin.goldcoasttokota.store`, not `/admin/**` pages inside the storefront app — see Implementation Deviations. Native CMS included either way. |
| SEO | Nuxt 3 SSR + `useSeoMeta`/`useHead` + `@nuxtjs/sitemap` | Full server-rendered HTML for crawlers |
| Hosting | **Render**, region **Frankfurt** | Three separate Web Services: Laravel API + Nuxt SSR storefront + Nuxt SPA admin dashboard |
| SSL | Render built-in (auto Let's Encrypt) | No manual Certbot/nginx configuration required |
| Analytics | Google Analytics 4 + custom admin metrics | GA4 via gtag.js, key events mirrored server-side into Postgres for dashboard |
| Notifications | Transactional email (SMTP/Mailgun/SES) + Fish Africa SMS | Order and booking confirmations |
| Inventory sync | Simple polling (15-30s interval) | No WebSockets/broadcast; correctness enforced server-side regardless |
| Dashboard metrics | Real-time direct queries | No pre-aggregation/caching layer initially; documented upgrade path if volume grows |

# Architecture

**Pattern:** Decoupled application -- two independent Nuxt 3 apps (storefront + admin dashboard) + stateless Laravel REST API + PostgreSQL, deployed as three independent Render Web Services. **Deviation from the original plan:** the admin dashboard was split into its own Nuxt app/deployment rather than living at `/admin/**` inside the storefront app — see Implementation Deviations.

- **Storefront (Nuxt 3, `frontend/`):**
  - **SSR routes** (server-rendered per request, SEO-critical): Home, Shop, Product Detail, About, Blog/Stories. These must render fully in HTML before any client-side JS (including GSAP) executes.
  - **SPA-mode routes** (client-rendered only, not indexed): Checkout/Cart, Order Confirmation. Configured via Nuxt's per-route rendering rules (`routeRules` in `nuxt.config.ts`).
  - Communicates exclusively via authenticated/unauthenticated HTTPS calls to the Laravel API, using Nuxt's `useFetch`/`useAsyncData` composables (server-side fetch on initial SSR load, client-side fetch on subsequent navigation).
- **Admin Dashboard (Nuxt 3, `admin/`):** A fully separate, SPA-only Nuxt app (no SSR anywhere — nothing here is SEO-relevant, and every route sits behind Sanctum-authenticated Admin/Staff login). Deployed independently at `admin.goldcoasttokota.store`, calling the same Laravel API as the storefront. Shares no runtime code with `frontend/`, only the same design tokens/conventions.
- **Backend (Laravel 12 API):** Stateless REST API (Sanctum SPA auth using cookie + CSRF token, trusting both first-party frontend origins) exposing resources for products, orders, inventory, bookings (including workshop session capacity/waitlist), blog posts, About/Page content, site settings, newsletter subscriptions, feedback, delivery quotes/booking, payment session creation/webhooks, FX rate lookups, SMS/email dispatch, and admin analytics.
- **Database (PostgreSQL):** Single relational database. All monetary values stored as integer minor units (e.g., pesewas/cents) with an explicit `currency` column to avoid floating-point errors.
- **Third-party integrations (server-side only, called from Laravel):**
  - Paystack (GHS transactions) and Stripe (USD transactions) -- payment initialization + webhook verification.
  - FX rate provider -- periodic job fetches and caches the GHS-to-USD rate (see Clarifications Needed for provider selection).
  - Yango API/link -- domestic delivery quote/booking.
  - DHL API/link -- international shipment creation/tracking.
  - Fish Africa -- SMS dispatch for order/booking confirmations, called from queued Laravel jobs behind a swappable `NotificationService` interface.
  - WhatsApp -- static deep link, no API integration required.
  - Google Analytics 4 -- client-side tracking snippet plus server-side event mirroring into Postgres for dashboard independence from ad-blockers.

**Request flow example (Checkout):**
1. Frontend builds cart (client-rendered Cart/Checkout route), requests currency-appropriate checkout session from `/api/v1/checkout/session`.
2. Backend resolves the live FX rate if currency is USD, locks inventory (soft reservation), creates a `Paystack` or `Stripe` payment intent at the **locked** rate, returns client secret / authorization URL.
3. Frontend redirects to/renders payment gateway UI.
4. Gateway calls backend webhook (`/api/v1/webhooks/paystack` or `/api/v1/webhooks/stripe`) on completion.
5. Backend verifies signature, finalizes order, decrements inventory, creates delivery booking (Yango or DHL based on shipping address country), triggers confirmation email + Fish Africa SMS.
6. Frontend polls or is redirected to an order-confirmation route showing final status.

**Request flow example (Public SSR page -- e.g., Product Detail):**
1. Crawler or user requests `/shop/some-sandal`.
2. Render's Nuxt Web Service executes the SSR render: `useAsyncData` fetches product data server-side from the Laravel API, injects it into fully-formed HTML, including `useSeoMeta` tags.
3. HTML is sent to the client; Nuxt hydrates client-side.
4. Post-hydration, GSAP/ScrollTrigger initializes (inside `onMounted`/`<ClientOnly>`) to animate the already-visible content -- it never blocks or alters the initial SSR HTML.

# Folder Structure

**Deviation from the original plan:** `admin/` is a third top-level app (its
own Nuxt project, `package.json`, and Render deployment), not
`frontend/pages/admin/**`. See Implementation Deviations for why.

```
gold-coast-tokota/
├── frontend/                          # Nuxt 3 storefront application
│   ├── public/
│   ├── assets/
│   ├── components/
│   │   ├── common/                    # Buttons, Modals, Loaders, CurrencyToggle
│   │   ├── layout/                    # Header, Footer, NavBar, WhatsAppButton
│   │   ├── shop/                      # ProductCard, ProductGrid, ProductFilter
│   │   ├── checkout/                  # CartSummary, CheckoutForm, PaymentStep
│   │   ├── booking/                   # WorkshopBookingForm, DiyOrderForm, BookingCalendar, WaitlistBanner
│   │   ├── blog/                      # BlogCard, BlogList, BlogPost
│   │   ├── forms/                     # NewsletterForm, FeedbackForm
│   │   └── motion/                    # GsapScrollReveal, GsapHeroIntro (client-only wrappers)
│   ├── pages/                         # File-based routing: index.vue, about.vue, shop/index.vue,
│   │                                  #   shop/[slug].vue, booking/index.vue, blog/index.vue,
│   │                                  #   blog/[slug].vue, checkout.vue, order-confirmation/[id].vue
│   ├── layouts/                       # default.vue (storefront shell)
│   ├── stores/                        # Pinia stores (cart, auth, currency, catalog, bookings)
│   ├── composables/                   # useCurrency, useCart, useAuth, useAnalytics, useInventoryPolling
│   ├── utils/                         # formatters, validators, constants
│   ├── nuxt.config.ts                 # routeRules for SSR vs SPA split, GSAP client-only config
│   ├── tailwind.config.ts
│   └── package.json
│
├── admin/                              # Nuxt 3 admin dashboard — separate app, SPA-only (no SSR
│   │                                   #   anywhere), deployed at admin.goldcoasttokota.store
│   ├── components/                    # Sidebar, MetricCard, DataTable, OrdersTable, InventoryTable,
│   │                                  #   PageEditor (About/CMS), SiteSettingsForm, WorkshopSessionManager,
│   │                                  #   FormField, StatusBadge
│   ├── pages/                         # index.vue (dashboard), login.vue, orders.vue, inventory.vue,
│   │                                  #   bookings.vue, blog.vue, about.vue, settings.vue (admin-only),
│   │                                  #   products.vue (admin-only), newsletter.vue
│   ├── layouts/                       # default.vue (sidebar shell)
│   ├── stores/                        # auth (role: admin|staff)
│   ├── composables/                   # useAuth
│   ├── utils/                         # formatters
│   ├── nuxt.config.ts                 # routeRules: '/**' ssr:false
│   ├── tailwind.config.ts
│   └── package.json
│
├── backend/                            # Laravel 12 API (see Implementation Deviations)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/     # ProductController, OrderController, BookingController,
│   │   │   │                           #   CheckoutController, WebhookController, AdminController,
│   │   │   │                           #   PageController (About/CMS), SiteSettingController,
│   │   │   │                           #   WorkshopSessionController, FxRateController
│   │   │   ├── Middleware/             # EnsureAdminRole, EnsureStaffOrAdminRole
│   │   │   ├── Requests/               # Form Request validation classes per endpoint
│   │   │   └── Resources/              # API Resource transformers (JSON shaping)
│   │   ├── Models/                     # Product, Order, OrderItem, Booking, WorkshopSession, Customer,
│   │   │                               #   BlogPost, Page, SiteSetting, InventoryItem,
│   │   │                               #   NewsletterSubscriber, FeedbackEntry, AdminUser
│   │   ├── Services/
│   │   │   ├── Payments/               # PaystackService, StripeService, PaymentGatewayInterface
│   │   │   ├── Delivery/               # YangoService, DhlService, DeliveryProviderInterface
│   │   │   ├── Notifications/          # OrderConfirmationMailer, FishAfricaSmsService, NotificationServiceInterface
│   │   │   ├── Currency/               # FxRateService (fetch/cache live rate)
│   │   │   └── Analytics/              # MetricsAggregatorService
│   │   ├── Jobs/                       # SendOrderConfirmation, RefreshFxRate, ReleaseExpiredReservations,
│   │   │                               #   PromoteWaitlistedBooking, PushGaEvent
│   │   ├── Events/ & Listeners/         # OrderPlaced -> DecrementInventory, NotifyCustomer
│   │   └── Providers/
│   ├── database/
│   │   ├── migrations/                 # PostgreSQL-compatible migrations
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/
│   │   └── api.php                     # /api/v1/* routes
│   ├── config/
│   └── tests/
│       ├── Feature/
│       └── Unit/
│
├── docs/                                # Handover documentation, user guides, admin training materials
├── render.yaml                          # Render Blueprint: 3 Web Services + Managed Postgres
└── README.md
```

# Components

Reusable, cross-feature Nuxt/Vue components:

- `CurrencyToggle` -- global GHS/USD switch; persists selection via Pinia + cookie (SSR-safe; never `localStorage` directly, since SSR has no browser storage on first render).
- `WhatsAppButton` -- floating/site-wide CTA rendering the WhatsApp deep link; configurable position (fixed corner) and visibility per breakpoint; storefront-only (the admin app, being separate, never includes it at all).
- `ProductCard` / `ProductGrid` -- used on Home (featured), Shop (full catalogue), and search/filter results.
- `PriceDisplay` -- renders price in active currency; for USD, applies the locked/cached FX rate; formatting rules per locale (GHS vs USD symbol/decimal conventions).
- `CartSummary` -- used in mini-cart dropdown and full checkout page (SPA-mode).
- `BookingCalendar` -- shared date/slot picker for both Workshop bookings (respects `WorkshopSession.capacity`) and DIY order scheduling (no capacity constraint, shows estimated turnaround instead).
- `WaitlistBanner` -- shown on `BookingCalendar` when a workshop session is at capacity, offering waitlist signup instead of blocking the form entirely.
- `FormField` (text/select/date/file) -- base input wrapper with validation error slot, used across Newsletter, Feedback, Booking, and Checkout forms.
- `MetricCard` -- admin dashboard KPI tile (orders today, revenue, traffic, low-stock alerts), sourced from real-time direct queries.
- `DataTable` -- generic sortable/paginated table used for Orders, Inventory, Bookings, Newsletter/Feedback in admin.
- `PageEditor` -- rich-text (WYSIWYG) editor component used for both the About page and Blog posts, reused across both CMS resources.
- `StatusBadge` -- order/booking status indicator (Pending, Paid, Fulfilled, Cancelled, Waitlisted, etc.).
- `GsapScrollReveal` -- client-only wrapper (`<ClientOnly>`) applying GSAP ScrollTrigger reveal animations to slotted content; used on Home/About brand-story sections.
- `Toast` / `Modal` / `Skeleton Loader` -- generic UI feedback primitives.

# Data Models

> All monetary fields stored as integers in minor currency units. All timestamps UTC. PostgreSQL as the target RDBMS (use `jsonb` for JSON columns, `serial`/`bigserial` or UUID for primary keys per team convention).

**Product**
- id, name, slug, description, category_id, base_price_ghs, sku, images[], is_active, is_featured, created_at, updated_at
- *(Note: no `base_price_usd` column -- USD is computed at read-time from `base_price_ghs` times cached FX rate; see FxRate model below.)*

**FxRate**
- id, base_currency (GHS), quote_currency (USD), rate, fetched_at, source
- Latest row is used for live display; the rate applied at `Order` creation is snapshotted onto the order itself (see below) so historical orders are immune to later rate changes.

**InventoryItem**
- id, product_id (FK), variant_attributes (jsonb: size/color), quantity_available, quantity_reserved, reservation_expires_at, low_stock_threshold, updated_at

**Customer**
- id, name, email, phone, preferred_currency, password_hash (nullable -- guest checkout supported), created_at

**Order**
- id, customer_id (nullable FK for guest), currency (GHS|USD), fx_rate_applied (nullable -- snapshotted at checkout for USD orders), subtotal, shipping_cost, tax, total, status (pending|paid|processing|shipped|delivered|cancelled|refunded|inventory_conflict), payment_gateway (paystack|stripe), payment_reference, delivery_provider (yango|dhl), delivery_reference, shipping_address (jsonb), created_at, updated_at

**OrderItem**
- id, order_id (FK), product_id (FK), inventory_item_id (FK), quantity, unit_price, currency

**WorkshopSession**
- id, scheduled_date, scheduled_slot, capacity, location_notes, created_by_admin_id (FK), created_at
- Owner-creatable/editable in admin; distinct sessions can have different capacities.

**Booking** (polymorphic: workshop | diy_order)
- id, type (workshop|diy_order), customer_id (nullable FK), workshop_session_id (nullable FK -- only for type=workshop), scheduled_date (for diy_order), details (jsonb -- differs per type: workshop = attendee_count; diy_order = sandal specs/measurements/reference images, preferred pickup/delivery), status (pending|confirmed|waitlisted|completed|cancelled), created_at

**BlogPost**
- id, title, slug, body (rich text/HTML from `PageEditor`), cover_image, author, published_at, is_published

**Page** (generic CMS resource -- used for About page; extensible to other static marketing pages)
- id, slug (e.g., `about`), title, body (rich text/HTML from `PageEditor`), updated_at, updated_by_admin_id (FK)

**SiteSetting** (single-row or key/value CMS resource for owner-editable global config)
- id, whatsapp_number, whatsapp_default_message, contact_email, contact_phone, instagram_url, hero_headline, hero_image, diy_turnaround_estimate, updated_at

**NewsletterSubscriber**
- id, email, subscribed_at, source
- *(No `is_confirmed` column -- single opt-in means subscription is immediate/active on insert.)*

**FeedbackEntry**
- id, name, email, rating (nullable), message, submitted_at

**AdminUser**
- id, name, email, password_hash, role (**admin|staff**), created_at
- `admin`: full access including product deletion, pricing, refunds, site settings, FX/payment config.
- `staff`: operational access only -- orders, inventory adjustments, bookings, content -- no destructive/financial-config actions.

**AnalyticsEventSnapshot** (optional materialized rollups; not required at launch given real-time-query decision, retained as a documented upgrade path)
- id, metric_key, metric_value, period_start, period_end

# User Flow

1. **Visitor** lands on Home (SSR) -> GSAP ScrollTrigger reveals brand-story hero as they scroll -> browses Shop or About (both SSR, both crawlable).
2. **Shop -> Product Detail** (SSR) -> select variant/size -> Add to Cart (currency already selected via `CurrencyToggle`; USD prices reflect the current cached FX rate).
3. **Cart -> Checkout** (SPA mode, not indexed) -> enters shipping address -> system determines delivery provider (Ghana address -> Yango; non-Ghana -> DHL) -> displays computed shipping cost -> FX rate is locked for USD orders at session creation -> selects/confirms payment (Paystack for GHS, Stripe for USD) -> completes payment -> redirected to Order Confirmation (SPA mode).
4. **Alternative flow -- Workshop Booking:** Visitor navigates to Booking page -> selects Workshop tab -> `BookingCalendar` shows available `WorkshopSession`s with remaining capacity; if a session is full, `WaitlistBanner` offers waitlist signup instead -> submits -> receives booking confirmation email + SMS (Fish Africa).
5. **Alternative flow -- DIY Sandals Booking:** Visitor selects DIY tab -> submits sandal specs/measurements + reference images, no date/capacity constraint, sees current estimated turnaround time (owner-editable in admin) -> receives confirmation email + SMS.
6. **Content flow:** Visitor reads Blog/Stories (SSR), may subscribe via Newsletter form (single opt-in, immediate) or submit Customer Feedback form independently of purchase.
7. **Support flow:** At any point, visitor may click floating WhatsApp button to contact the brand directly, bypassing formal checkout.
8. **Admin flow:** Authenticated Admin/Staff logs in at the separate admin app (`admin.goldcoasttokota.store`, SPA-only) -> views Dashboard (real-time orders, traffic, KPIs) -> manages Orders/Inventory/Bookings/WorkshopSessions/Blog/About-page/Site-Settings per role permissions -> reviews Analytics.

---

# Feature Specifications

## Feature 1: Site Architecture & Core Pages (Home, About, Shop shell)

**Purpose:** Establish the foundational Nuxt 3 hybrid-rendering structure and brand-aligned, SEO-critical marketing pages.

**Implementation Details:**
- Configure Nuxt file-based routing under `pages/`: `index.vue` (`/`), `about.vue`, `shop/index.vue`, `shop/[slug].vue`, `booking/index.vue`, `blog/index.vue`, `blog/[slug].vue`, `checkout.vue`, `order-confirmation/[id].vue`. (Admin dashboard routes live in the separate `admin/` app — see Implementation Deviations.)
- Set `routeRules` in `nuxt.config.ts`: SSR (default/`{ ssr: true }`) for `/`, `/about`, `/shop/**`, `/blog/**`; `{ ssr: false }` for `/checkout`, `/order-confirmation/**`.
- Home page: hero section (brand story intro, GSAP scroll-reveal via `GsapScrollReveal`), featured products grid (pulls top N `is_featured` products via `GET /api/v1/products?featured=true`, fetched server-side via `useAsyncData`), CTA to Shop and Booking.
- About page: fully CMS-editable, fetched from `GET /api/v1/pages/about` (native CMS `Page` resource -- see Feature 9), rendered server-side.
- Shop page shell: product grid with pagination, category filter, and currency-aware `PriceDisplay`.
- Global layout (`layouts/default.vue`): `Header` (nav + `CurrencyToggle`), `Footer` (contact info from `SiteSetting`, social links, WhatsApp), persistent `WhatsAppButton`. Admin (separate app) has its own `layouts/default.vue` sidebar shell.

**Acceptance Criteria:**
- All core SSR routes render fully-formed HTML (verifiable via "view source", not just DevTools-rendered DOM) on desktop and mobile breakpoints.
- Home hero and Shop grid load real product/content data from the API, not placeholder/mock data, once Feature 2/9 backends are available.
- Currency toggle updates displayed prices across all visible product cards without a full page reload.
- Navigation is keyboard-accessible and all interactive elements have visible focus states.
- GSAP animations run only after client hydration and never throw SSR errors (`window`/`document` never referenced during server render).

**Dependencies:** Backend product endpoints (Feature 2), Page/CMS endpoints (Feature 9), Tailwind config, Nuxt routing/`routeRules` setup.

**Edge Cases:**
- Empty product catalogue -> Shop page renders an explicit empty-state message, not a blank grid.
- Slow network -> skeleton loaders shown for hero/grid instead of layout shift.
- Currency toggle interaction before catalog data loads -> toggle state persists and applies once data arrives.
- GSAP/ScrollTrigger must degrade gracefully (content still fully visible/readable) if the animation script fails to load -- never hide content behind a broken animation state.

---

## Feature 2: Product Catalogue & Multi-Currency Pricing (Live FX Rate)

**Purpose:** Provide backend-managed product data with GHS base pricing and live-derived USD pricing.

**Implementation Details:**
- Laravel `Product`, `InventoryItem`, and `FxRate` models/migrations per Data Models section.
- API endpoints: `GET /api/v1/products`, `GET /api/v1/products/{slug}`, `GET /api/v1/categories`, `GET /api/v1/fx-rate` (current cached GHS-to-USD rate).
- `FxRateService` fetches the live rate from an external FX provider on a scheduled cadence (Laravel scheduler job `RefreshFxRate`); caches the latest rate in the `FxRate` table. Provider selection and refresh interval are open -- see Clarifications Needed.
- USD prices are computed at API-response time: `base_price_ghs` times latest `FxRate.rate`, never stored as a separate static field.
- At checkout, the rate is **locked/snapshotted** onto the `Order.fx_rate_applied` field at session-creation time, so the amount charged always matches what the customer saw, immune to later rate fluctuations.
- Admin-only endpoints for CRUD: `POST/PUT/DELETE /api/v1/admin/products`.
- Image handling: store product images via Laravel filesystem (Render persistent disk or S3-compatible object storage), return absolute URLs in API Resource.

**Acceptance Criteria:**
- Product list and detail endpoints return correctly formatted GHS price and live-derived USD price.
- USD prices update automatically across the storefront when the underlying FX rate refreshes (subject to short client-side cache TTL).
- A completed order's `fx_rate_applied` never changes retroactively, even if the live rate changes afterward.
- Admin can create/update/deactivate a product and see it reflected in the storefront within one request cycle.
- Inactive (`is_active = false`) products are excluded from public endpoints but visible in admin.

**Dependencies:** PostgreSQL schema, Laravel Sanctum (for admin-only routes), file storage config, FX rate provider credentials (pending -- Clarifications Needed).

**Edge Cases:**
- FX provider outage at read-time -> serve the last successfully cached rate rather than failing the product endpoint; flag staleness if the cached rate exceeds a defined age threshold.
- Product with zero inventory across all variants still displays but with an "Out of Stock" state and disabled Add-to-Cart.
- Missing image -> fallback placeholder image rendered client-side.
- Concurrent admin edits -- last-write-wins is acceptable for V1 (no optimistic locking required).

---

## Feature 3: Inventory Tracking (Polling-Based Freshness, Server-Enforced Correctness)

**Purpose:** Maintain accurate stock levels across storefront and admin, with UI freshness via polling and correctness enforced entirely server-side.

**Implementation Details:**
- `InventoryItem.quantity_available` decremented only on confirmed payment (post-webhook), never on Add-to-Cart.
- Implement a **soft reservation** mechanism: on checkout session creation, temporarily increment `quantity_reserved` with `reservation_expires_at` (e.g., now + 15 minutes) to prevent overselling during payment; release reservation via scheduled job (`ReleaseExpiredReservations`) if payment session expires/fails.
- Use database transactions with row-level locking (`SELECT ... FOR UPDATE`, PostgreSQL-compatible) when decrementing/reserving stock to avoid race conditions under concurrent checkouts.
- Admin Inventory view: `GET /api/v1/admin/inventory` with low-stock filter (`quantity_available <= low_stock_threshold`).
- **Frontend polling:** `useInventoryPolling` composable re-fetches stock status for the currently viewed product every 15-30 seconds while the Product Detail page is open, updating Add-to-Cart availability client-side. No WebSockets/broadcast layer.

**Acceptance Criteria:**
- Two simultaneous checkout attempts for the last unit of a variant result in exactly one successful order; the second is rejected with a clear "out of stock" error before payment is charged -- this correctness holds regardless of polling interval or UI staleness.
- Admin dashboard low-stock alert appears when `quantity_available` crosses the configured threshold.
- Expired reservations are released automatically (via scheduled job) without manual intervention.
- A product's displayed stock status on the storefront refreshes within the polling interval without requiring a manual page reload.

**Dependencies:** Feature 2 (Product/Inventory models), Laravel task scheduling (`schedule:run` cron on Render), Feature 4 (checkout).

**Edge Cases:**
- Payment succeeds after reservation TTL has already expired and stock was reallocated elsewhere -> order status set to `inventory_conflict`, flagged in admin Order detail for manual review rather than silent oversell.
- Bulk admin stock adjustment while reservations are active must not overwrite reserved quantities incorrectly.
- Polling should pause/stop when the tab is backgrounded (Page Visibility API) to avoid unnecessary load.

---

## Feature 4: Checkout & Multi-Currency Payment Processing

**Purpose:** Provide a secure, single checkout flow supporting both GHS (Paystack) and USD (Stripe) transactions, rendered in Nuxt SPA mode.

**Implementation Details:**
- Checkout, Cart, and Order Confirmation pages run with `ssr: false` (per Feature 1 `routeRules`) -- no SEO requirement here, simpler client-only state handling.
- `POST /api/v1/checkout/session` -- accepts cart contents, currency, and shipping address; resolves and snapshots the live FX rate for USD orders; returns a gateway-specific session (Paystack authorization URL or Stripe PaymentIntent client secret).
- `PaymentGatewayInterface` with two implementations: `PaystackService`, `StripeService`, selected at runtime based on order currency.
- Webhooks: `POST /api/v1/webhooks/paystack`, `POST /api/v1/webhooks/stripe` -- verify signature/secret, idempotently finalize the order (guard against duplicate webhook delivery via a processed-events table or idempotency key).
- On successful webhook: create `Order` + `OrderItem` records, finalize inventory decrement, dispatch `OrderPlaced` event (triggers email + Fish Africa SMS + delivery booking).
- Guest checkout supported (no forced account creation); optional account creation offered post-purchase.

**Acceptance Criteria:**
- A GHS order routes exclusively through Paystack; a USD order routes exclusively through Stripe -- no cross-routing.
- Webhook replay (duplicate delivery) does not create duplicate orders or double-decrement inventory.
- Failed/abandoned payment sessions do not create an `Order` record and release any soft-reserved inventory.
- Order Confirmation page displays order number, items, total, currency, FX rate applied (if USD), and estimated delivery method.

**Dependencies:** Feature 3 (inventory), Paystack/Stripe SDKs, Feature 2 (FX rate service), Feature 5 (delivery routing), Feature 8/9 (notifications).

**Edge Cases:**
- Customer changes currency mid-checkout -> cart totals must be recalculated in the new currency (with a freshly locked FX rate) before payment session creation, not converted after the fact.
- Webhook arrives before frontend redirect completes -> order status must still be correctly retrievable by the frontend via polling `GET /api/v1/orders/{id}`.
- Partial gateway outage (e.g., Stripe down) -> USD checkout displays a clear service-unavailable message rather than a generic error.

---

## Feature 5: Delivery Integration (Yango & DHL)

**Purpose:** Route fulfillment to the correct courier based on shipping destination.

**Implementation Details:**
- `DeliveryProviderInterface` with `YangoService` (domestic, Ghana addresses) and `DhlService` (international).
- Routing rule: `shipping_address.country === 'GH'` -> Yango; otherwise -> DHL.
- At checkout, call the appropriate provider's quote endpoint (or, if a full API isn't available at implementation time, use static/link-based fallback per the "API/link" language in the source plan) to compute `shipping_cost` prior to payment.
- After payment confirmation, create the actual shipment/booking record with the provider and store `delivery_reference` on the `Order`.

**Acceptance Criteria:**
- Ghana-addressed orders never generate a DHL booking, and vice versa.
- Shipping cost shown at checkout matches the amount charged (no post-payment shipping surprises).
- Delivery reference/tracking info (if provided by the API) is surfaced on the Order Confirmation page and in admin Order detail.

**Dependencies:** Feature 4 (checkout must have final shipping address before payment), external Yango/DHL credentials.

**Edge Cases:**
- Provider API failure at quote time -> checkout blocks progression with a retry option rather than allowing an unpriced order.
- Address ambiguity (e.g., missing country field) -> validation error before reaching payment step.

---

## Feature 6: WhatsApp Integration

**Purpose:** Preserve the brand's informal, direct ordering channel alongside formal checkout.

**Implementation Details:**
- `WhatsAppButton` component renders a fixed-position (e.g., bottom-right) floating action button site-wide, linking to `https://wa.me/<configured_number>?text=<optional prefilled message>`.
- Number and default message sourced from the admin-editable `SiteSetting` resource (Feature 9), not hardcoded in multiple places.
- Also embed inline WhatsApp CTAs on Product Detail and Booking pages ("Prefer to order via WhatsApp?").

**Acceptance Criteria:**
- Button is visible and functional on every storefront route; not present in the separate admin app at all.
- Tapping the button on mobile opens the native WhatsApp app; on desktop without WhatsApp installed, opens WhatsApp Web.
- Changing the WhatsApp number in admin Site Settings updates the button site-wide without a code deploy.

**Dependencies:** Feature 9 (Site Settings CMS resource).

**Edge Cases:**
- Number misconfiguration should fail gracefully (button hidden or disabled rather than linking to an invalid number).

---

## Feature 7: Booking System (Workshop with Capacity/Waitlist, & Unlimited DIY Sandals)

**Purpose:** Allow customers to book capacity-managed in-person workshop experiences or submit unlimited/queue-based custom DIY sandal orders, independent of standard product checkout.

**Implementation Details:**
- Single `/booking` route (SSR -- see Feature 1) with two tabs/sub-views: Workshop and DIY Sandals, sharing the `BookingCalendar` component but distinct behavior per type.
- **Workshop:** Admin creates `WorkshopSession` records (date, slot, `capacity`, location notes) via admin. `BookingCalendar` displays available sessions with remaining capacity (`capacity` minus confirmed bookings). When a session reaches capacity, new submissions are automatically set to `status = waitlisted` and the `WaitlistBanner` is shown instead of blocking the form. On cancellation of a confirmed booking, `PromoteWaitlistedBooking` job promotes the next waitlisted booking to `confirmed` and triggers a notification.
- **DIY Sandals:** No date/capacity constraint -- `POST /api/v1/bookings` with `type=diy_order` is always accepted (queue-based). Form shows the current admin-set estimated turnaround time (sourced from `SiteSetting`) at submission time.
- Workshop form: session selection, number of attendees, contact info.
- DIY form: sandal specifications (size/measurements), reference image upload, preferred pickup/delivery, contact info.
- `POST /api/v1/bookings` creates a `Booking` record with `status = pending` (workshop, if capacity available) or `confirmed`/queued (diy_order); triggers confirmation email + Fish Africa SMS.
- Admin can transition booking status: pending -> confirmed -> completed/cancelled via `PATCH /api/v1/admin/bookings/{id}`; manage `WorkshopSession` capacity via `POST/PUT /api/v1/admin/workshop-sessions`.

**Acceptance Criteria:**
- Both booking types are submittable independently and validated with type-specific required fields.
- Workshop bookings beyond a session's capacity are automatically waitlisted, never silently overbooked or hard-rejected.
- Cancelling a confirmed workshop booking automatically promotes the next waitlisted booking and notifies that customer.
- DIY order submissions are never blocked or capacity-limited; customer sees the current estimated turnaround time.
- Customer receives an automated confirmation email + SMS (Fish Africa) immediately after submission.
- Admin Bookings view lists all bookings with filter by type/status/date; Admin can create/edit `WorkshopSession`s with custom capacity per session.

**Dependencies:** Feature 9 (notifications, Site Settings for turnaround estimate), Fish Africa SMS integration, file upload handling (DIY reference images).

**Edge Cases:**
- Image upload exceeding size limit or invalid file type is rejected client-side and server-side with a clear error message.
- Booking submitted for a past workshop session date is rejected by validation.
- Waitlist promotion when the promoted customer no longer wants the slot -- admin can manually skip to the next waitlisted entry.

---

## Feature 8: Fish Africa SMS & Transactional Email Notifications

**Purpose:** Deliver order and booking confirmations via both email and SMS.

**Implementation Details:**
- `NotificationServiceInterface` with `OrderConfirmationMailer` (email) and `FishAfricaSmsService` (SMS) implementations, both invoked from queued Laravel jobs (`SendOrderConfirmation`) to avoid blocking the request/response cycle.
- `FishAfricaSmsService` authenticates against `https://api.letsfish.africa` using App ID/App Secret Bearer token (per Fish Africa docs); sends order confirmation, booking confirmation, and waitlist-promotion messages.
- Both channels triggered on: order placed (post-webhook), booking submitted, booking status changed to confirmed, waitlist promotion.

**Acceptance Criteria:**
- Every completed order and booking submission triggers both an email and an SMS, queued and retried on transient failure.
- SMS/email failures are logged and do not block order/booking completion (notification failure is non-fatal to the underlying transaction).
- Fish Africa credentials are stored as environment secrets, never committed to version control.

**Dependencies:** Feature 4 (order events), Feature 7 (booking events), Fish Africa account/credentials, mail service provider.

**Edge Cases:**
- Invalid/malformed phone number -> SMS send fails gracefully, logged for admin visibility, email confirmation still sent.
- Fish Africa API outage -> queued job retries with backoff; does not retry indefinitely (define max attempts, e.g., 3).

---

## Feature 9: Native Admin CMS, Dashboard, and Site Settings

**Purpose:** Central operational view for orders, inventory, bookings, and -- critically -- a fully native content-management system (no third-party CMS) so the business owner can independently maintain the About page, blog, and site-wide settings post-handoff.

**Implementation Details:**
- **Deviation from the original plan:** the admin app is a separate Nuxt deployment (`admin/`, served at `admin.goldcoasttokota.store`), not `/admin/**` routes inside the storefront app — see Implementation Deviations. All routes gated by Sanctum-authenticated `AdminUser` with **two-tier role check** (`admin`|`staff` via `EnsureAdminRole`/`EnsureStaffOrAdminRole` middleware, checked against the dedicated `admin` Sanctum guard); unauthenticated access redirects to `/login`.
- Dashboard home: `MetricCard` grid (orders today/week, revenue by currency, site traffic, low-stock count, pending bookings/waitlist counts) -- **real-time direct database queries**, no caching/pre-aggregation layer at launch.
- Sub-views:
  - **Orders** (`DataTable`, status filters, detail drawer) -- Admin + Staff.
  - **Inventory** (stock levels + adjustment form) -- Admin + Staff.
  - **Bookings & Workshop Sessions** (status management, session capacity creation/editing) -- Admin + Staff.
  - **Blog** (`PageEditor` CMS) -- Admin + Staff.
  - **About Page** (`PageEditor` CMS, single `Page` resource) -- Admin + Staff.
  - **Site Settings** (WhatsApp number/message, contact info, social links, hero content, DIY turnaround estimate) -- **Admin only**.
  - **Products/Pricing/Refunds** (product deletion, price edits, refund issuance) -- **Admin only**.
  - **Newsletter/Feedback** (read-only lists, export option) -- Admin + Staff.
- `GET /api/v1/admin/dashboard/metrics` aggregates data server-side via direct queries (documented as upgradeable to a scheduled pre-aggregation job if data volume later demands it).

**Acceptance Criteria:**
- Only authenticated Admin/Staff roles can access any route in the `admin/` app or `/api/v1/admin/*` endpoint; Staff is correctly blocked (403) from Admin-only actions (product deletion, pricing changes, refunds, Site Settings).
- Dashboard metrics reflect live data on every load, with a visible last-updated timestamp.
- Orders table supports filtering by status and searching by customer name/email/order ID.
- Business owner can edit the About page and Site Settings (WhatsApp number, hero content, etc.) entirely through the admin UI, with changes reflected on the live storefront without any code deployment.

**Dependencies:** All prior data-producing features (Orders, Inventory, Bookings, Blog, Page/CMS models).

**Edge Cases:**
- Staff attempting a destructive/Admin-only action receives a clear, non-technical error message (not a raw 403 JSON blob).
- Session expiry mid-edit should prompt re-authentication without silently discarding unsaved form data where feasible.
- Rich-text editor (`PageEditor`) must sanitize submitted HTML server-side to prevent stored XSS.

---

## Feature 10: SEO & Social Integration

**Purpose:** Ensure the storefront is fully indexable and discoverable, leveraging Nuxt 3 SSR, with social channels linked.

**Implementation Details:**
- Per-route meta tags (title, description, Open Graph, canonical URL) via Nuxt's `useSeoMeta`/`useHead` composables, populated server-side during SSR for Home/Shop/Product/Blog/About routes.
- `@nuxtjs/sitemap` module generates `sitemap.xml` dynamically from active products and published blog posts (excluding inactive/unpublished content); `robots.txt` configured to allow crawling of SSR routes and disallow `/checkout`. (The admin app is a separate domain entirely, so it's simply never linked from or included in the storefront's sitemap/robots.txt — nothing to disallow there.)
- Footer/Header social links to Instagram and WhatsApp, sourced from `SiteSetting`.

**Acceptance Criteria:**
- Each product, blog post, and core page has a unique, descriptive `<title>` and meta description present in the server-rendered HTML (verifiable via "view source").
- Sitemap includes all active products and published blog posts, excluding inactive/unpublished content, and updates automatically as content changes.
- Social links open in a new tab and point to correct, live brand accounts.

**Dependencies:** Feature 2 (products), Feature 9 (blog, About page, Site Settings), Feature 1 (SSR routing setup).

**Edge Cases:**
- Missing product description falls back to a generated summary rather than an empty meta tag.
- GSAP/ScrollTrigger scripts must not delay or block the initial SSR HTML response -- loaded/executed strictly post-hydration.

---

## Feature 11: Analytics

**Purpose:** Track site traffic and e-commerce behavior for both external (GA4) and internal (admin dashboard) reporting.

**Implementation Details:**
- Client-side GA4 tag (`gtag.js`) loaded globally; standard e-commerce events tracked: `view_item`, `add_to_cart`, `begin_checkout`, `purchase`.
- Server-side mirroring of key events (at minimum `purchase`) into queryable order data in Postgres, to power the admin dashboard independent of GA4 availability/ad-blockers.

**Acceptance Criteria:**
- GA4 receives e-commerce events correctly attributed with currency and value (GHS or USD, with FX rate context for USD).
- Admin dashboard traffic/order metrics do not depend solely on client-side GA4 (i.e., remain accurate even if a customer blocks GA scripts).

**Dependencies:** Feature 4 (checkout events), Feature 9 (dashboard).

**Edge Cases:**
- Ad-blocker prevents GA4 script load -> server-side metrics must not be affected.

---

## Feature 12: Security & SSL

**Purpose:** Protect customer data and transactions.

**Implementation Details:**
- SSL handled entirely by **Render's built-in automatic Let's Encrypt provisioning/renewal** -- no manual Certbot or nginx configuration required for either the Laravel API or Nuxt frontend Web Service.
- Laravel Sanctum with CSRF protection for stateful SPA requests; rate-limiting on auth, checkout-session-creation, and payment-sensitive endpoints.
- Two-tier role middleware (`EnsureAdminRole`/`EnsureStaffOrAdminRole`) enforced on every `/api/v1/admin/*` route.
- Webhook endpoints verify provider signatures before processing.
- No sensitive payment data (card numbers) touches Laravel servers directly -- handled via gateway-hosted fields/redirect (PCI scope minimization).
- Fish Africa, Paystack, Stripe, Yango, DHL, and FX provider credentials stored as Render environment secrets, never committed to version control.

**Acceptance Criteria:**
- Site is served exclusively over HTTPS on all three Render Web Services; HTTP requests redirect to HTTPS automatically.
- Automated SSL renewal confirmed via Render's platform behavior (no manual intervention).
- Role-boundary smoke test confirms Staff accounts cannot access Admin-only endpoints and unauthenticated requests cannot access any route in the admin app.

**Dependencies:** Deployment infrastructure (Feature 13).

**Edge Cases:** N/A -- this is a cross-cutting requirement enforced across all features.

---

## Feature 13: Deployment & Hosting (Render)

**Purpose:** Deploy the application to production infrastructure on Render and hand over to the client.

**Implementation Details:**
- **Domains:** `goldcoasttokota.store` (storefront) is the authoritative production domain; `admin.goldcoasttokota.store` (admin dashboard) is a **deviation** from the original plan's `/admin/**`-path approach — see Implementation Deviations.
- **Hosting:** Render, region **Frankfurt**, structured as:
  - Web Service 1 -- Laravel API (Docker-based Laravel deployment on Render, `backend/Dockerfile`).
  - Web Service 2 -- Nuxt 3 SSR storefront (Node runtime), domain `goldcoasttokota.store`.
  - Web Service 3 -- Nuxt 3 SPA admin dashboard (Node runtime), domain `admin.goldcoasttokota.store`.
  - Managed PostgreSQL instance (Render Managed Postgres), same region, connected to the Laravel Web Service via Render's internal networking.
- SSL is automatic per Feature 12 -- no separate provisioning step.
- Environment separation: staging vs production environment variable groups on Render, with production secrets (payment/delivery/SMS/FX API keys) set directly in Render's dashboard, never committed to version control.
- Post-deployment smoke test checklist executed against the live environment before considering launch complete.
- `render.yaml` Blueprint recommended to codify all three Web Services + the database as infrastructure-as-config for reproducible deploys.

**Acceptance Criteria:**
- Production site accessible at `goldcoasttokota.store` over HTTPS with no mixed-content warnings; admin dashboard accessible at `admin.goldcoasttokota.store` over HTTPS.
- All three Web Services (API + storefront + admin) deploy independently via Render's Git-based deploy pipeline.
- Admin dashboard training session materials and credentials handed over per Week 8 plan.
- 30-day post-launch support window tracked with a defined start date and scope.

**Dependencies:** All prior features must pass QA (Feature 14) before deployment.

**Edge Cases:**
- Render cold-start latency on lower-tier plans -- confirm the selected Render plan tier keeps both services warm enough for acceptable response times (upgrade plan tier if needed).
- DNS propagation delay when pointing `goldcoasttokota.store` to Render -- plan for a buffer before the Week 8 go-live date.

---

## Feature 14: Testing & QA

**Purpose:** Validate full functionality prior to launch.

**Implementation Details:**
- Feature/unit tests (Laravel PHPUnit/Pest) for: checkout flow (both currencies, FX rate locking), webhook idempotency, inventory reservation/decrement race conditions, workshop capacity/waitlist logic, DIY unlimited-booking behavior, newsletter single-opt-in de-duplication, role-based access control (Admin vs Staff).
- Frontend: component tests (Vitest) for cart/currency logic; end-to-end tests (Cypress/Playwright) for full checkout and booking flows, including verifying SSR pages render correctly without JS (crawler-equivalent check).
- Manual QA checklist covering: payment gateway testing (GHS & USD, FX rate accuracy), inventory/order end-to-end, booking system (both types, capacity/waitlist), delivery integration (Yango & DHL), Fish Africa SMS delivery, mobile responsiveness across devices, cross-browser compatibility, SSL verification (Render auto-SSL), performance pass, GSAP animation behavior (no SSR errors, graceful degradation).

**Acceptance Criteria:**
- All automated tests pass in CI prior to deployment.
- Manual QA checklist (see Testing Checklist section) fully signed off before Week 8 deployment begins.

**Dependencies:** All functional features (1-13) implemented.

**Edge Cases:** N/A -- this feature exists to surface edge cases in others.

---

# State Management

- **Pinia** (`@pinia/nuxt` module) as the state management library -- Nuxt-compatible, SSR-safe hydration.
- Stores are split across the two Nuxt apps (see Implementation Deviations) — each app's Pinia instance is independent, they don't share state:
  - `frontend/` (storefront): `useCurrencyStore` (active currency, cached FX rate, display formatting rules), `useCartStore` (cart items/quantities/subtotal, persisted via first-party cookie rather than `localStorage` so it's available on the very first server-rendered response), `useAuthStore` (customer authentication state only), `useCatalogStore` (cached product/category/FX-rate data, short TTL-based invalidation), `useBookingStore` (in-progress booking form state, selected `WorkshopSession` + remaining capacity).
  - `admin/` (dashboard): `useAuthStore` -- admin authentication state, **role** (admin|staff).
- Server remains the source of truth for price, FX rate, inventory, booking capacity, and order status at all times; client state is a cache/UI convenience layer only.

# API / Backend Requirements

- All endpoints versioned under `/api/v1/`.
- Authentication: Laravel Sanctum, cookie-based for the first-party Nuxt SPA-mode routes; **two-tier role guard** (`admin`|`staff`) for `/api/v1/admin/*`, with clearly separated Admin-only sub-routes (products, pricing, refunds, site settings) vs Admin+Staff routes (orders, inventory, bookings, content).
- Standard response envelope: `{ data, meta, errors }` with consistent HTTP status codes (200/201/204/400/401/403/404/422/500).
- Validation via Laravel Form Requests for every write endpoint; return field-level 422 errors.
- Rate limiting on: login, checkout session creation, webhook endpoints (webhooks additionally protected by signature verification, not rate limiting alone).
- Idempotency keys or processed-event tracking required for both payment webhooks.
- Pagination (cursor or page-based, consistent choice) on all list endpoints (products, orders, bookings, blog).
- `GET /api/v1/fx-rate` -- public, cached, short TTL -- powers live USD price display.

# Styling Requirements

- Tailwind CSS, mobile-first utility classes; no legacy custom CSS frameworks.
- Design tokens (colors, spacing, typography) sourced from the brand identity established in Week 2 design sign-off; centralize in `tailwind.config.ts` theme extension rather than ad hoc utility values.
- Consistent component spacing/typography scale reused across storefront and admin (admin may use a denser variant of the same token set, not a wholly separate design system).

# Animation Requirements

- **GSAP + ScrollTrigger** for brand-story motion design (confirmed), used primarily on Home and About sections to create scroll-driven reveals reflecting the brand's handmade/artisan feel.
- **Critical SSR constraint:** GSAP is DOM-manipulation-based and must run **client-side only**. All GSAP code must be wrapped in `onMounted()` and/or rendered via `<ClientOnly>` (the `GsapScrollReveal` component) -- never referenced during the Nuxt server render pass, since the SSR routes (Home, Shop, Product, Blog, About) would otherwise throw `window is not defined` errors.
- Pattern: SSR renders full static HTML first (content fully visible/readable and crawlable with zero JS), then GSAP progressively enhances with animation on client hydration. Content must never be hidden or broken if GSAP fails to load.
- Lighter transitions elsewhere (route changes, cart drawer open/close, toast notifications) may still use Nuxt/Vue's built-in `<Transition>` component rather than GSAP, reserving GSAP for the more expressive brand-story moments.

# Performance Considerations

- Image compression and responsive image delivery for product/blog images (explicit Week 7 requirement) -- consider Nuxt Image module for automatic optimization.
- Page-speed optimization pass required before launch (Week 7): Nuxt's automatic code-splitting per route, lazy-loading non-critical components (e.g., admin dashboard chunks excluded from the public SSR bundle entirely, since admin is SPA-mode and separately loaded).
- Cache product/category/FX-rate list responses with short TTL to reduce database load on high-traffic SSR pages.
- Database indexes on frequently filtered columns: `products.slug`, `products.is_active`, `orders.status`, `bookings.scheduled_date`, `workshop_sessions.scheduled_date`.
- Render Web Service plan tier should be sized to avoid cold-start delays affecting SSR response times (see Feature 13 edge cases).

# Accessibility Requirements

- **Best-effort baseline (confirmed)** -- not a formal WCAG 2.1 AA audit/certification.
- Semantic HTML, keyboard navigability for all interactive elements, reasonable color contrast, form fields with associated labels and error messaging.
- GSAP animations must respect `prefers-reduced-motion` where practical, degrading to instant/no-animation for users who have that OS-level preference set.

# Testing Checklist

- [ ] Full functionality testing of all pages and features
- [ ] Payment gateway testing -- GHS via Paystack
- [ ] Payment gateway testing -- USD via Stripe (including FX rate lock verification)
- [ ] Inventory tracking correctness under concurrent checkout (polling freshness separately verified)
- [ ] Order flow end-to-end (cart -> payment -> confirmation -> admin visibility)
- [ ] Booking system -- Workshop flow (capacity enforcement + waitlist promotion)
- [ ] Booking system -- DIY Sandals flow (unlimited/queue-based, turnaround estimate display)
- [ ] Delivery integration -- Yango (Ghana addresses)
- [ ] Delivery integration -- DHL (international addresses)
- [ ] Fish Africa SMS delivery for order and booking confirmations
- [ ] Two-tier role enforcement (Admin vs Staff) across all admin endpoints
- [ ] Native CMS -- About page and Blog editable end-to-end via admin, reflected live with no deploy
- [ ] Site Settings changes (WhatsApp number, hero content) reflected site-wide with no deploy
- [ ] Mobile responsiveness across representative device set
- [ ] Cross-browser compatibility (Chrome, Safari, Firefox, Edge)
- [ ] SSR verification -- "view source" on Home/Shop/Product/Blog/About shows fully-rendered HTML with correct meta tags
- [ ] GSAP animations do not throw SSR errors and degrade gracefully if scripts fail
- [ ] SSL certificate valid via Render auto-provisioning
- [ ] Security checks (auth boundaries, webhook signature verification, rate limiting)
- [ ] Performance pass (page speed, image compression, bundle size, Render cold-start check)
- [ ] SEO meta tags and sitemap validated
- [ ] Analytics events firing correctly (GA4 + server-side mirror)
- [ ] Admin dashboard real-time metrics accuracy
- [ ] Newsletter (single opt-in) and feedback form submission and de-duplication
- [ ] WhatsApp deep link functional on mobile and desktop

# Implementation Deviations

Two decisions made during implementation depart from this document. Both are
scoped, documented, and don't change any other feature's acceptance
criteria.

1. **Laravel 12, not Laravel 11.** By the time implementation started, the
   entire Laravel 11 branch was flagged with unpatched security advisories in
   Composer (Laravel had moved on to v13.x). Laravel 12 was used instead — no
   architectural difference relevant to this spec (Sanctum SPA cookie auth,
   Eloquent, Form Requests, API Resources, `bootstrap/app.php`-based
   middleware config all work identically).

2. **Admin dashboard is a separate top-level app, not `/admin/**` inside the
   storefront.** The original plan describes the admin dashboard as Nuxt SPA
   routes living inside the same Nuxt app as the public storefront (Feature
   9, Folder Structure). Per explicit request, it was built instead as its
   own Nuxt project (`admin/`), with its own `package.json`, build, and
   Render Web Service, served at `admin.goldcoasttokota.store`. Both apps
   call the same Laravel API; nothing else in this spec changes — the
   two-tier role model, the `EnsureAdminRole`/`EnsureStaffOrAdminRole`
   middleware, the CMS resources, and every Feature 9 acceptance criterion
   still apply, just against a different frontend deployment. The tradeoff:
   no shared route table/bundle with the storefront (so, e.g., component
   changes need to happen in two places if ever shared), in exchange for a
   fully independent build/deploy cycle and no risk of admin code shipping
   in the public bundle.

# Implementation Order

**Phase 1 -- Requirements & Planning (Week 1)**
- Finalize product catalogue structure, FX rate provider selection, delivery/payment integration requirements.
- Confirm scope/timeline/communication plan; all clarifications resolved except the deferred timeline-date item.

**Phase 2 -- Design (Week 2)**
- Site architecture/page map, wireframes for Home/Shop/About/Booking/Blog, Admin Dashboard layout (including CMS/Site Settings screens), design sign-off.

**Phase 3a -- Core Development (Week 3)**
- Provision Render Web Services (API + frontend) and Managed Postgres; connect `goldcoasttokota.store` domain.
- Scaffold Laravel + Nuxt project structure per Folder Structure section; configure `routeRules` for SSR/SPA split.
- Implement Feature 1 (Home, About, Shop shell) and Feature 6 (WhatsApp).

**Phase 3b -- E-commerce & Inventory (Week 4)**
- Implement Feature 2 (Catalogue/Pricing with live FX rate), Feature 3 (Inventory/polling), Feature 4 (Checkout/Payments), Feature 5 (Delivery Integration), Feature 8 (Fish Africa SMS + email notifications).

**Phase 3c -- Bookings & Content (Week 5)**
- Implement Feature 7 (Booking system -- Workshop capacity/waitlist + DIY unlimited queue), native CMS foundations (Blog, About Page, Newsletter, Feedback per Feature 9).

**Phase 3d -- Admin & SEO (Week 6)**
- Implement full Feature 9 (Admin Dashboard, two-tier roles, Site Settings), Feature 10 (SEO/sitemap), Feature 11 (Analytics).
- Implement GSAP/ScrollTrigger motion design (client-only, per Animation Requirements) on Home/About.

**Phase 4 -- Testing & QA (Week 7)**
- Execute Feature 14 in full; work through Testing Checklist; performance and security hardening pass; verify SSR correctness and GSAP SSR-safety explicitly.

**Phase 5 & 6 -- Deployment & Handover (Week 8)**
- Finalize Render production deployment; admin training session (covering native CMS/Site Settings usage explicitly, given the no-developer-on-call context); documentation/credential handover; agree Phase 2 (post-launch) roadmap; begin 30-day support window.

# Clarifications Needed

All clarifications from the initial draft have been resolved through stakeholder discussion, with the following exceptions/notes:

1. **Document date inconsistency (deferred):** The original source plan's header states the engagement spans "July 01 to August 26" in one place and "July 01 to August 31, 2026" in another (with the Week 8 table also showing Aug 19-31). The authoritative end date for scheduling purposes is still to be confirmed -- deferred at stakeholder request. Does not block Phase 1-7 implementation, only final Week 8 go-live scheduling.

2. **FX rate provider (partially open):** Live/periodic exchange rate confirmed as the pricing approach, but the specific FX data provider (e.g., exchangerate.host, Open Exchange Rates, a bank feed) and exact refresh cadence (hourly/daily) have not yet been selected. Recommend finalizing before Phase 3b (Week 4) implementation begins, since `FxRateService` needs a concrete API to integrate against.

3. **Fish Africa Ghana network coverage/delivery reporting:** Fish Africa (`letsfish.africa`) has been selected as the SMS provider, but MTN/Vodafone/AirtelTigo-specific delivery rates and whether they provide delivery-status webhooks have not been independently verified. Recommend a sandbox test during Phase 3b before relying on it for production confirmations.
