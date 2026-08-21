import type { Timestamp } from './common'

export interface BlogPost {
  id: number
  title: string
  slug: string
  excerpt: string
  /** Sanitised HTML from the editor. Server-side sanitisation is mandatory
   *  (README Feature 9 edge cases — stored XSS). */
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
  uploadedByName: string
  uploadedAt: Timestamp
}
