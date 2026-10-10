/**
 * Resolves the admin session before the first route renders.
 *
 * Against a real API this asks `GET /admin/me` whether the Sanctum cookie is
 * still good; `middleware/auth.global.ts` then sends a signed-out user to
 * /login. In `fixtures` data mode there is nothing to authenticate against, so
 * a demo session is seeded to keep the dashboard reviewable offline.
 */
export default defineNuxtPlugin(async () => {
  const auth = useAuth()
  if (auth.usesApi) await auth.restoreSession()
  else auth.ensureDemoSession()
})
