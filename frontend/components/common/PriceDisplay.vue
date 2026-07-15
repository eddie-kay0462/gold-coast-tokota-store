<script setup lang="ts">
import { useCurrencyStore } from '~/stores/currency'
import { formatMoney } from '~/utils/formatters'

const props = defineProps<{
  /** GHS price in minor units (pesewas) — the only value ever persisted. */
  basePriceGhs: number
}>()

const currency = useCurrencyStore()

const displayAmount = computed(() => {
  if (currency.active === 'GHS') return props.basePriceGhs
  return Math.round(props.basePriceGhs * currency.fxRate)
})
</script>

<template>
  <span>{{ formatMoney(displayAmount, currency.active) }}</span>
</template>
