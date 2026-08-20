<script setup lang="ts">
import { useCurrencyStore } from '~/stores/currency'
import { formatMoney } from '~/utils/formatters'

const props = defineProps<{
  /** GHS price in minor units (pesewas) — the only value ever persisted. */
  basePriceGhs: number
  /**
   * Was-price in GHS minor units. When it's higher than `basePriceGhs` it
   * renders struck through ahead of the live price, per the listing design.
   */
  compareAtGhs?: number | null
  /** Narrow-symbol, decimal-trimmed formatting used on product cards. */
  compact?: boolean
}>()

const currency = useCurrencyStore()

/** USD is always derived from GHS × the live rate, never a stored field. */
function toActive(ghsMinorUnits: number) {
  if (currency.active === 'GHS') return ghsMinorUnits
  return Math.round(ghsMinorUnits * currency.fxRate)
}

const price = computed(() =>
  formatMoney(toActive(props.basePriceGhs), currency.active, { compact: props.compact }),
)

const isDiscounted = computed(
  () => !!props.compareAtGhs && props.compareAtGhs > props.basePriceGhs,
)

const comparePrice = computed(() =>
  isDiscounted.value
    ? formatMoney(toActive(props.compareAtGhs!), currency.active, { compact: props.compact })
    : null,
)
</script>

<template>
  <span class="inline-flex items-center gap-1">
    <s v-if="comparePrice" class="font-light">
      <span class="sr-only">Was </span>{{ comparePrice }}
    </s>
    <span :class="isDiscounted ? 'font-normal' : ''">
      <span v-if="isDiscounted" class="sr-only">Now </span>{{ price }}
    </span>
  </span>
</template>
