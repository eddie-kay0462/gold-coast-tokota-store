import { useAuthStore } from '~/stores/auth'

/**
 * Customer session — BUILT BUT DELIBERATELY INACTIVE.
 *
 * The account pages are complete and validate, but submitting does not
 * authenticate. The Laravel side is most of the way there already — `Customer`
 * extends Authenticatable with hashed-password casting, the `web` guard is
 * configured against a `customers` provider, `passwords.customers` is wired to
 * `password_reset_tokens`, Sanctum's `guard` array lists `web`, and
 * `statefulApi()` is on globally — but there is no CustomerAuthController and
 * no routes in `backend/routes/api.php`, so calling one would just produce a
 * confusing 404.
 *
 * When those routes land, turning this on is:
 *   1. flip `AUTH_ENABLED` to true (the account pages' notices read it);
 *   2. replace the three `simulate()` bodies below with
 *      `GET /sanctum/csrf-cookie` then `POST /login` | `/register` | `/logout`,
 *      passing `credentials: 'include'` (no storefront call does today);
 *   3. `store.setSession(response.data)` on success, honour a `redirect` query;
 *   4. add `middleware/auth.ts` and `definePageMeta({ middleware: 'auth' })` to
 *      `pages/account/orders.vue` and `pages/account/settings.vue`.
 *
 * No middleware is registered until then, deliberately: nothing can
 * authenticate, so a guard would make those two pages permanently unreachable.
 * `admin/` sets the same precedent for the same reason.
 */

/**
 * The single switch. Grep it to find every place that has to change when
 * customer auth goes live.
 */
export const AUTH_ENABLED = false

export const AUTH_DISABLED_NOTICE =
  'Accounts aren’t enabled yet. The customer sign-in endpoints haven’t been built on the API ' +
  'side, so this form is inactive for now — you can still order as a guest, and we’ll email ' +
  'your confirmation.'

export type AuthResult = { ok: false, notice: string }

/**
 * Shows the loading state the real call will have, then explains why nothing
 * happened instead of failing with a raw network error. There is no `$fetch`
 * anywhere in this file, and there should not be one until step 2 above.
 */
async function simulate(): Promise<AuthResult> {
  await new Promise((resolve) => setTimeout(resolve, 600))
  return { ok: false, notice: AUTH_DISABLED_NOTICE }
}

export function useAuth() {
  const store = useAuthStore()

  return {
    user: computed(() => store.user),
    isAuthenticated: computed(() => store.isAuthenticated),

    signIn: (_credentials: { email: string, password: string }) => simulate(),
    register: (_payload: { name: string, email: string, password: string }) => simulate(),

    /**
     * Local-only today. Once sessions exist this must also POST /logout, or the
     * server session outlives the client one.
     */
    async signOut() {
      store.clearSession()
      await navigateTo('/')
    },
  }
}
