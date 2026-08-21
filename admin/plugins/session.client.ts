/**
 * Seeds the demo session at boot.
 *
 * Temporary by design: it exists only because there is no login endpoint to
 * authenticate against yet. When Feature 9 lands this plugin is replaced by a
 * `GET /admin/me` call, and `pages/login.vue` stops being inert.
 */
export default defineNuxtPlugin(() => {
  useAuth().ensureDemoSession()
})
