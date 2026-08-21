# For the Team

A running log of what has changed in this codebase and what is still
outstanding, so anyone picking the project up can get current without reading
the whole diff.

**Read `README.md` for the spec and `CLAUDE.md` for the architectural rules.**
This file is the *status* layer on top of those two — it does not restate them.

- **Last updated:** 21 August 2026 (second entry below)
- **Last commit on `main`:** `393413a` — *feat(header): centered brand logo on website header*
- **Working tree:** contains substantial uncommitted work (News & Events, the
  search panel, About, Sustainability) — everything from 19 Aug 00:06 onward.
  Read the "Uncommitted work" note at the end of *Recent changes* before you branch.

---

## Where the project stands

| Area | Status |
|---|---|
| Storefront — Home | **Built** from Figma |
| Storefront — Shop listing + Product detail + Cart drawer | **Built** from Figma |
| Storefront — News & Events (listing + article) | **Built** from Figma *(uncommitted)* |
| Storefront — About | **Built** from Figma *(uncommitted)* |
| Storefront — Sustainability | **Built** from Figma *(uncommitted)* |
| Storefront — Booking, Checkout, Order confirmation | **Scaffold stubs only** (10–45 lines each) |
| Backend API | **Two endpoints only** — `GET /v1/pages/{slug}`, `GET /v1/site-settings` |
| Database | 4 tables: `admin_users`, `customers`, `pages`, `site_settings` |
| Admin dashboard | **Scaffold only** — 10 pages averaging ~15 lines, no API wiring |
| Tests | None beyond Laravel's two `ExampleTest` placeholders |

Against the README's "Implementation Order": **Phase 3a is nearly done**
(Feature 1 core pages), Feature 6 (WhatsApp) is in place, and Phases 3b–3d have
not started on the backend.

---

## Recent changes

Everything below happened over four working days (18–21 August). The 19–20 Aug
work is **not yet committed** — see the note at the end of this section.

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

Almost nothing exists yet. Every storefront page currently renders from
hardcoded design fallbacks (see "Conventions" below). In rough dependency
order:

1. **Feature 2 — Catalogue & pricing.** `products`, `variants`, `categories`
   tables; `ProductController`; `FxRateService` + the cached GHS→USD rate. USD
   is always derived, never stored. *Blocked on: FX provider still unchosen —
   see README "Clarifications Needed" #2.*
2. **Feature 3 — Inventory.** Stock levels, reservations
   (`RESERVATION_TTL_MINUTES` is already in `utils/constants.ts`), and the
   polling endpoint behind `useInventoryPolling`.
3. **Feature 4 — Checkout & payments.** Orders, Paystack (GHS) + Stripe (USD)
   session creation and webhook verification, FX rate locked at checkout.
4. **Feature 5 — Delivery.** Yango (domestic) and DHL (international) quote/booking.
5. **Feature 7 — Bookings.** Workshop sessions with capacity + waitlist, and
   the unlimited DIY queue.
6. **Feature 8 — Notifications.** Fish Africa SMS + transactional email behind
   a swappable `NotificationService`. *Sandbox test recommended first — Ghana
   network delivery rates unverified (README "Clarifications Needed" #3).*
7. **Feature 9 — CMS + admin API.** Blog posts, About/Page editing, site
   settings, newsletter, feedback; two-tier role middleware is scaffolded but
   unused.

### Storefront

- **Booking page** (`/booking`) — the four components are stubs.
- **Checkout + order confirmation** — stubs; SPA-only route rules are already
  configured.
- **Wire the pages to real endpoints** as each API lands, and delete the
  corresponding design fallback.
- **Routes referenced by the footer/nav that do not exist yet:** `/careers`,
  `/account/login`, `/account/register`, `/gift-cards`, `/help/**`,
  `/legal/**`, `/international`, `/accessibility`, `/affiliates`, `/stores`,
  `/about#dei`. These log router warnings in dev — expected, not a regression.
- **Currency toggle does not follow the flag.** The header now knows the
  visitor's country but `stores/currency.ts` still defaults everyone to GHS,
  and the README asks for the choice to persist via cookie. Deliberately left
  alone — defaulting a country to a currency is a commercial decision, not a
  technical one. Decide GH → GHS / everyone else → USD (or otherwise) and wire
  it, respecting an explicit user choice over the geo default.
- **`public/design/flag.svg`** is now unused; kept only as the original Figma
  export. Delete it once nobody wants the reference.
- **Placeholder nav targets** are flagged `placeholder: true` in
  `utils/navigation.ts`. Grep that flag to find what still needs a real route
  (currently: seven mega-menu categories, and "Annual Impact Report").

### Admin dashboard

Entirely unimplemented past the scaffold. Needs auth against the `admin` Sanctum
guard, then the tables and editors listed in README Feature 9.

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
| 1 | ~~**Site-wide horizontal overflow below ~500px**~~ | **Closed 21 Aug 2026.** Measured rather than estimated: the document never actually scrolled sideways, but the sign-up link did overlap the currency cluster by 41px at 320px and 375px. The cluster is a normal flex child now, and the message runs through a marquee below `sm` — the treatment Kirk chose. |
| 2 | **About price-breakdown artwork is Everlane's** | The Figma export (`about-price-breakdown.png`) has "Everlane T-shirt vs Traditional Retail" and USD figures baked into the bitmap. Needs real Gold Coast Tokota cost data. Cannot be fixed in code. |
| 3 | **About "Designed to last" copy was rewritten** | Figma's text names Everlane, cashmere sweaters and Peruvian Pima tees. Adapted to the brand. All other copy is verbatim from the design. |
| 4 | **"Our Carbon Commitment" is tagged `Style`** | Straight from Figma `10:958`; looks like a design slip. Transcribed faithfully — flag if it should read Sustainability. |
| 5 | **FX provider unchosen** | Blocks Feature 2. README "Clarifications Needed" #2. |
| 6 | **Fish Africa coverage unverified** | Blocks confidence in Feature 8. README "Clarifications Needed" #3. |
| 7 | **Engagement end date unconfirmed** | README "Clarifications Needed" #1. |
| 8 | **App chrome is 8–12px out of alignment with page content** | Content now sits at a 60px desktop gutter everywhere (`.page-gutter`). `Header.vue` is still at 68px, `Footer.vue` at 72px, `MegaMenuPanel.vue` at 140px and `SearchPanel.vue` at 156/326px — all Figma-exact. Moving them to 60px would line the nav and footer edges up with the content below, but it visibly changes brand chrome. **Awaiting a decision.** |
| 9 | **Marquee line-height on the Sustainability masthead** | `display-brand` keeps Figma's 176/96 ratio, so at the mobile floor (36px) the leading is 66px — very airy. Faithful to the design, but it may be a Figma artifact rather than intent. Cheap to change to ~1.1 if it is. |
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
  design and code stay reconcilable.
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
