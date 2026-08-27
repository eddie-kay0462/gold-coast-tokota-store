export type NavLink = { label: string, to: string }

export type MegaMenuPromo = {
  label: string
  image: string
  alt: string
  to: string
}

export type MegaMenu = {
  columns: { heading: string, links: NavLink[] }[]
  promos: MegaMenuPromo[]
}

export type NavItem = NavLink & { accent?: boolean, menu?: MegaMenu }

// Row 2 of the header — the non-shopping destinations.
//
// `Bookings` and `Stories` are the approved Template B mockup's own labels.
// `Shop` stays even though the mockup has no plain "Shop" item: row 3 only
// offers *filtered* entries to the catalogue, and dropping the one unfiltered
// way in would be a usability regression, not a design decision.
export const primaryNav: NavLink[] = [
  { label: 'Shop', to: '/shop' },
  { label: 'Bookings', to: '/booking' },
  { label: 'Stories', to: '/blog' },
  { label: 'About', to: '/about' },
  { label: 'Sustainability', to: '/sustainability' },
]

/** Merges extra query params into a base route, preserving what's already there. */
function withParams(baseTo: string, params: Record<string, string>): string {
  const [path, existing] = baseTo.split('?')
  const search = new URLSearchParams(existing)
  for (const [key, value] of Object.entries(params)) search.set(key, value)
  const query = search.toString()
  return query ? `${path}?${query}` : path!
}

// Both promo tiles come from the Figma Mens panel; they are the only two pieces
// of menu artwork that exist. Swap them per category once real photography does.
const promoArtwork = {
  ahenema: { image: '/design/menu-ahenema.png', alt: 'Blue and black leather ahenema sandals' },
  closedToe: { image: '/design/menu-closed-toe.png', alt: 'Closed-toe leather shoes in tan and black' },
}

/**
 * Builds the two-column + two-promo panel drawn in Figma, rebased onto a given
 * shop route.
 *
 * Every link here resolves to a filter `/shop` actually implements — `type`,
 * `sort` and `sale`, the keys `pages/shop/index.vue` reads. The previous
 * version pointed at `?collection=gift-guide`, `?collection=new-ahenema` and
 * friends, which nothing filters on: the panel looked complete and every link
 * in it silently returned the unfiltered catalogue. That is why these menus
 * were all flagged `placeholder` — they no longer need to be.
 *
 * Rebasing is what makes one template serve every item: opened from Sandals,
 * "New Arrivals" means new sandals; opened from Sale, it means new sale items.
 */
function createShopMenu(baseTo: string, label: string): MegaMenu {
  return {
    columns: [
      {
        heading: 'Shop All',
        links: [
          { label: `All ${label}`, to: baseTo },
          { label: 'Ahenema', to: withParams(baseTo, { type: 'ahenema' }) },
          { label: 'Sandals', to: withParams(baseTo, { type: 'sandals' }) },
          { label: 'Slippers', to: withParams(baseTo, { type: 'slippers' }) },
          { label: 'Closed-Toe Shoes', to: withParams(baseTo, { type: 'closed-toe' }) },
          { label: 'Merchandise', to: withParams(baseTo, { type: 'merchandise' }) },
        ],
      },
      {
        heading: 'Featured Shops',
        links: [
          { label: 'New Arrivals', to: withParams(baseTo, { sort: 'newest' }) },
          { label: 'Best Sellers', to: withParams(baseTo, { sort: 'best-selling' }) },
          { label: 'Top Rated', to: withParams(baseTo, { sort: 'top-rated' }) },
          { label: 'On Sale', to: withParams(baseTo, { sale: 'true' }) },
          // Custom work is a booking, not a catalogue filter.
          { label: 'Customised Sandals', to: '/booking' },
        ],
      },
    ],
    promos: [
      {
        label: 'Latest and Greatest Ahenema',
        ...promoArtwork.ahenema,
        to: withParams(baseTo, { type: 'ahenema', sort: 'newest' }),
      },
      {
        label: 'Closed-Toe\nShoes',
        ...promoArtwork.closedToe,
        to: withParams(baseTo, { type: 'closed-toe' }),
      },
    ],
  }
}

/**
 * Row 3 — the catalogue entry points, taken from the approved Template B
 * mockup's nav: Best Sellers, Sandals, Ahenema.
 *
 * This replaces the seven department placeholders that used to live here
 * (Mens, Womens, Kids, New Arrivals, Best-Sellers, Merchandise, Custom Shoes).
 * They were flagged `placeholder: true` because `?category=mens` and friends
 * filter on a `departments` field only the design catalogue carries — the API's
 * `ProductResource` has never returned it, so those tabs would have shown the
 * whole catalogue on real data. `?type=` is a facet the shop genuinely filters.
 *
 * `Sale` is not in the mockup and is kept deliberately: sale pricing is built
 * (`compare_at_ghs`, the discount badge, the struck compare-at price), and the
 * `sale` colour token exists for this one item.
 */
export const categoryNav: NavItem[] = [
  {
    label: 'Best Sellers',
    to: '/shop?sort=best-selling',
    menu: createShopMenu('/shop?sort=best-selling', 'Best Sellers'),
  },
  {
    label: 'Sandals',
    to: '/shop?type=sandals',
    menu: createShopMenu('/shop?type=sandals', 'Sandals'),
  },
  {
    label: 'Ahenema',
    to: '/shop?type=ahenema',
    menu: createShopMenu('/shop?type=ahenema', 'Ahenema'),
  },
  {
    label: 'Sale',
    to: '/shop?sale=true',
    accent: true,
    menu: createShopMenu('/shop?sale=true', 'Sale'),
  },
]

/**
 * The About page's own section nav (Figma 6:554). Every tab but the Annual
 * Impact Report now has a real destination — the brand-story sections drawn on
 * this page, the Sustainability page, its stories, and the workshop booking
 * flow. The report is not a designed page yet, so it resolves to the nearest
 * existing one and is flagged `placeholder`; grep the flag to find what still
 * needs a real route.
 */
export const aboutSectionNav: (NavLink & { placeholder?: boolean })[] = [
  { label: 'About', to: '/about' },
  { label: 'Cleaner Manufacturing', to: '/about#factories' },
  { label: 'Workshop', to: '/booking' },
  { label: 'Environmental Initiatives', to: '/sustainability' },
  { label: 'Our Carbon Commitment', to: '/blog/our-carbon-commitment' },
  { label: 'Annual Impact Report', to: '/sustainability', placeholder: true },
  { label: 'Partnerships', to: '/blog/partnerships-for-change' },
]

/**
 * The account section's own tab row, consumed by `AccountShell` the same way
 * `aboutSectionNav` is consumed by `AboutSectionNav`.
 *
 * Sign-out is not a link and so is not here — it is an action, and it lives in
 * the shell's footer.
 */
export const accountNav: NavLink[] = [
  { label: 'Overview', to: '/account' },
  { label: 'Orders', to: '/account/orders' },
  { label: 'Settings', to: '/account/settings' },
]
