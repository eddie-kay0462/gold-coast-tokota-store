# For the Team

A running log of what has changed in this codebase and what is still
outstanding, so anyone picking the project up can get current without reading
the whole diff.

**Read `README.md` for the spec and `CLAUDE.md` for the architectural rules.**
This file is the *status* layer on top of those two — it does not restate them.

- **Last updated:** 27 August 2026 (first entry below — the sticky header)
- **Last commit on `main`:** `fc84d9e` — *Merge branch 'backend' into main*
- **Working tree:** clean, and `dev` is pushed. The 27 Aug work is seven commits
  on `dev` starting at `d201f5a` — the Template B design pass and everything
  that followed from it. `dev` is ahead of `main`; merging it is a separate
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
| Storefront — Account, Legal, Help, Company, Commerce | **Built** 26 Aug — 17 new page files covering 22 routes. Auth and payment are inert by design; see the entry below |
| Storefront — Checkout | **Built to the payment boundary** — 3-step flow, real validation, currency-routed gateway UI, restyled 27 Aug as a Shopify-style checkout on its own stripped layout. `POST /checkout/session` does not exist, so placing an order is inert |
| Storefront — Order confirmation | **Built** — full receipt, three states, webhook-race polling. Waiting on `GET /orders/{id}` |
| Storefront — Booking | **Built** 27 Aug — real session list from `GET /workshop-sessions`, capacity chips, waitlist, both forms matched to `StoreBookingRequest`. Was scaffold stubs |
| Backend API | **Further along than this file used to claim.** `routes/api.php` serves products, categories, collections, fx-rate, workshop-sessions, bookings, blog-posts, newsletter, pages and site-settings, plus a working `AdminAuthController` (`POST /v1/admin/login`, `/logout`, `GET /me`). No customer auth, no checkout, no orders endpoint |
| Database | Migrations for admin_users, customers, pages, site_settings, categories, products, inventory_items, fx_rates, collections, workshop_sessions, bookings, blog_posts, newsletter_subscribers, orders, order_items |
| Admin dashboard | **Built** — 36 routes, dark/light/system theming, four-tier roles. Runs on bundled fixtures; see the entry below |
| Tests | None beyond Laravel's two `ExampleTest` placeholders |

Against the README's "Implementation Order": **Phase 3a is done** (Feature 1
core pages, now including every route the chrome links to), Feature 6 (WhatsApp)
is in place, and Feature 2's catalogue endpoints exist. What is still missing on
the backend is the transactional half — checkout sessions and payments
(Feature 4), delivery quotes (Feature 5), notifications (Feature 8) and customer
auth — which is why several storefront pages are complete but deliberately
inert at their last step.

---

## Recent changes

Everything below happened over four working days (18–21 August). The 19–20 Aug
work is **not yet committed** — see the note at the end of this section.

### 27 August 2026 (latest) — the header is sticky

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

- ~~**Booking page** (`/booking`) — the four components are stubs.~~ — **closed
  27 Aug.** Sessions are fetched, capacity and waitlist render, and both forms
  now send the payload `StoreBookingRequest` actually validates. What is left is
  backend: an upload endpoint for DIY reference images (the form records the
  file *name* and asks the customer to send the photo over WhatsApp, because
  `details.reference_image` is typed as a string and nothing accepts a binary).
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
  authenticates until `backend/routes/api.php` gains a `CustomerAuthController`
  (register / login / logout / me / profile / password reset). The model side is
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
2. **Admin login** — no `AuthController`, no route, no Form Request. Until it
   exists `pages/login.vue` is inert by design and no route middleware is
   registered. Wiring it is: `GET /sanctum/csrf-cookie`, `POST /admin/login`,
   `GET /admin/me`, then add an `auth` middleware to the pages.
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
| 5 | **FX provider unchosen** | Blocks Feature 2. README "Clarifications Needed" #2. The *frontend* half is no longer blocked as of 27 Aug — `plugins/fx-rate.ts` consumes `GET /fx-rate`, and prices fall back to cedis when no rate is available rather than rendering `$0`. What is still missing is a real provider behind `FxRateService`; it currently serves a `seed-placeholder` rate. |
| 6 | **Fish Africa coverage unverified** | Blocks confidence in Feature 8. README "Clarifications Needed" #3. |
| 7 | **Engagement end date unconfirmed** | README "Clarifications Needed" #1. |
| 8 | **App chrome is 8–12px out of alignment with page content** | Content now sits at a 60px desktop gutter everywhere (`.page-gutter`). `Header.vue` is still at 68px, `Footer.vue` at 72px, `MegaMenuPanel.vue` at 140px and `SearchPanel.vue` at 156/326px — all Figma-exact. Moving them to 60px would line the nav and footer edges up with the content below, but it visibly changes brand chrome. **Awaiting a decision.** |
| 9 | ~~**Marquee line-height on the Sustainability masthead**~~ | **Closed 27 Aug 2026.** The masthead was the only user of `display-brand`, whose 176/96 Figma leading measured 66px at the 36px mobile floor. It went with the About/Sustainability merge — About already had a hero, and a second brand-sized wordmark mid-page read as a different page starting. The token is still defined and now unused; delete it if nothing claims it. |
| 11 | **The WhatsApp inbox implies scope README does not cover** | README Feature 6 specifies a `wa.me` deep link and states "no API integration required". `/inbox` needs the Business Cloud API, a verified WABA, approved templates and a webhook receiver. It ships as a clearly-labelled simulation; **whether to fund the real integration is awaiting a decision.** |
| 12 | **Role model disagrees across sources** | README and `admin_users.role` say two tiers; the brand PDF names three; the business asked for four (adding a time-boxed `intern`). The admin UI implements four. **The database enum and role middleware still need widening** — until then the server cannot enforce what the UI presents. |
| 13 | **WhatsApp number is provisional** | The PDF gives `+233 25 753 4297` annotated "(update with official number)". It is seeded into site settings and surfaced with a warning on `/settings/whatsapp`. Needs confirming before launch. |
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
