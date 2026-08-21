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
    '/checkout': { ssr: false },
    '/order-confirmation/**': { ssr: false },
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
