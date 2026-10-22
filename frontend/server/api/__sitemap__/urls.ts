import { LEGAL_SLUGS, HELP_SLUGS } from '~/utils/policyContent'

/**
 * Dynamic sitemap entries: the `[slug]` pages `@nuxtjs/sitemap` can't
 * discover from the filesystem.
 *
 * - Legal and help articles, from the app's own content list.
 * - Products (`/shop/{slug}`) and blog posts (`/blog/{slug}`), from the API.
 *   Its public lists already leave out inactive products and unpublished
 *   posts, which is what README Feature 10 asks of the sitemap, and the
 *   module caches the result, so the API isn't asked on every crawl.
 *
 * This lives in `server/api/` rather than in `nuxt.config.ts` because the
 * config runs outside the app's alias resolution and cannot import `~/utils`.
 *
 * If the API can't be reached, its entries are left out for that build of the
 * sitemap rather than failing the whole file: the static pages still publish.
 */

interface Page<T> {
  data: T[]
  meta?: { current_page?: number, last_page?: number }
}

/**
 * Every page of a paginated list, stopping at a sane ceiling. `size` is the
 * endpoint's own page-size parameter: products take `per_page` (up to 48),
 * blog posts `limit` (up to 24).
 */
async function everyPage<T>(url: string, size: Record<string, number>): Promise<T[]> {
  const rows: T[] = []
  for (let page = 1; page <= 50; page++) {
    const res = await $fetch<Page<T>>(url, { query: { page, ...size } })
    rows.push(...res.data)
    if (!res.meta?.last_page || page >= res.meta.last_page) break
  }
  return rows
}

export default defineSitemapEventHandler(async () => {
  const apiBase = useRuntimeConfig().public.apiBase as string

  const [products, posts] = await Promise.all([
    everyPage<{ slug: string, updated_at?: string | null }>(`${apiBase}/products`, { per_page: 48 }).catch(() => []),
    everyPage<{ slug: string, published_at?: string | null }>(`${apiBase}/blog-posts`, { limit: 24 }).catch(() => []),
  ])

  return [
    ...LEGAL_SLUGS.map((slug) => ({ loc: `/legal/${slug}`, _sitemap: 'pages' as const })),
    ...HELP_SLUGS.map((slug) => ({ loc: `/help/${slug}`, _sitemap: 'pages' as const })),
    ...products.map((p) => ({ loc: `/shop/${p.slug}`, lastmod: p.updated_at ?? undefined, _sitemap: 'pages' as const })),
    ...posts.map((p) => ({ loc: `/blog/${p.slug}`, lastmod: p.published_at ?? undefined, _sitemap: 'pages' as const })),
  ]
})
