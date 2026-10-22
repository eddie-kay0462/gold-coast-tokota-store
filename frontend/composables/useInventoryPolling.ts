import { INVENTORY_POLL_INTERVAL_MS } from '~/utils/constants'

/** `GET /products/{slug}/stock`: sellable stock, live. */
export interface LiveStock {
  /** All colours together, keyed by size. */
  size_availability: Record<string, number>
  /** Keyed by colour, then size. Empty for a style with no colour axis. */
  variant_availability: Record<string, Record<string, number>>
  in_stock: boolean
  merchandising_badge: string | null
}

/**
 * Polls a product's stock while its detail page is open, so a size that
 * sells out while someone is looking is struck through without a reload.
 * Checkout still enforces stock on the server; this only keeps the page honest.
 *
 * - Pauses while the tab is hidden (Page Visibility API, Feature 3 edge case)
 *   and catches up as soon as it is visible again.
 * - A failed poll keeps the last good answer; the next one tries again.
 * - Stops for good on a 404: the product is a design stand-in, or has been
 *   switched off, and there is nothing to poll.
 */
export function useInventoryPolling(slug: Ref<string>) {
  const stock = ref<LiveStock | null>(null)
  const config = useRuntimeConfig()
  let timer: ReturnType<typeof setTimeout> | null = null
  let gone = false

  async function poll() {
    if (gone || document.hidden) return
    try {
      const res = await $fetch<{ data: LiveStock }>(`${config.public.apiBase}/products/${slug.value}/stock`)
      stock.value = res.data
    } catch (err) {
      if ((err as { statusCode?: number }).statusCode === 404) {
        gone = true
        return
      }
      // Keep the last answer on screen.
    }
    schedule()
  }

  function schedule() {
    stop()
    if (!gone && !document.hidden) timer = setTimeout(poll, INVENTORY_POLL_INTERVAL_MS)
  }

  function stop() {
    if (timer) clearTimeout(timer)
    timer = null
  }

  function onVisibility() {
    if (document.hidden) stop()
    else poll()
  }

  // A different product (client-side navigation between products) starts over.
  watch(slug, () => {
    stock.value = null
    gone = false
    stop()
    poll()
  })

  onMounted(() => {
    document.addEventListener('visibilitychange', onVisibility)
    // The first poll waits one interval: the page has just loaded the same
    // figures with the product.
    schedule()
  })

  onUnmounted(() => {
    stop()
    document.removeEventListener('visibilitychange', onVisibility)
  })

  return { stock }
}
