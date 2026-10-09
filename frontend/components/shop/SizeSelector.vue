<script setup lang="ts">
/**
 * The three-state size picker from the approved Template B mockup, shared by
 * the product card (`sm`) and the product detail panel (`lg`).
 *
 * The three states are the point of it: available, selected, and *made but not
 * currently sellable*. That third one is drawn rather than hidden — the mockup
 * strikes it through with a diagonal rule and leaves it in place, so a customer
 * can see the product runs in their size and ask about a restock, instead of
 * concluding it was never made for them.
 *
 * Per the project's tap-target rule the *hit area* is 44px; the drawn box stays
 * at its design size inside it (38×34 on a card, 46×44 on the detail page).
 *
 * One row that scrolls sideways, never wrapping. It used to wrap, so a product
 * with many sizes grew a second row and its card stopped lining up with its
 * neighbours in the shop grid. The scrollbar is hidden for the same reason: it
 * would add height only to the cards that overflow. A fade on the edge with
 * more sizes past it shows there is more to scroll to.
 *
 * With `hint`, the row also slides to its end and back once, the first time it
 * comes into view, so it's clear more sizes are off to the side. See
 * "Scroll hint" below.
 */
const props = withDefaults(
  defineProps<{
    sizes: string[]
    /**
     * Per-size sellable stock. `undefined` means the source isn't reporting
     * per-size stock at all, in which case every listed size stays selectable
     * — the server is still the authority at checkout. An empty-but-present
     * map means the opposite: nothing is sellable.
     */
    availability?: Record<string, number> | null
    modelValue?: string | null
    /** Bypasses the stock check — a made-to-order product has none by definition. */
    ignoreStock?: boolean
    size?: 'sm' | 'lg'
    /**
     * Play the one-off scroll hint. Off by default: on the product page there
     * is one selector, but a shop grid of cards all sliding at once would be
     * noise.
     */
    hint?: boolean
  }>(),
  { availability: undefined, modelValue: null, ignoreStock: false, size: 'lg', hint: false },
)

const emit = defineEmits<{ 'update:modelValue': [string] }>()

function isAvailable(size: string) {
  if (props.ignoreStock) return true
  if (props.availability === undefined || props.availability === null) return true
  return (props.availability[size] ?? 0) > 0
}

const boxClass = computed(() =>
  props.size === 'sm' ? 'h-[34px] w-[38px] text-tag' : 'h-11 w-[46px] text-caption',
)

// --- Overflow fades ----------------------------------------------------------
// Measured after mount only: there is no layout during SSR, so the server
// renders no fade and the client adds one if the row turns out to overflow.
const track = ref<HTMLElement | null>(null)
const canScrollBack = ref(false)
const canScrollForward = ref(false)

function measure() {
  const el = track.value
  if (!el) return
  // 1px slack: fractional widths can leave scrollLeft a hair short of the end.
  canScrollBack.value = el.scrollLeft > 1
  canScrollForward.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 1
}

let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  measure()
  resizeObserver = new ResizeObserver(measure)
  if (track.value) resizeObserver.observe(track.value)
  if (props.hint) armScrollHint()
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  stopScrollHint()
})

// --- Scroll hint -------------------------------------------------------------
// Slides the row to its end and back once. Only when:
//   - the row actually overflows (nothing to hint otherwise);
//   - it is on screen. On a phone the panel sits below the gallery, so playing
//     on mount would happen out of sight. An IntersectionObserver waits until
//     most of the row is visible;
//   - the visitor hasn't asked for reduced motion.
// It stops the moment the visitor touches, scrolls, clicks or tabs into the
// row, and never moves the row out from under them. GSAP animates a plain
// number copied to `scrollLeft`, which avoids needing the ScrollTo plugin.
let hintObserver: IntersectionObserver | null = null
let hintTimeline: { kill: () => void } | null = null
/** Set once the hint has finished or been interrupted; it never replays. */
let stopped = false

const HINT_INTERRUPTS = ['pointerdown', 'wheel', 'touchstart', 'keydown', 'focusin'] as const

function armScrollHint() {
  const el = track.value
  if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  hintObserver = new IntersectionObserver((entries) => {
    if (!entries.some(entry => entry.isIntersecting)) return
    hintObserver?.disconnect()
    hintObserver = null
    void playScrollHint()
  }, { threshold: 0.6 })
  hintObserver.observe(el)

  for (const type of HINT_INTERRUPTS) el.addEventListener(type, stopScrollHint, { passive: true })
}

async function playScrollHint() {
  const el = track.value
  if (!el) return

  const distance = el.scrollWidth - el.clientWidth
  if (distance <= 1) return

  const { gsap } = await import('gsap')
  // Interrupted (or unmounted) while GSAP was loading.
  if (stopped || !track.value) return

  const position = { x: el.scrollLeft }
  const apply = () => (el.scrollLeft = position.x)

  hintTimeline = gsap.timeline({ delay: 0.4, onComplete: stopScrollHint })
    .to(position, { x: distance, duration: 0.9, ease: 'power2.inOut', onUpdate: apply })
    .to(position, { x: 0, duration: 0.9, ease: 'power2.inOut', onUpdate: apply }, '+=0.35')
}

function stopScrollHint() {
  stopped = true
  hintObserver?.disconnect()
  hintObserver = null
  hintTimeline?.kill()
  hintTimeline = null
  const el = track.value
  if (el) for (const type of HINT_INTERRUPTS) el.removeEventListener(type, stopScrollHint)
}

// A colour change can swap the size list; re-measure once it has rendered.
watch(() => props.sizes, () => nextTick(measure))

function stateClass(size: string) {
  if (!isAvailable(size)) return 'unavailable border border-line text-line'
  if (size === props.modelValue) return 'border border-ink bg-ink text-white'
  return 'border border-line bg-white text-graphite hover:border-graphite'
}
</script>

<template>
  <!-- Negative margin so the 44px hit areas tile at the design's visual gap
       rather than the hit area's. -->
  <div
    ref="track"
    class="size-track -m-1 flex flex-nowrap overflow-x-auto overscroll-x-contain"
    :class="{ 'fade-start': canScrollBack, 'fade-end': canScrollForward }"
    @scroll.passive="measure"
  >
    <button
      v-for="size in sizes"
      :key="size"
      type="button"
      class="flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center p-1 disabled:cursor-not-allowed"
      :disabled="!isAvailable(size)"
      :aria-pressed="size === modelValue"
      :aria-label="isAvailable(size) ? `Size ${size}` : `Size ${size} — unavailable`"
      @click="emit('update:modelValue', size)"
    >
      <span
        class="flex items-center justify-center transition-colors"
        :class="[boxClass, stateClass(size)]"
      >{{ size }}</span>
    </button>
  </div>
</template>

<style scoped>
/* Hidden scrollbar: see the note at the top of the script. Still scrollable by
   touch, trackpad, Shift + mouse wheel, and Tab, which scrolls the focused size
   into view. */
.size-track {
  scrollbar-width: none;
}
.size-track::-webkit-scrollbar {
  display: none;
}

/* Edge fades, as a mask so they work over any background. */
.size-track.fade-start {
  mask-image: linear-gradient(to right, transparent, #000 24px);
}
.size-track.fade-end {
  mask-image: linear-gradient(to left, transparent, #000 24px);
}
.size-track.fade-start.fade-end {
  mask-image: linear-gradient(to right, transparent, #000 24px, #000 calc(100% - 24px), transparent);
}

/* The unavailable state's diagonal rule. A gradient rather than a pseudo
   element or an SVG so it scales with the box at either size without a second
   set of measurements, and so it sits behind the numeral rather than over it. */
.unavailable {
  background-image: linear-gradient(
    to top left,
    transparent calc(50% - 1px),
    theme('colors.line') calc(50% - 1px),
    theme('colors.line') calc(50% + 1px),
    transparent calc(50% + 1px)
  );
}
</style>
