import { defineStore } from 'pinia'

/** `CustomerResource`, as `POST /login`, `POST /register` and `GET /me` return it. */
export interface CustomerSession {
  id: string
  name: string
  email: string
  phone: string | null
  preferred_currency: 'GHS' | 'USD' | null
}

// Customer-only: admin/staff auth lives in the separate ../admin app.
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as CustomerSession | null,
    /**
     * True once `GET /me` has answered (signed in or not). Guards wait for it,
     * so a signed-in customer opening /account/orders directly is not bounced
     * to sign-in in the moment before the session is known.
     */
    restored: false,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
  },
  actions: {
    setSession(user: CustomerSession) {
      this.user = user
      this.restored = true
    },
    clearSession() {
      this.user = null
    },
    markRestored() {
      this.restored = true
    },
  },
})
