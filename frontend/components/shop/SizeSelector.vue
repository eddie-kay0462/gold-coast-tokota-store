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
  }>(),
  { availability: undefined, modelValue: null, ignoreStock: false, size: 'lg' },
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

function stateClass(size: string) {
  if (!isAvailable(size)) return 'unavailable border border-line text-line'
  if (size === props.modelValue) return 'border border-ink bg-ink text-white'
  return 'border border-line bg-white text-graphite hover:border-graphite'
}
</script>

<template>
  <!-- Negative margin so the 44px hit areas tile at the design's visual gap
       rather than the hit area's. -->
  <div class="-m-1 flex flex-wrap">
    <button
      v-for="size in sizes"
      :key="size"
      type="button"
      class="flex min-h-[44px] min-w-[44px] items-center justify-center p-1 disabled:cursor-not-allowed"
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
