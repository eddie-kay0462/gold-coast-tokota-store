/**
 * Drives the arrow + dot carousels on the landing page (News & Events, UGC
 * gallery). Uses native horizontal scrolling with snap points rather than a
 * transform-based slider, so the rails stay usable by keyboard and touch and
 * degrade to a plain scrollable row if JS never runs.
 */
export function useScrollRail() {
  const railEl = ref<HTMLElement | null>(null)
  const scrollLeft = ref(0)
  const scrollWidth = ref(0)
  const clientWidth = ref(0)

  const pageCount = computed(() => {
    if (!clientWidth.value) return 1
    return Math.max(1, Math.ceil(scrollWidth.value / clientWidth.value))
  })

  const activeIndex = computed(() => {
    if (!clientWidth.value) return 0
    return Math.min(pageCount.value - 1, Math.round(scrollLeft.value / clientWidth.value))
  })

  const canScrollPrev = computed(() => scrollLeft.value > 1)
  const canScrollNext = computed(
    () => scrollLeft.value + clientWidth.value < scrollWidth.value - 1,
  )

  function measure() {
    const el = railEl.value
    if (!el) return
    scrollLeft.value = el.scrollLeft
    scrollWidth.value = el.scrollWidth
    clientWidth.value = el.clientWidth
  }

  function scrollToPage(index: number) {
    const el = railEl.value
    if (!el) return
    el.scrollTo({ left: index * el.clientWidth, behavior: 'smooth' })
  }

  function scrollByPage(direction: -1 | 1) {
    scrollToPage(activeIndex.value + direction)
  }

  let resizeObserver: ResizeObserver | null = null

  onMounted(() => {
    const el = railEl.value
    if (!el) return
    measure()
    el.addEventListener('scroll', measure, { passive: true })
    resizeObserver = new ResizeObserver(measure)
    resizeObserver.observe(el)
  })

  onBeforeUnmount(() => {
    railEl.value?.removeEventListener('scroll', measure)
    resizeObserver?.disconnect()
  })

  return {
    railEl,
    pageCount,
    activeIndex,
    canScrollPrev,
    canScrollNext,
    scrollToPage,
    scrollByPage,
  }
}
