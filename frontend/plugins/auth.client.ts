/**
 * Restores the customer's session once per visit (`GET /me`).
 *
 * Client-only: the session cookie belongs to the API's domain and is sent by
 * the browser, so a server render could not see it anyway. Not awaited, so
 * a first paint never waits on the API; `middleware/auth.ts` waits for it
 * where a page actually needs to know.
 */
export default defineNuxtPlugin(() => {
  const { restore } = useAuth()
  restore()
})
