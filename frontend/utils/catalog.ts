/**
 * Catalogue shapes and listing facets.
 *
 * The facet lists below are transcribed from the Figma listing frame
 * (node 10:2275) and act as the source of truth until Feature 2 ships
 * `GET /api/v1/categories` and colour/size facets from the API.
 */

export type ProductColor = {
  /** Display name, e.g. "Navy Blue". */
  name: string
  /** Swatch fill. */
  hex: string
}

export type ApiProduct = {
  slug: string
  name: string
  /** GHS minor units (pesewas). USD is always derived, never stored. */
  base_price_ghs: number
  /** Was-price in GHS minor units; present only while a product is on sale. */
  compare_at_ghs?: number | null
  images?: string[]
  /** Name of the colourway pictured. */
  color?: string | null
  /** Every colourway the product ships in — rendered as swatches on the card. */
  colors?: ProductColor[]
  /** Sustainability/fulfilment badges, e.g. "CUSTOM MADE". */
  tags?: string[]
  /** Renders as a "Pre-Order" badge and blocks immediate add-to-cart. */
  is_pre_order?: boolean
  /**
   * Product type — what the sidebar's "Category" facet filters on
   * (`ahenema`, `slippers`, …). Distinct from `departments` below.
   */
  product_type?: string | null
  /**
   * Departments the product is merchandised under (`mens`, `womens`, `kids`).
   * This is what the header's category nav links resolve to via `?category=`.
   */
  departments?: string[]
  sizes?: string[]
  widths?: string[]

  // --- Detail-page fields (absent from listing responses) ---
  /** Long-form copy under the buy panel; `description_heading` titles it. */
  description?: string | null
  description_heading?: string | null
  /** e.g. "Model is 6′2″, wearing a size M". */
  model_note?: string | null
  /**
   * Per-size stock, keyed by size. A size missing from the map, or at zero,
   * renders disabled — server-side checks remain the source of truth.
   */
  size_availability?: Record<string, number>
  rating?: ProductRating | null
  reviews?: ProductReview[]
  /**
   * "Transparent Pricing" breakdown — GHS minor units per cost line, in the
   * order they should display.
   */
  cost_breakdown?: { label: string, amount_ghs: number, icon: string }[]
}

export type ProductRating = {
  /** Mean score out of 5. */
  average: number
  count: number
  /** Review count per star, keyed "1".."5". */
  distribution?: Record<string, number>
  /**
   * Fit skew on a 1–5 scale, 3 being true to size. Renders as the
   * "Runs slightly large" meter.
   */
  fit?: number | null
}

export type ProductReview = {
  id: string
  author: string
  verified?: boolean
  rating: number
  title: string
  body: string
  /** ISO 8601; rendered as a relative age ("14 days ago"). */
  created_at: string
  /** Free-form reviewer attributes shown in the left column. */
  attributes?: { label: string, value: string }[]
}

export type Facet = { value: string, label: string }

/** Labelled "Category" in the design; filters on `ApiProduct.product_type`. */
export const TYPE_FACETS: Facet[] = [
  { value: 'all-gender', label: 'Everyone - All Gender Collection' },
  { value: 'accessories', label: 'Accessories & Gift Cards' },
  { value: 'ahenema', label: 'Ahenema' },
  { value: 'closed-toe', label: 'Closed-Toe Shoes' },
  { value: 'merchandise', label: 'Merchandise' },
  { value: 'slippers', label: 'Slippers' },
  { value: 'sandals', label: 'Sandals' },
]

export const COLOR_FACETS: (Facet & { hex: string })[] = [
  { value: 'black', label: 'Black', hex: '#000000' },
  { value: 'blue', label: 'Blue', hex: '#1E4C8F' },
  { value: 'brown', label: 'Brown', hex: '#8B5A2B' },
  { value: 'green', label: 'Green', hex: '#4A5D3A' },
  { value: 'grey', label: 'Grey', hex: '#D9D9D9' },
  { value: 'orange', label: 'Orange', hex: '#E8952B' },
  { value: 'pink', label: 'Pink', hex: '#F2D2D2' },
  { value: 'red', label: 'Red', hex: '#C6212B' },
  { value: 'tan', label: 'Tan', hex: '#C4AE93' },
  { value: 'white', label: 'White', hex: '#FFFFFF' },
  { value: 'navy', label: 'Navy', hex: '#1B2A4A' },
  { value: 'olive', label: 'Olive', hex: '#5A6134' },
]

/** Figma shows sizes grouped under a "Shoes & Slippers" sub-heading. */
export const SIZE_GROUPS: { label: string, sizes: string[] }[] = [
  { label: 'Shoes & Slippers', sizes: ['38', '39', '40', '41', '42', '43', '44', '45'] },
]

/** Star-rating scale used across the product page. */
export const MAX_RATING = 5

export const WIDTH_FACETS: Facet[] = [
  { value: 's', label: 'S' },
  { value: 'm', label: 'M' },
  { value: 'l', label: 'L' },
]

/** How many rows of each facet group show before "View More +" is needed. */
export const TYPE_COLLAPSED_COUNT = 5
export const COLOR_COLLAPSED_ROWS = 3
