import type { AdminRole } from '~/types'
import { sessionFromAdminUser, useAuthStore, type AdminSession } from '~/stores/auth'
import { adminUsers } from '~/fixtures'
import { denialMessage, ROLE_DESCRIPTIONS, type Capability } from '~/utils/permissions'
import { daysUntil } from '~/utils/formatters'
import { apiOrigin, xsrfToken, type DataMode } from '~/composables/useAdminApi'

/** `AdminUserResource`, as `GET /admin/me` and `POST /admin/login` return it. */
interface ApiAdminUser {
  id: number
  name: string
  email: string
  job_title: string | null
  avatar: string | null
  role: AdminRole
  access_expires_at: string | null
}

function sessionFromApi(u: ApiAdminUser): AdminSession {
  return {
    id: u.id,
    name: u.name,
    email: u.email,
    jobTitle: u.job_title ?? '',
    avatar: u.avatar,
    role: u.role,
    accessExpiresAt: u.access_expires_at,
  }
}

/**
 * Session, permissions and the intern access window.
 *
 * Sanctum SPA cookie auth against the `admin` guard: `GET /sanctum/csrf-cookie`,
 * then `POST /admin/login`; `GET /admin/me` restores the session on reload.
 * In `fixtures` data mode there is no API to sign in to, so a demo session is
 * seeded instead and the app stays reviewable offline.
 */
export function useAuth() {
  const store = useAuthStore()

  /** Seeds a reviewable session while there is no login flow. */
  function ensureDemoSession() {
    if (store.user) return
    const founder = adminUsers.find((u) => u.role === 'super_admin') ?? adminUsers[0]!
    store.setDemoSession(sessionFromAdminUser(founder))
  }

  function can(capability: Capability): boolean {
    return store.capabilities.includes(capability)
  }

  function canAny(...capabilities: Capability[]): boolean {
    return capabilities.some(can)
  }

  function whyNot(capability: Capability): string {
    if (store.hasLapsed) {
      return 'This account’s access period has ended, so it is read-only. An Admin can extend it from the Team page.'
    }
    return denialMessage(capability, store.effectiveRole)
  }

  const accessDaysRemaining = computed(() => daysUntil(store.accessExpiresAt))

  /** Drives the countdown banner. Warns from two weeks out. */
  const accessWarningLevel = computed<'none' | 'notice' | 'urgent' | 'lapsed'>(() => {
    const d = accessDaysRemaining.value
    if (d === null) return 'none'
    if (d < 0) return 'lapsed'
    if (d <= 3) return 'urgent'
    if (d <= 14) return 'notice'
    return 'none'
  })

  const config = useRuntimeConfig()
  const base = config.public.apiBase as string
  const dataMode = ((config.public.adminData as DataMode) || 'auto')
  const usesApi = dataMode !== 'fixtures'

  function writeHeaders(): Record<string, string> {
    const token = xsrfToken()
    return token
      ? { Accept: 'application/json', 'X-XSRF-TOKEN': token }
      : { Accept: 'application/json' }
  }

  /** Resolves the current session from the API cookie. False if signed out. */
  async function restoreSession(): Promise<boolean> {
    try {
      const res = await $fetch<{ data: ApiAdminUser }>(`${base}/admin/me`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
        retry: 0,
      })
      store.setSession(sessionFromApi(res.data))
      return true
    } catch {
      store.clearSession()
      return false
    }
  }

  /**
   * Throws a FetchError on failure; a 422 carries Laravel's message (bad
   * credentials or the 5-attempt lockout) under `data.errors.email`.
   */
  async function login(email: string, password: string, remember = false): Promise<void> {
    await $fetch(`${apiOrigin(base)}/sanctum/csrf-cookie`, { credentials: 'include', retry: 0 })
    const res = await $fetch<{ data: ApiAdminUser }>(`${base}/admin/login`, {
      method: 'POST',
      body: { email, password, remember },
      credentials: 'include',
      headers: writeHeaders(),
      retry: 0,
    })
    store.setSession(sessionFromApi(res.data))
  }

  async function logout() {
    if (usesApi && store.isAuthenticated) {
      try {
        await $fetch(`${base}/admin/logout`, {
          method: 'POST',
          credentials: 'include',
          headers: writeHeaders(),
          retry: 0,
        })
      } catch {
        // Already expired server-side; clearing locally is all that is left.
      }
    }
    store.clearSession()
    await navigateTo('/login')
  }

  return {
    user: computed(() => store.user),
    role: computed<AdminRole>(() => store.effectiveRole),
    roleDescription: computed(() => ROLE_DESCRIPTIONS[store.effectiveRole]),
    isAuthenticated: computed(() => store.isAuthenticated),
    isDemoSession: computed(() => store.isDemoSession),
    isImpersonating: computed(() => store.isImpersonating),
    hasLapsed: computed(() => store.hasLapsed),
    accessExpiresAt: computed(() => store.accessExpiresAt),
    accessDaysRemaining,
    accessWarningLevel,
    can,
    canAny,
    whyNot,
    setViewAsRole: (r: AdminRole | null, expiry?: string | null) => store.setViewAsRole(r, expiry),
    ensureDemoSession,
    restoreSession,
    usesApi,
    login,
    logout,
  }
}
