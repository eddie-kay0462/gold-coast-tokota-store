/**
 * Binds the theme to the OS media query on boot.
 *
 * The class itself is already on <html> by this point — the inline script in
 * `nuxt.config.ts` runs before first paint so there is no white flash on a
 * dark-mode reload. This plugin's job is only to keep it in sync afterwards.
 */
export default defineNuxtPlugin(() => {
  const { watchSystem } = useTheme()
  watchSystem()
})
