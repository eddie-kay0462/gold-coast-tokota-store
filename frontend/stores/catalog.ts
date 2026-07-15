import { defineStore } from 'pinia'

export const useCatalogStore = defineStore('catalog', {
  state: () => ({
    products: [] as Record<string, unknown>[],
    categories: [] as Record<string, unknown>[],
    lastFetchedAt: null as number | null,
  }),
  getters: {
    // Short TTL invalidation, per README State Management section.
    isStale: (state) => !state.lastFetchedAt || Date.now() - state.lastFetchedAt > 60_000,
  },
  actions: {
    setProducts(products: Record<string, unknown>[]) {
      this.products = products
      this.lastFetchedAt = Date.now()
    },
  },
})
