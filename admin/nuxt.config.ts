// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss'],

  css: ['~/assets/css/main.css'],

  // No SEO requirement for the admin dashboard — the whole app is
  // client-only, gated behind Sanctum-authenticated Admin/Staff login.
  // Using routeRules instead of the top-level `ssr: false` — the latter
  // triggers a "No entry found in rollupOptions.input" dev-server crash in
  // this Nuxt/Vite version combo; routeRules achieves the same SPA-only
  // behavior without that bug.
  routeRules: {
    '/**': { ssr: false },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
    },
  },

  devServer: {
    port: 3001,
  },
})
