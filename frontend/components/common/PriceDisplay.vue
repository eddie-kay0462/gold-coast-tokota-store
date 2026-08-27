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
  <!--
    Live price first, was-price after it.

    It used to be the other way round, both at the same size, with the struck
    price faded to 50%. The eye landed on the crossed-out number first and had
    to work out which of two equally sized figures it was actually paying — the
    "was" was competing with the "now" instead of qualifying it.

    Now: the live price leads and turns `sale` red when it is a discount (the
    colour the design system reserves for exactly this, and the one the product
    card's own discount chip already uses), and the was-price follows at 0.8em
    in muted grey. `0.8em` rather than a fixed size so this scales with whatever
    type the parent sets — 12px on a card, 24px in the purchase panel.

    `items-baseline` keeps the two sitting on one line despite the size gap, and
    `flex-wrap` matters because consumers put this in narrow columns (the cart
    line item leaves it ~90px).
  -->
  <span class="inline-flex min-w-0 flex-wrap items-baseline gap-x-2">
    <span :class="isDiscounted ? 'font-normal text-sale' : ''">
      <span v-if="isDiscounted" class="sr-only">Now </span>{{ price }}
    </span>
    <s v-if="comparePrice" class="text-[0.8em] font-light text-muted decoration-1">
      <span class="sr-only">Was </span>{{ comparePrice }}
    </s>
  </span>
</template>
