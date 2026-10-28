/**
 * The shape of a story from `GET /api/v1/blog-posts`, and its date format.
 *
 * The Figma sample posts that used to live here (and stand in whenever the API
 * failed) were removed on 28 Oct 2026: they showed invented stories as real
 * ones, linked to pages that don't exist, whenever the API was busy.
 */

/** A rendered article is a sequence of rich-text and full-bleed image blocks. */
export type PostBlock =
  | { type: 'html', html: string }
  | { type: 'image', src: string, alt: string }

export type ApiPost = {
  slug: string
  title: string
  /** Deck shown under the title on the article hero. */
  subtitle?: string | null
  /** Pill above the hero title, e.g. "Partnerships". */
  category?: string | null
  /** ISO 8601. Rendered as "21st March 2025". */
  published_at: string
  /** Listing thumbnail. */
  cover_image?: string
  /** Full-bleed article hero; falls back to `cover_image`. */
  hero_image?: string
  /** Opening statement, set large above the article body. */
  lede?: string | null
  blocks?: PostBlock[]
  /** Plain rich-text body, as the CMS will return it. Used when `blocks` is absent. */
  body?: string | null
}

/** "21st March 2025": the date format used throughout the news designs. */
export function formatPostDate(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''

  const day = date.getUTCDate()
  const suffix
    = day % 10 === 1 && day !== 11
      ? 'st'
      : day % 10 === 2 && day !== 12
        ? 'nd'
        : day % 10 === 3 && day !== 13
          ? 'rd'
          : 'th'

  const month = date.toLocaleDateString('en-GB', { month: 'long', timeZone: 'UTC' })
  return `${day}${suffix} ${month} ${date.getUTCFullYear()}`
}
