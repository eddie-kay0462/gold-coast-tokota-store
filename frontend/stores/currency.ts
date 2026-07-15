import { defineStore } from 'pinia'
import type { Currency } from '~/utils/constants'

export const useCurrencyStore = defineStore('currency', {
  state: () => ({
    active: 'GHS' as Currency,
    // Cached GHS->USD rate; server remains source of truth at checkout.
    fxRate: 0,
    fxRateFetchedAt: null as string | null,
  }),
  actions: {
    setCurrency(currency: Currency) {
      this.active = currency
    },
    setFxRate(rate: number, fetchedAt: string) {
      this.fxRate = rate
      this.fxRateFetchedAt = fetchedAt
    },
  },
})
