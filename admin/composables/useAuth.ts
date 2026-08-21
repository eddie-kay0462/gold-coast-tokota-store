import type { AdminRole } from '~/types'
import { sessionFromAdminUser, useAuthStore } from '~/stores/auth'
import { adminUsers } from '~/fixtures'
import { denialMessage, ROLE_DESCRIPTIONS, type Capability } from '~/utils/permissions'
import { daysUntil } from '~/utils/formatters'

/**
 * Session, permissions and the intern access window.
 *
 * `login()` is present and typed, but throws rather than pretending: the
 * endpoint it would call does not exist. When README Feature 9 lands, the body
 * becomes a `sanctum/csrf-cookie` call followed by a POST, and nothing else in
 * the app changes.
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

  async function login(_email: string, _password: string): Promise<never> {
    throw createError({
      statusCode: 501,
      statusMessage:
        'Sign-in is not enabled yet — the admin login endpoint has not been built. ' +
        'See README Feature 9.',
    })
  }

  async function logout() {
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
    login,
    logout,
  }
}
