import { INVENTORY_POLL_INTERVAL_MS } from '~/utils/constants'

/**
 * Polls stock status for a single product while its detail page is mounted.
 * Correctness is always enforced server-side at checkout — this only keeps
 * the displayed Add-to-Cart state fresh. Pauses when the tab is backgrounded
 * (Page Visibility API) per Feature 3 edge cases.
 */
export function useInventoryPolling(productId: Ref<string>) {
  const quantityAvailable = ref<number | null>(null)
  let timer: ReturnType<typeof setInterval> | null = null

  async function fetchStock() {
    const config = useRuntimeConfig()
    const data = await $fetch<{ data: { quantity_available: number } }>(
      `${config.public.apiBase}/products/${productId.value}/stock`,
    )
    quantityAvailable.value = data.data.quantity_available
  }

  function start() {
    stop()
    fetchStock()
    timer = setInterval(fetchStock, INVENTORY_POLL_INTERVAL_MS)
  }

  function stop() {
    if (timer) clearInterval(timer)
    timer = null
  }

  function handleVisibilityChange() {
    if (document.hidden) stop()
    else start()
  }

  onMounted(() => {
    start()
    document.addEventListener('visibilitychange', handleVisibilityChange)
  })

  onUnmounted(() => {
    stop()
    document.removeEventListener('visibilitychange', handleVisibilityChange)
  })

  return { quantityAvailable }
}
