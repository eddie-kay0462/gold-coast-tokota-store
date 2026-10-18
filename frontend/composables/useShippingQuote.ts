import type { ShippingAddress } from '~/components/checkout/CheckoutForm.vue'
import { useCartStore } from '~/stores/cart'

/**
 * Delivery options and prices for the cart, from `POST /checkout/shipping-quote`.
 *
 * The quote id it holds is sent back with the order, and the API charges
 * exactly what it quoted under that id — so the shipping figure the summary
 * shows is the figure on the Paystack page. If the quote has expired by the
 * time the order is placed, the API refuses with a fresh one, which
 * `replace()` takes in.
 */
export type ShippingOption = {
  id: string
  carrier: string
  label: string
  /** GHS minor units; already 0 when free shipping applies. */
  amount_ghs: number
  free: boolean
  eta: string | null
}

export type ShippingQuote = {
  quote_id: string
  zone: 'accra' | 'ghana' | 'international'
  options: ShippingOption[]
  /** Estimated duties paid on arrival. Shown, never charged. */
  landed_cost: { amount: number, currency: string } | null
}

/** `slug:size:colour` → the `{ slug, size, colour }` the API resolves to a stock row. */
export function cartLine(item: { inventoryItemId: string, slug: string, quantity: number }) {
  const [slug, size, colour] = item.inventoryItemId.split(':')
  return { slug: slug || item.slug, size: size || null, colour: colour || null, quantity: item.quantity }
}

export function addressPayload(address: ShippingAddress) {
  return {
    full_name: address.fullName,
    email: address.email,
    phone: address.phone,
    line1: address.line1,
    city: address.city,
    region: address.region || null,
    postcode: address.postcode || null,
    country: address.country,
    lat: address.lat,
    lng: address.lng,
    place_id: address.placeId,
  }
}

export function useShippingQuote() {
  const api = useApi()
  const cart = useCartStore()

  const quote = ref<ShippingQuote | null>(null)
  const selectedId = ref<string | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const selected = computed(() => quote.value?.options.find((option) => option.id === selectedId.value) ?? null)

  function replace(next: ShippingQuote) {
    quote.value = next
    // Keep the customer's choice if it survived the re-quote.
    if (!next.options.some((option) => option.id === selectedId.value)) {
      selectedId.value = next.options[0]?.id ?? null
    }
  }

  async function fetchQuote(address: ShippingAddress) {
    loading.value = true
    error.value = null
    try {
      const res = await api<{ data: ShippingQuote }>('/checkout/shipping-quote', {
        method: 'POST',
        body: { items: cart.items.map(cartLine), shipping_address: addressPayload(address) },
      })
      replace(res.data)
    }
    catch (err: unknown) {
      const data = (err as { data?: { message?: string, blocked_items?: string[] } }).data
      quote.value = null
      selectedId.value = null
      // Products without customs data can't go abroad yet; name them, so the
      // customer knows which ones to remove or ask about.
      const blocked = data?.blocked_items?.length ? ` Items affected: ${data.blocked_items.join(', ')}.` : ''
      error.value = data?.message
        ? `${data.message}${blocked}`
        : 'We couldn’t work out delivery just now. Check your connection and try again.'
    }
    finally {
      loading.value = false
    }
  }

  return { quote, selectedId, selected, loading, error, fetchQuote, replace }
}
