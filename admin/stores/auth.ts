import { defineStore } from 'pinia'
import type { AdminRole, AdminUser } from '~/types'
import { ROLE_CAPABILITIES, type Capability } from '~/utils/permissions'

/**
 * Admin session state.
 *
 * AUTHENTICATION IS NOT WIRED UP. The Laravel side has no login endpoint —
 * no AuthController, no route, no Form Request (README Feature 9 is still
 * outstanding). The login page is built but deliberately inactive, and this
 * store is seeded with a demo session so the dashboard is reviewable.
 *
 * `viewAsRole` exists for that review: it lets someone step through all four
 * permission tiers without four accounts and a working login. It only ever
 * narrows what the UI offers — it cannot widen server access, because the
 * server is the actual boundary.
 */
export interface AdminSession {
  id: number
  name: string
  email: string
  jobTitle: string
  avatar: string | null
  role: AdminRole
  accessExpiresAt: string | null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AdminSession | null,
    /** Overrides the effective role for review purposes. Null = use user.role. */
    viewAsRole: null as AdminRole | null,
    /**
     * Expiry adopted while previewing a role.
     *
     * Without this the preview was half a preview: capabilities changed but
     * `accessExpiresAt` still read the real session's (the founder's, i.e.
     * null), so the intern countdown banner and the lapsed read-only state
     * could never be seen in the running app. Previewing a time-boxed role now
     * adopts a representative expiry too.
     */
    viewAsExpiry: null as string | null,
    viewAsExpirySet: false,
    /** True once a real login flow exists and has succeeded. Always false today. */
    isAuthenticated: false,
    /** Set when the app is running without a real session. */
    isDemoSession: true,
  }),

  getters: {
    /** The role permissions are actually evaluated against. */
    effectiveRole: (state): AdminRole => state.viewAsRole ?? state.user?.role ?? 'intern',

    isImpersonating: (state) =>
      state.viewAsRole !== null && state.viewAsRole !== state.user?.role,

    /**
     * Intern access is time-boxed. Once past the expiry the account keeps its
     * role label but loses every write capability — see `capabilities` below.
     */
    accessExpiresAt: (state): string | null =>
      state.viewAsExpirySet ? state.viewAsExpiry : (state.user?.accessExpiresAt ?? null),

    hasLapsed(): boolean {
      const expiry = this.accessExpiresAt
      return expiry !== null && new Date(expiry).getTime() < Date.now()
    },

    capabilities(): Capability[] {
      const granted = ROLE_CAPABILITIES[this.effectiveRole] ?? []
      // A lapsed account is read-only, whatever its role says.
      return this.hasLapsed ? granted.filter((c) => c.endsWith('.view')) : granted
    },
  },

  actions: {
    setSession(user: AdminSession) {
      this.user = user
      this.isAuthenticated = true
      this.isDemoSession = false
    },

    /** Used at boot while there is no login endpoint to call. */
    setDemoSession(user: AdminSession) {
      this.user = user
      this.isAuthenticated = false
      this.isDemoSession = true
    },

    /**
     * `expiry` is `undefined` to leave the real session's expiry alone, or an
     * explicit value (including null) to adopt one for the preview.
     */
    setViewAsRole(role: AdminRole | null, expiry?: string | null) {
      this.viewAsRole = role
      if (expiry === undefined) {
        this.viewAsExpiry = null
        this.viewAsExpirySet = false
      } else {
        this.viewAsExpiry = expiry
        this.viewAsExpirySet = true
      }
    },

    clearSession() {
      this.user = null
      this.viewAsRole = null
      this.viewAsExpiry = null
      this.viewAsExpirySet = false
      this.isAuthenticated = false
    },
  },
})

/** Narrow an AdminUser fixture down to what a session actually carries. */
export function sessionFromAdminUser(u: AdminUser): AdminSession {
  return {
    id: u.id,
    name: u.name,
    email: u.email,
    jobTitle: u.jobTitle,
    avatar: u.avatar,
    role: u.role,
    accessExpiresAt: u.accessExpiresAt,
  }
}
