/**
 * Drives the arrow + dot carousels on the landing page (News & Events, UGC
 * gallery). Uses native horizontal scrolling with snap points rather than a
 * transform-based slider, so the rails stay usable by keyboard and touch and
 * degrade to a plain scrollable row if JS never runs.
 *
 * Paging is measured from the *slides*, not from the container width. Slide
 * widths differ per breakpoint (`w-[70%] sm:w-[45%] lg:w-[calc(25%-…)]`) and
 * only tile the container exactly at `lg`; scrolling by one container width
 * elsewhere landed mid-slide, snap re-snapped somewhere else, and the dots lit
 * a page the user had never scrolled to.
 */
export function useScrollRail() {
  const railEl = ref<HTMLElement | null>(null)
  const scrollLeft = ref(0)
  const scrollWidth = ref(0)
  const clientWidth = ref(0)
  /** Left offset of each slide, relative to the rail's content box. */
  const slideOffsets = ref<number[]>([])

  /**
   * Slides fully visible at once. Used to group slides into pages so an arrow
   * advances a screenful rather than a single card on a wide rail.
   */
  const perPage = computed(() => {
    if (slideOffsets.value.length < 2 || !clientWidth.value) return 1
    const step = slideOffsets.value[1]! - slideOffsets.value[0]!
    if (step <= 0) return 1
    return Math.max(1, Math.round(clientWidth.value / step))
  })

  const pageCount = computed(() => {
    if (!slideOffsets.value.length) return 1
    return Math.max(1, Math.ceil(slideOffsets.value.length / perPage.value))
  })

  const activeIndex = computed(() => {
    if (!slideOffsets.value.length) return 0
    // The slide nearest the left edge, mapped onto its page.
    let nearest = 0
    let best = Number.POSITIVE_INFINITY
    for (let i = 0; i < slideOffsets.value.length; i += 1) {
      const distance = Math.abs(slideOffsets.value[i]! - scrollLeft.value)
      if (distance < best) {
        best = distance
        nearest = i
      }
    }
    return Math.min(pageCount.value - 1, Math.floor(nearest / perPage.value))
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
    slideOffsets.value = Array.from(el.children).map(
      (child) => (child as HTMLElement).offsetLeft - (el.firstElementChild as HTMLElement | null)!.offsetLeft,
    )
  }

  function scrollToPage(index: number) {
    const el = railEl.value
    if (!el || !slideOffsets.value.length) return
    const slide = Math.min(
      slideOffsets.value.length - 1,
      Math.max(0, index * perPage.value),
    )
    el.scrollTo({ left: slideOffsets.value[slide]!, behavior: 'smooth' })
  }

  function scrollByPage(direction: -1 | 1) {
    scrollToPage(activeIndex.value + direction)
  }

  let resizeObserver: ResizeObserver | null = null
  let mutationObserver: MutationObserver | null = null

  onMounted(() => {
    const el = railEl.value
    if (!el) return
    measure()
    el.addEventListener('scroll', measure, { passive: true })
    // Observes the rail's box (breakpoint changes) *and* its children, since a
    // ResizeObserver does not fire when only `scrollWidth` changes — which is
    // what happens when an async list of posts resolves and the slide count
    // changes underneath a stale dot row.
    resizeObserver = new ResizeObserver(measure)
    resizeObserver.observe(el)
    mutationObserver = new MutationObserver(measure)
    mutationObserver.observe(el, { childList: true })
  })

  onBeforeUnmount(() => {
    railEl.value?.removeEventListener('scroll', measure)
    resizeObserver?.disconnect()
    mutationObserver?.disconnect()
  })

  return {
    railEl,
    pageCount,
    activeIndex,
    canScrollPrev,
    canScrollNext,
    scrollToPage,
    scrollByPage,
    measure,
  }
}
