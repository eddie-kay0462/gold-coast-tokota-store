// https://nuxt.com/docs/api/configuration/nuxt-config

// Runs before first paint, inlined into <head>. Without it a dark-mode user
// gets a white flash on every reload while Nuxt hydrates and applies the class.
// Kept deliberately tiny and dependency-free; it duplicates a few lines of
// `composables/useTheme.ts` on purpose, because it cannot import anything.
const themeBootScript = `
(function () {
  try {
    var m = document.cookie.match(/(?:^|;\\s*)gct-admin-theme=([^;]*)/);
    var pref = m ? decodeURIComponent(m[1]) : 'system';
    var dark = pref === 'dark' || (pref !== 'light' &&
      window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
  } catch (e) {}
})();
`.trim()

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

  app: {
    head: {
      titleTemplate: '%s · Gold Coast Tokota Admin',
      title: 'Dashboard',
      htmlAttrs: { lang: 'en' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        // The dashboard is private; keep it out of indexes even though it
        // sits on its own domain and is never linked from the storefront.
        { name: 'robots', content: 'noindex, nofollow' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico' },
        { rel: 'apple-touch-icon', href: '/brand/apple-touch-icon.png' },
      ],
      script: [{ innerHTML: themeBootScript, tagPosition: 'head' }],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
      // How the admin app sources data while the Laravel admin API is still
      // being built (README Feature 9 — none of /api/v1/admin/* exists yet):
      //   auto     — try the real endpoint, fall back to fixtures (default)
      //   live     — real API only; failures surface as errors
      //   fixtures — never call the API
      // See composables/useAdminApi.ts.
      adminData: process.env.NUXT_PUBLIC_ADMIN_DATA || 'auto',
    },
  },

  devServer: {
    port: 3001,
  },
})
