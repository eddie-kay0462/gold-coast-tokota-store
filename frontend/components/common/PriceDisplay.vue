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

/**
 * USD is always derived from GHS × the live rate, never a stored field.
 *
 * `displayCurrency` — not `active` — is what gets rendered: the rate is
 * fetched at runtime and is 0 until it lands, and multiplying by 0 would print
 * a confident "$0" on every price in the shop. Falling back to the cedi price
 * is the honest failure.
 */
function toDisplay(ghsMinorUnits: number) {
  if (currency.displayCurrency === 'GHS') return ghsMinorUnits
  return Math.round(ghsMinorUnits * currency.fxRate)
}

const price = computed(() =>
  formatMoney(toDisplay(props.basePriceGhs), currency.displayCurrency, { compact: props.compact }),
)

const isDiscounted = computed(
  () => !!props.compareAtGhs && props.compareAtGhs > props.basePriceGhs,
)

const comparePrice = computed(() =>
  isDiscounted.value
    ? formatMoney(toDisplay(props.compareAtGhs!), currency.displayCurrency, { compact: props.compact })
    : null,
)
</script>

<template>
  <!-- `flex-wrap` matters: consumers put this in narrow columns (the cart
       line item leaves it ~90px), and a discounted price renders both the
       struck compare-at and the live price. Without wrapping the pair is an
       unshrinkable box that pushes its row sideways. -->
  <span class="inline-flex min-w-0 flex-wrap items-center gap-x-1">
    <s v-if="comparePrice" class="font-light">
      <span class="sr-only">Was </span>{{ comparePrice }}
    </s>
    <span :class="isDiscounted ? 'font-normal' : ''">
      <span v-if="isDiscounted" class="sr-only">Now </span>{{ price }}
    </span>
  </span>
</template>
