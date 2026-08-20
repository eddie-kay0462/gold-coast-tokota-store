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
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'apple-touch-icon', sizes: '180x180', href: '/brand/apple-touch-icon.png' },
      ],
    },
  },

  site: {
    url: 'https://goldcoasttokota.store',
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
      whatsappNumber: process.env.NUXT_PUBLIC_WHATSAPP_NUMBER || '',
      gaMeasurementId: process.env.NUXT_PUBLIC_GA_MEASUREMENT_ID || '',
    },
  },
})
