export type NavLink = { label: string, to: string }

export type MegaMenuPromo = {
  label: string
  image: string
  alt: string
  to: string
  /** From the API: true when picked in the admin, false for a product photo. */
  chosen?: boolean
}

export type MegaMenu = {
  columns: { heading: string, links: NavLink[] }[]
  promos: MegaMenuPromo[]
}

/** `key` matches the menu's key in `GET /navigation/menus` (App\\Support\\MegaMenus). */
export type NavItem = NavLink & { key?: string, accent?: boolean, menu?: MegaMenu }

// Row 2 of the header: the non-shopping destinations.
//
// `Bookings` and `Stories` are the approved Template B mockup's own labels.
// `Shop` stays even though the mockup has no plain "Shop" item: row 3 only
// offers *filtered* entries to the catalogue, and dropping the one unfiltered
// way in would be a usability regression, not a design decision.
export const primaryNav: NavLink[] = [
  { label: 'Shop', to: '/shop' },
  { label: 'Bookings', to: '/booking' },
  { label: 'Stories', to: '/blog' },
  // `Sustainability` used to sit beside this. It is a section of About now
  // (27 Aug), the two routes were telling one story, and `/sustainability`
  // 301s here.
  { label: 'About', to: '/about' },
]

/** Merges extra query params into a base route, preserving what's already there. */
function withParams(baseTo: string, params: Record<string, string>): string {
  const [path, existing] = baseTo.split('?')
  const search = new URLSearchParams(existing)
  for (const [key, value] of Object.entries(params)) search.set(key, value)
  const query = search.toString()
  return query ? `${path}?${query}` : path!
}

// Both promo tiles come from the Figma Mens panel. They only show if the API
// can't be reached: normally each menu's tiles are chosen in the admin
// (Content › Menus), or are that menu's own product photos.
const promoArtwork = {
  ahenema: { image: '/design/menu-ahenema.png', alt: 'Blue and black leather ahenema sandals' },
  closedToe: { image: '/design/menu-closed-toe.png', alt: 'Closed-toe leather shoes in tan and black' },
}

/**
 * The stand-in for a menu when `GET /navigation/menus` can't be reached
 * (`composables/useMegaMenus.ts`). The live menus are built by the API from
 * the catalogue, so they only offer styles and departments that have
 * products; this one can't know that, so it keeps to the three styles the
 * catalogue is made of and the sorts `/shop` honours. ("Top Rated" was
 * dropped: there are no ratings, and the sort was ignored.)
 *
 * Rebasing onto the menu's own route is what makes one template serve every
 * item: opened from Sale, "New Arrivals" means new sale items.
 */
function createShopMenu(baseTo: string, allLabel: string): MegaMenu {
  return {
    columns: [
      {
        heading: 'Styles',
        links: [
          { label: allLabel, to: baseTo },
          { label: 'Slippers', to: withParams(baseTo, { type: 'slippers' }) },
          { label: 'Ahenema', to: withParams(baseTo, { type: 'ahenema' }) },
          { label: 'Closed-Toe Shoes', to: withParams(baseTo, { type: 'closed-toe' }) },
        ],
      },
      {
        heading: 'Featured',
        links: [
          { label: 'New Arrivals', to: withParams(baseTo, { sort: 'newest' }) },
          { label: 'Best Sellers', to: withParams(baseTo, { sort: 'best-selling' }) },
          // Custom work is a booking, not a catalogue filter.
          { label: 'Customised Sandals', to: '/booking' },
        ],
      },
    ],
    promos: [
      { label: 'Ahenema', ...promoArtwork.ahenema, to: withParams(baseTo, { type: 'ahenema', sort: 'newest' }) },
      { label: 'Closed-Toe Shoes', ...promoArtwork.closedToe, to: withParams(baseTo, { type: 'closed-toe' }) },
    ],
  }
}

/**
 * Row 3: the catalogue entry points from the approved Template B mockup:
 * Best Sellers, Sandals, Ahenema, plus Sale (sale pricing is built, and the
 * `sale` colour token exists for this item).
 *
 * "Sandals" is the whole range. Everything the brand makes is a sandal, and
 * no product is typed `sandals` (the catalogue's styles are slippers, ahenema
 * and closed-toe), so `?type=sandals` was an empty page.
 *
 * Each item's menu is replaced by the API's (`useMegaMenus`), matched on `key`.
 */
export const categoryNav: NavItem[] = [
  {
    key: 'best-sellers',
    label: 'Best Sellers',
    to: '/shop?sort=best-selling',
    menu: createShopMenu('/shop?sort=best-selling', 'All Best Sellers'),
  },
  {
    key: 'sandals',
    label: 'Sandals',
    to: '/shop',
    menu: createShopMenu('/shop', 'All Sandals'),
  },
  {
    key: 'ahenema',
    label: 'Ahenema',
    to: '/shop?type=ahenema',
    menu: createShopMenu('/shop?type=ahenema', 'All Ahenema'),
  },
  {
    key: 'sale',
    label: 'Sale',
    to: '/shop?sale=true',
    accent: true,
    menu: createShopMenu('/shop?sale=true', 'Everything on Sale'),
  },
]

/**
 * The About page's own section nav (Figma 6:554).
 *
 * Since Sustainability was merged into About (27 Aug) this is what moves a
 * reader between the parts of one long page, which is what a section nav is
 * for. Before the merge, four of its seven tabs left the page entirely.
 *
 * Trimmed to six on 27 Aug at the customer's request: Radical Transparency,
 * Our Progress, Our Carbon Commitment and Annual Impact Report came out. The
 * sections those first two named are still on the page and still have their
 * anchors (`#prices`, `#progress`); a section nav is a shortcut list, not a
 * table of contents.
 *
 * Every tab now resolves to something real, so nothing here is flagged
 * `placeholder` any more; the last user of that flag was the Annual Impact
 * Report, which was pointing at the nearest existing section because the report
 * itself has never been designed.
 */
export const aboutSectionNav: NavLink[] = [
  // Since the 23 Oct redesign; the old tabs pointed at sections that are gone
  // (Our Workshop → the "ethical factories" block, Designed to Last).
  { label: 'Our Story', to: '/about#story' },
  { label: 'Materials', to: '/about#sustainability' },
  { label: 'People', to: '/about#people' },
  { label: 'Experiences', to: '/about#experiences' },
  { label: 'Watch', to: '/about#watch' },
  { label: 'Recognition', to: '/about#recognition' },
  { label: 'The Latest', to: '/about#stories' },
]

/**
 * The account section's own tab row, consumed by `AccountShell` the same way
 * `aboutSectionNav` is consumed by `AboutSectionNav`.
 *
 * Sign-out is not a link and so is not here; it is an action, and it lives in
 * the shell's footer.
 */
export const accountNav: NavLink[] = [
  { label: 'Overview', to: '/account' },
  { label: 'Orders', to: '/account/orders' },
  { label: 'Settings', to: '/account/settings' },
]
