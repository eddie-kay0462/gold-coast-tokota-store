import { defineStore } from 'pinia'

export interface CartItem {
  productId: string
  inventoryItemId: string
  name: string
  quantity: number
  unitPriceGhs: number
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [] as CartItem[],
  }),
  getters: {
    subtotalGhs: (state) => state.items.reduce((sum, item) => sum + item.unitPriceGhs * item.quantity, 0),
  },
  actions: {
    addItem(item: CartItem) {
      const existing = this.items.find((i) => i.inventoryItemId === item.inventoryItemId)
      if (existing) {
        existing.quantity += item.quantity
      } else {
        this.items.push(item)
      }
    },
    removeItem(inventoryItemId: string) {
      this.items = this.items.filter((i) => i.inventoryItemId !== inventoryItemId)
    },
    clear() {
      this.items = []
    },
  },
})
