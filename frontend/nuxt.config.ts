import { fileURLToPath } from 'node:url'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss', '@nuxtjs/sitemap'],

  css: ['~/assets/css/main.css'],

  // SSR is the project default (Home, About, Shop, Blog must be crawlable).
  // Cart/Checkout is the one SPA-only carve-out here — the admin dashboard
  // now lives in its own app (../admin), not in this one.
  ssr: true,
  routeRules: {
    // SPA-only: per-visitor, never SSR-cached, never indexed. The pages also
    // carry `robots: 'noindex, nofollow'` — `ssr: false` stops the server
    // rendering content, it does not stop the URL being indexed.
    //
    // The meta tag alone is not enough here: on an `ssr: false` route it is only
    // added after hydration, so a crawler that doesn't run JS never sees it.
    // `X-Robots-Tag` is served with the shell itself and needs no JS.
    '/checkout': { ssr: false, headers: { 'X-Robots-Tag': 'noindex, nofollow' } },
    '/order-confirmation/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex, nofollow' } },
    '/account/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex, nofollow' } },

    // `/help/returns` and `/help/shipping` are canonical — they sit inside the
    // help hub's structure and the footer already used those URLs. The short
    // forms stay alive as permanent redirects so printed cards and packaging
    // inserts keep working. A Nuxt `alias` would instead publish two indexable
    // URLs with identical content.
    '/returns': { redirect: { to: '/help/returns', statusCode: 301 } },
    '/shipping': { redirect: { to: '/help/shipping', statusCode: 301 } },

    // Nothing links to a bare /legal, but a 404 there reads as broken.
    '/legal': { redirect: { to: '/legal/privacy', statusCode: 302 } },

    // About and Sustainability told one story across two routes and merged into
    // `/about` on 27 Aug. Permanent, not a 302: the old URL was indexable and
    // linked from the footer, the home page and the About tab row, so its
    // ranking and any external links should transfer rather than be split.
    '/sustainability': { redirect: { to: '/about#sustainability', statusCode: 301 } },
  },

  app: {
    head: {
      // `viewport-fit=cover` is what makes `env(safe-area-inset-*)` resolve to
      // real values — the fixed WhatsApp button and the cart drawer's checkout
      // footer both sit in the iOS home-indicator band without it.
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'apple-touch-icon', sizes: '180x180', href: '/brand/apple-touch-icon.png' },
      ],
    },
  },

  // Country flags are served straight out of the flag-icons package rather
  // than copied into public/ — one SVG is fetched per visitor, and the repo
  // stays free of ~270 vendored assets.
  nitro: {
    publicAssets: [
      {
        baseURL: 'flags',
        dir: fileURLToPath(new URL('./node_modules/flag-icons/flags/4x3', import.meta.url)),
        maxAge: 60 * 60 * 24 * 365,
      },
    ],
  },

  site: {
    url: 'https://goldcoasttokota.store',
  },

  sitemap: {
    // Authenticated and per-customer routes must never be published. Without
    // this the module auto-discovers every `pages/` file, /account included.
    exclude: ['/account/**', '/checkout', '/order-confirmation/**'],
    // `[slug]` params can't be discovered from the filesystem, so the legal and
    // help articles are supplied by `server/api/__sitemap__/urls.ts` — that
    // handler can import from `~/utils`, which this config file cannot.
    sources: ['/api/__sitemap__/urls'],
  },

  runtimeConfig: {
    // Server-only geo settings. `secret` signs the geo cache cookie — set a
    // real value in production or a restart invalidates every cached lookup.
    geo: {
      secret: process.env.NUXT_GEO_SECRET || 'gct-dev-geo-secret',
      // `{ip}` is substituted with the connecting address. Any provider whose
      // JSON carries a country code works; ipwho.is is keyless and also
      // returns the IP's timezone and org, both of which we use.
      lookupEndpoint: process.env.NUXT_GEO_LOOKUP_ENDPOINT || 'https://ipwho.is/{ip}',
      // Only enable behind a proxy/CDN we control — otherwise a visitor can
      // set X-Forwarded-For themselves and pick their own country.
      trustProxyHeaders: process.env.NUXT_GEO_TRUST_PROXY !== 'false',
    },
    public: {
      geo: {
        // Shown when no signal resolves at all (private network, provider down).
        fallbackCountry: process.env.NUXT_PUBLIC_GEO_FALLBACK_COUNTRY || 'GH',
      },
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
      whatsappNumber: process.env.NUXT_PUBLIC_WHATSAPP_NUMBER || '',
      gaMeasurementId: process.env.NUXT_PUBLIC_GA_MEASUREMENT_ID || '',
    },
  },
})
