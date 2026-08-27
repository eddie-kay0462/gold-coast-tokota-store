<script setup lang="ts">
import { formatMoney } from '~/utils/formatters'
import { useCurrencyStore } from '~/stores/currency'

const props = defineProps<{
  /** Cost lines in GHS minor units, in display order. */
  breakdown: { label: string, amount_ghs: number, icon: string }[]
}>()

const currency = useCurrencyStore()

const lines = computed(() =>
  props.breakdown.map((line) => ({
    ...line,
    // Same GHS → USD derivation the rest of the storefront uses, including
    // the fallback to cedis when no rate has been fetched yet.
    display: formatMoney(
      currency.displayCurrency === 'GHS'
        ? line.amount_ghs
        : Math.round(line.amount_ghs * currency.fxRate),
      currency.displayCurrency,
      { compact: true },
    ),
  })),
)
</script>

<template>
  <section class="page-gutter section-y flex w-full flex-col items-center gap-4">
    <div class="flex w-full max-w-[684px] flex-col items-center gap-4 text-center text-graphite">
      <h2 class="w-full text-display-sm font-normal">Transparent Pricing</h2>
      <p class="w-full text-label font-light">
        We publish what it costs us to make every one of our products. There are a lot of costs we
        can't neatly account for — like design, fittings, wear testing, rent on office and retail
        space — but we believe you deserve to know what goes into making the products you love.
      </p>
    </div>

    <ul class="flex w-full max-w-[684px] flex-wrap items-start justify-center">
      <li
        v-for="line in lines"
        :key="line.label"
        class="flex min-w-0 flex-1 basis-1/3 flex-col items-center gap-3 p-6 sm:basis-0"
      >
        <img :src="line.icon" alt="" class="h-[60px] w-auto" loading="lazy">
        <p class="w-full text-center text-caption font-light text-graphite">
          <span class="block">{{ line.label }}</span>
          <span class="block">{{ line.display }}</span>
        </p>
      </li>
    </ul>
  </section>
</template>
