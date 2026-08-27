import type { ApiProduct } from '~/utils/catalog'
import { useCartStore } from '~/stores/cart'

/**
 * Adds a product variant to the cart, fires the GA4 event, and opens the
 * sidecart as confirmation.
 *
 * Shared because two places now do it — the product detail panel and, since the
 * approved Template B design put sizes on the card, the product card in the
 * listing grid. The synthetic `inventoryItemId` in particular has to match
 * between them, or the same pair added from the grid and from the detail page
 * would sit in the cart as two separate lines.
 */
export function useAddToCart() {
  const cart = useCartStore()
  const { addToCart: addToCartEvent } = useAnalytics()

  return function addToCart(
    product: ApiProduct,
    { size, color, quantity = 1 }: { size?: string | null, color?: string | null, quantity?: number },
  ) {
    cart.addItem({
      productId: product.slug,
      // Real inventory item ids arrive with Feature 2/3; until then the variant
      // key keeps distinct size/colour selections as separate cart lines.
      inventoryItemId: [product.slug, size ?? '', color ?? ''].join(':'),
      slug: product.slug,
      name: product.name,
      image: product.images?.[0],
      variantLabel: [size, color].filter(Boolean).join(' | ') || undefined,
      quantity,
      unitPriceGhs: product.base_price_ghs,
      compareAtGhs: product.compare_at_ghs,
    })

    addToCartEvent({
      currency: 'GHS',
      value: (product.base_price_ghs * quantity) / 100,
      items: [{ item_id: product.slug, item_name: product.name, quantity }],
    })

    // Opening the sidecart is the confirmation that the add worked, and puts
    // checkout one click away.
    cart.openDrawer()
  }
}
