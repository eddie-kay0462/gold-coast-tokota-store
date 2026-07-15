import { defineStore } from 'pinia'

export type AdminRole = 'admin' | 'staff'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as { id: string; name: string; email: string } | null,
    role: null as AdminRole | null,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    isAdmin: (state) => state.role === 'admin',
  },
  actions: {
    setSession(user: { id: string; name: string; email: string }, role: AdminRole) {
      this.user = user
      this.role = role
    },
    clearSession() {
      this.user = null
      this.role = null
    },
  },
})
