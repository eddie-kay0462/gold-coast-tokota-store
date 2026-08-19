import { useCartStore } from '~/stores/cart'

/**
 * Restores the cookie-persisted cart before the first render, so a returning
 * customer's cart is already populated in the server-rendered HTML.
 */
export default defineNuxtPlugin(() => {
  useCartStore().hydrate()
})
