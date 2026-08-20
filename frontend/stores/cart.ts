import { defineStore } from 'pinia'

export interface CartItem {
  productId: string
  inventoryItemId: string
  /** Product slug, so a line item can link back to its detail page. */
  slug: string
  name: string
  /** Thumbnail shown in the drawer and checkout summary. */
  image?: string
  /** Variant summary as drawn in the design, e.g. "45 | Black & Blue". */
  variantLabel?: string
  quantity: number
  /** GHS minor units. USD is always derived at display time. */
  unitPriceGhs: number
  /** Was-price in GHS minor units, when the line is discounted. */
  compareAtGhs?: number | null
}

/** Cookie the cart is mirrored into, per the README's State Management notes. */
const CART_COOKIE = 'gct_cart'
const CART_COOKIE_MAX_AGE = 60 * 60 * 24 * 30

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [] as CartItem[],
    /** Sidecart visibility. Lives here so the header, PDP and layout share it. */
    isDrawerOpen: false,
  }),

  getters: {
    subtotalGhs: (state) =>
      state.items.reduce((sum, item) => sum + item.unitPriceGhs * item.quantity, 0),

    /** Subtotal at pre-discount prices, for showing what the cart saves. */
    compareAtSubtotalGhs: (state) =>
      state.items.reduce(
        (sum, item) => sum + (item.compareAtGhs ?? item.unitPriceGhs) * item.quantity,
        0,
      ),

    /** Total units, not line count — the drawer's "(2 items)" and header badge. */
    itemCount: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),

    isEmpty: (state) => state.items.length === 0,
  },

  actions: {
    addItem(item: CartItem) {
      const existing = this.items.find((i) => i.inventoryItemId === item.inventoryItemId)
      if (existing) existing.quantity += item.quantity
      else this.items.push(item)
      this.persist()
    },

    /** Setting a quantity of zero or less removes the line entirely. */
    setQuantity(inventoryItemId: string, quantity: number) {
      if (quantity <= 0) return this.removeItem(inventoryItemId)
      const item = this.items.find((i) => i.inventoryItemId === inventoryItemId)
      if (item) item.quantity = quantity
      this.persist()
    },

    removeItem(inventoryItemId: string) {
      this.items = this.items.filter((i) => i.inventoryItemId !== inventoryItemId)
      this.persist()
    },

    clear() {
      this.items = []
      this.persist()
    },

    openDrawer() {
      this.isDrawerOpen = true
    },

    closeDrawer() {
      this.isDrawerOpen = false
    },

    /**
     * Mirrors the cart into a first-party cookie rather than localStorage, so
     * it is readable during SSR and present on the very first rendered
     * response — a localStorage cart would flash empty until hydration.
     */
    persist() {
      useCookie<CartItem[]>(CART_COOKIE, {
        maxAge: CART_COOKIE_MAX_AGE,
        sameSite: 'lax',
        path: '/',
      }).value = this.items
    },

    /** Called once from a plugin on both server and client. */
    hydrate() {
      const stored = useCookie<CartItem[]>(CART_COOKIE).value
      if (Array.isArray(stored)) this.items = stored
    },
  },
})
