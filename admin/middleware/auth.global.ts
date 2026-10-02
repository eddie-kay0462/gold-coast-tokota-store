/**
 * Every page except /login needs a session. The API is the real boundary —
 * each `/admin/*` route carries a `capability:` check — so this only spares a
 * signed-out user a dashboard full of 401s.
 */
/** Same-app paths only — `?redirect=//evil.example` must not leave the dashboard. */
function safeRedirect(value: unknown): string {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : '/'
}

export default defineNuxtRouteMiddleware((to) => {
  const { isAuthenticated, isDemoSession } = useAuth()
  const signedIn = isAuthenticated.value || isDemoSession.value

  if (to.path === '/login') {
    if (signedIn) return navigateTo(safeRedirect(to.query.redirect))
    return
  }

  if (!signedIn) {
    return navigateTo({ path: '/login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } })
  }
})
