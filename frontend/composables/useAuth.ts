import { useAuthStore, type CustomerSession } from '~/stores/auth'

/**
 * Customer accounts, on the API's `web` guard (Sanctum SPA cookie auth).
 *
 * `useApi()` does the CSRF handshake and sends `credentials: 'include'`, so
 * every call here carries the session. Accounts are optional throughout:
 * guest checkout stays the default (README Feature 4), and a signed-in
 * customer's checkout is attached to their account by the session alone.
 *
 * The session is restored once per visit by `plugins/auth.client.ts`
 * (`GET /me`), and `middleware/auth.ts` keeps the order history and settings
 * pages for signed-in customers.
 */

/** Kept for the pages that read it; accounts are live. */
export const AUTH_ENABLED = true

/**
 * Every account action answers the same way: `ok`, or field errors keyed by
 * the form's field names, plus one line for a notice.
 */
export type AuthResult =
  | { ok: true, message?: string }
  | { ok: false, notice: string, errors: Record<string, string> }

interface ApiError {
  statusCode?: number
  data?: { message?: string, errors?: Record<string, string[]> }
}

/** Laravel's 422 and 429 bodies, turned into what a form shows. */
function failure(err: unknown, fallback: string): AuthResult {
  const e = err as ApiError
  const errors: Record<string, string> = {}

  for (const [field, messages] of Object.entries(e.data?.errors ?? {})) {
    if (messages?.[0]) errors[field] = messages[0]
  }

  const notice = e.statusCode === 429
    ? 'Too many attempts. Wait a minute, then try again.'
    : !e.statusCode
      ? 'We couldn’t reach our server. Check your connection and try again.'
      : (Object.values(errors)[0] ?? e.data?.message ?? fallback)

  return { ok: false, notice, errors }
}

export function useAuth() {
  const store = useAuthStore()
  const api = useApi()

  async function signIn(credentials: { email: string, password: string, remember?: boolean }): Promise<AuthResult> {
    try {
      const res = await api<{ data: CustomerSession }>('/login', { method: 'POST', body: credentials })
      store.setSession(res.data)
      return { ok: true }
    } catch (err) {
      return failure(err, 'We couldn’t sign you in. Check your details and try again.')
    }
  }

  async function register(payload: {
    first_name: string
    last_name: string
    email: string
    phone?: string | null
    preferred_currency?: 'GHS' | 'USD'
    password: string
    password_confirmation: string
  }): Promise<AuthResult> {
    try {
      const res = await api<{ data: CustomerSession }>('/register', { method: 'POST', body: payload })
      // The API signs a new customer straight in.
      store.setSession(res.data)
      return { ok: true }
    } catch (err) {
      return failure(err, 'We couldn’t create your account. Check the form and try again.')
    }
  }

  async function signOut() {
    try {
      await api('/logout', { method: 'POST' })
    } catch {
      // Already gone on the server; clearing here is all that is left.
    }
    store.clearSession()
    await navigateTo('/')
  }

  /** Asks the API who is signed in. Called once per visit by the auth plugin. */
  async function restore() {
    try {
      const res = await api<{ data: CustomerSession }>('/me')
      store.setSession(res.data)
    } catch {
      store.clearSession()
    } finally {
      store.markRestored()
    }
  }

  async function forgotPassword(email: string): Promise<AuthResult> {
    try {
      // The API answers the same whether or not the email has an account.
      const res = await api<{ message: string }>('/forgot-password', { method: 'POST', body: { email } })
      return { ok: true, message: res.message }
    } catch (err) {
      return failure(err, 'We couldn’t send the link. Try again in a moment.')
    }
  }

  async function resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<AuthResult> {
    try {
      const res = await api<{ message?: string }>('/reset-password', { method: 'POST', body: payload })
      return { ok: true, message: res.message }
    } catch (err) {
      return failure(err, 'We couldn’t reset your password. The link may have expired.')
    }
  }

  return {
    user: computed(() => store.user),
    isAuthenticated: computed(() => store.isAuthenticated),
    restored: computed(() => store.restored),
    signIn,
    register,
    signOut,
    restore,
    forgotPassword,
    resetPassword,
  }
}
