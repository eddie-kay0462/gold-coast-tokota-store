<script setup lang="ts">
import { useCurrencyStore } from '~/stores/currency'

const currency = useCurrencyStore()
</script>

<template>
  <div class="flex items-center gap-1 rounded-full border px-1 py-1 text-sm">
    <button
      type="button"
      class="rounded-full px-3 py-1"
      :class="currency.active === 'GHS' ? 'bg-black text-white' : ''"
      @click="currency.setCurrency('GHS')"
    >
      GHS
    </button>
    <button
      type="button"
      class="rounded-full px-3 py-1"
      :class="currency.active === 'USD' ? 'bg-black text-white' : ''"
      @click="currency.setCurrency('USD')"
    >
      USD
    </button>
  </div>
</template>
