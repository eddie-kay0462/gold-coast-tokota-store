import { useAuthStore } from '~/stores/auth'

/**
 * Keeps a page for signed-in customers: anyone else goes to sign in and comes
 * back here afterwards. Waits for the session to be restored first, so a
 * signed-in customer opening the page directly is not bounced.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (import.meta.server) return

  const store = useAuthStore()

  if (!store.restored) {
    await new Promise<void>((resolve) => {
      const stop = watch(() => store.restored, (done) => {
        if (done) { stop(); resolve() }
      }, { immediate: true })
    })
  }

  if (!store.isAuthenticated) {
    return navigateTo({ path: '/account/login', query: { redirect: to.fullPath } })
  }
})
