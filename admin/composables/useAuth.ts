import { useAuthStore } from '~/stores/auth'

export function useAuth() {
  const store = useAuthStore()
  return {
    user: computed(() => store.user),
    role: computed(() => store.role),
    isAuthenticated: computed(() => store.isAuthenticated),
    isAdmin: computed(() => store.isAdmin),
  }
}
