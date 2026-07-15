import { useCartStore } from '~/stores/cart'

export function useCart() {
  const store = useCartStore()
  return {
    items: computed(() => store.items),
    subtotalGhs: computed(() => store.subtotalGhs),
    addItem: store.addItem,
    removeItem: store.removeItem,
    clear: store.clear,
  }
}
