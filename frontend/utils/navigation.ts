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
  /**
   * True when the menu's copy and promo artwork are stand-ins awaiting design
   * sign-off. Only `Mens` is drawn in Figma (node 6:368); every other category
   * reuses that structure verbatim. Grep this flag to find what still needs
   * real content before launch.
   */
  placeholder?: boolean
}

export type NavItem = NavLink & { accent?: boolean, menu?: MegaMenu }

// Row 2 of the header. `Sustainability` has no route in the spec's page map
// yet, so it points at the About page's sustainability anchor.
export const primaryNav: NavLink[] = [
  { label: 'News & Events', to: '/blog' },
  { label: 'Shop', to: '/shop' },
  { label: 'About', to: '/about' },
  { label: 'Sustainability', to: '/about#sustainability' },
]

/** Merges extra query params into a base route, preserving what's already there. */
function withParams(baseTo: string, params: Record<string, string>): string {
  const [path, existing] = baseTo.split('?')
  const search = new URLSearchParams(existing)
  for (const [key, value] of Object.entries(params)) search.set(key, value)
  const query = search.toString()
  return query ? `${path}?${query}` : path!
}

// Both promo tiles come from the Mens panel. Categories without their own
// artwork reuse them so the layout is representative; swap per category once
// real photography exists.
const promoArtwork = {
  ahenema: { image: '/design/menu-ahenema.png', alt: 'Blue and black leather ahenema sandals' },
  closedToe: { image: '/design/menu-closed-toe.png', alt: 'Closed-toe leather shoes in tan and black' },
}

/**
 * Builds the two-column + two-promo panel drawn in Figma, rebased onto a given
 * category route. Every category gets the identical structure; only the route
 * each link resolves to and the gendered "Top Rated" label differ.
 */
function createCategoryMenu(
  baseTo: string,
  options: { topRatedLabel: string, placeholder?: boolean },
): MegaMenu {
  return {
    placeholder: options.placeholder,
    columns: [
      {
        heading: 'Highlights',
        links: [
          { label: 'Shop All New Arrivals', to: withParams(baseTo, { sort: 'newest' }) },
          { label: 'The Gift Guide', to: withParams(baseTo, { collection: 'gift-guide' }) },
          { label: 'New Ahenema', to: withParams(baseTo, { collection: 'new-ahenema' }) },
          { label: 'New Shoes', to: withParams(baseTo, { collection: 'new-shoes' }) },
          { label: 'Product Bundles', to: withParams(baseTo, { collection: 'bundles' }) },
          { label: 'Under ₵200', to: withParams(baseTo, { max_price: '200' }) },
        ],
      },
      {
        heading: 'Featured Shops',
        links: [
          { label: 'Latest and Greatest Ahenema', to: withParams(baseTo, { collection: 'latest-ahenema' }) },
          { label: 'Closed-Toe Shoes', to: withParams(baseTo, { collection: 'closed-toe' }) },
          { label: 'Customised Sandals', to: '/booking' },
          { label: 'Best Sellers', to: withParams(baseTo, { sort: 'best-selling' }) },
          { label: options.topRatedLabel, to: withParams(baseTo, { sort: 'top-rated' }) },
        ],
      },
    ],
    promos: [
      {
        label: 'Latest and Greatest Ahenema',
        ...promoArtwork.ahenema,
        to: withParams(baseTo, { collection: 'latest-ahenema' }),
      },
      {
        label: 'Closed-Toe\nShoes',
        ...promoArtwork.closedToe,
        to: withParams(baseTo, { collection: 'closed-toe' }),
      },
    ],
  }
}

// Row 3 — category entry points. `Mens` matches the Figma frame exactly; the
// rest are placeholders built from the same template (see MegaMenu.placeholder).
export const categoryNav: NavItem[] = [
  {
    label: 'Mens',
    to: '/shop?category=mens',
    menu: createCategoryMenu('/shop?category=mens', { topRatedLabel: 'Top Rated Men’s Sandals' }),
  },
  {
    label: 'Womens',
    to: '/shop?category=womens',
    menu: createCategoryMenu('/shop?category=womens', {
      topRatedLabel: 'Top Rated Women’s Sandals',
      placeholder: true,
    }),
  },
  {
    label: 'Kids',
    to: '/shop?category=kids',
    menu: createCategoryMenu('/shop?category=kids', {
      topRatedLabel: 'Top Rated Kids’ Sandals',
      placeholder: true,
    }),
  },
  {
    label: 'New Arrivals',
    to: '/shop?sort=newest',
    menu: createCategoryMenu('/shop?sort=newest', {
      topRatedLabel: 'Top Rated New Arrivals',
      placeholder: true,
    }),
  },
  {
    label: 'Best-Sellers',
    to: '/shop?sort=best-selling',
    menu: createCategoryMenu('/shop?sort=best-selling', {
      topRatedLabel: 'Top Rated Best-Sellers',
      placeholder: true,
    }),
  },
  {
    label: 'Merchandise',
    to: '/shop?category=merchandise',
    menu: createCategoryMenu('/shop?category=merchandise', {
      topRatedLabel: 'Top Rated Merchandise',
      placeholder: true,
    }),
  },
  {
    label: 'Custom Shoes',
    to: '/booking',
    menu: createCategoryMenu('/booking', {
      topRatedLabel: 'Top Rated Custom Builds',
      placeholder: true,
    }),
  },
  {
    label: 'Sale',
    to: '/shop?sale=true',
    accent: true,
    menu: createCategoryMenu('/shop?sale=true', {
      topRatedLabel: 'Top Rated Sale Items',
      placeholder: true,
    }),
  },
]
