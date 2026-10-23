import type { Timestamp } from './common'

export interface BlogPost {
  id: number
  title: string
  slug: string
  excerpt: string
  /** Sanitised HTML from the editor. Server-side sanitisation is mandatory
   *  (README Feature 9 edge cases: stored XSS). */
  body: string
  coverImage: string | null
  coverImageAlt: string
  metaDescription: string
  authorName: string
  isPublished: boolean
  publishedAt: Timestamp | null
  updatedAt: Timestamp
}

/**
 * CMS pages. README only names `about`; the brand PDF specifies four more
 * legal/policy pages the storefront needs and the owner must be able to edit
 * without a deploy. All five are the same `Page` resource, different slugs.
 */
export type PageSlug =
  | 'about'
  | 'shipping-and-delivery'
  | 'returns-and-exchanges'
  | 'privacy-policy'
  | 'terms-of-service'

export interface CmsPage {
  id: number
  slug: PageSlug
  title: string
  body: string
  /** Policy pages are legally load-bearing; flag which ones are. */
  isPolicy: boolean
  updatedByAdminName: string | null
  updatedAt: Timestamp
}

export interface MediaAsset {
  id: number
  filename: string
  url: string
  mimeType: string
  sizeBytes: number
  width: number | null
  height: number | null
  altText: string
  /** Null for photos put there by `catalogue:import-media`. */
  uploadedByName: string | null
  uploadedAt: Timestamp
}

/** One image tile slot in a storefront mega menu (Content › Menus). */
export interface MegaMenuSlot {
  position: number
  /** Null when nobody has chosen an image: the storefront shows a product photo. */
  mediaAsset: { id: number, url: string, filename: string, altText: string } | null
  caption: string
  link: string
}

/** A tile as the storefront shows it right now. */
export interface MegaMenuTile {
  label: string
  image: string
  alt: string
  to: string
  /** False for a product photo filling an empty slot. */
  chosen: boolean
}

/** `GET /admin/menus`: a header menu, its tiles, and its catalogue-built links. */
export interface MegaMenuAdmin {
  key: string
  label: string
  to: string
  slots: MegaMenuSlot[]
  showing: MegaMenuTile[]
  columns: { heading: string, links: { label: string, to: string }[] }[]
  linkOptions: { label: string, to: string }[]
}

// --- About page (Content › Pages › About; App\Support\AboutPage) -----------

interface AboutSection { visible: boolean }
interface AboutImageBlock {
  /** The default, a storefront path. Not editable. */
  image: string
  /** A media library image chosen instead, or null for the default. */
  imageId: number | null
  imageAlt: string
  /** Resolved by the API: the chosen image, else the default. */
  imageUrl?: string
}

export interface AboutVideoPost {
  platform: 'youtube' | 'instagram'
  id: string
  title: string
  meta: string
  shape: 'portrait' | 'landscape'
  thumb: 'oardefault' | 'maxresdefault' | null
}

export interface AboutContent {
  hero: AboutSection & AboutImageBlock & { eyebrow: string; heading: string; lede: string }
  statement: AboutSection & { text: string }
  story: AboutSection & { eyebrow: string; heading: string; paragraphs: string[]; quote: string; quoteBy: string; quoteSource: string; quoteUrl: string }
  figures: AboutSection & { items: { value: string; label: string; source: string; href: string }[] }
  materials: AboutSection & { eyebrow: string; heading: string; body: string; items: { name: string; body: string }[] }
  people: AboutSection & AboutImageBlock & { eyebrow: string; heading: string; body: string; caption: string; team: { role: string; name: string }[] }
  ahenema: AboutSection & AboutImageBlock & { eyebrow: string; heading: string; body: string }
  experiences: AboutSection & { eyebrow: string; heading: string; body: string; items: { name: string; when: string; detail: string }[] }
  watch: AboutSection & { eyebrow: string; heading: string; body: string; posts: AboutVideoPost[] }
  slogans: AboutSection & { items: string[] }
  recognition: AboutSection & { eyebrow: string; heading: string; items: { name: string; detail: string; date: string; href: string }[] }
  mission: AboutSection & { heading: string; lede: string; mission: string; vision: string }
  socials: { instagram: string; youtube: string }
}

/** `GET /admin/pages/about`. */
export interface AboutPageAdmin {
  content: AboutContent
  images: { id: number; filename: string; altText: string }[]
  updatedAt: Timestamp | null
  updatedBy: string | null
}
