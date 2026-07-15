import { defineStore } from 'pinia'

// Customer-only — admin/staff auth lives in the separate ../admin app.
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as { id: string; name: string; email: string } | null,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
  },
  actions: {
    setSession(user: { id: string; name: string; email: string }) {
      this.user = user
    },
    clearSession() {
      this.user = null
    },
  },
})
