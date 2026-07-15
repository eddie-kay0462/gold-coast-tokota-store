import { useCurrencyStore } from '~/stores/currency'

export function useCurrency() {
  const store = useCurrencyStore()
  return {
    active: computed(() => store.active),
    fxRate: computed(() => store.fxRate),
    setCurrency: store.setCurrency,
  }
}
