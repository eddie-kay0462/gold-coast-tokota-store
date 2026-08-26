import { LEGAL_SLUGS, HELP_SLUGS } from '~/utils/policyContent'

/**
 * Dynamic sitemap entries for the two `[slug]` prose templates.
 *
 * `@nuxtjs/sitemap` discovers static `pages/` files on its own but cannot
 * enumerate route params, so the legal and help articles would otherwise be
 * missing from sitemap.xml entirely. This lives in `server/api/` rather than in
 * `nuxt.config.ts` because the config runs outside the app's alias resolution
 * and cannot import from `~/utils`.
 *
 * Blog posts and products are absent from the sitemap for the same reason and
 * still need the same treatment — see FOR_THE_TEAM.md.
 */
export default defineSitemapEventHandler(() => [
  ...LEGAL_SLUGS.map((slug) => ({ loc: `/legal/${slug}`, _sitemap: 'pages' as const })),
  ...HELP_SLUGS.map((slug) => ({ loc: `/help/${slug}`, _sitemap: 'pages' as const })),
])
