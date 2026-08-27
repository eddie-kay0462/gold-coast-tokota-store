<script setup lang="ts">
import { useCurrencyStore } from '~/stores/currency'
import { CURRENCIES, type Currency } from '~/utils/constants'

/**
 * The GHS|USD segmented control from the approved Template B mockup: two
 * adjoining cells in a hairline box, the active one filled.
 *
 * It replaces the header's older single button that flipped between the two
 * labels — that showed only the *current* currency, so a visitor could not see
 * that the other one existed without clicking.
 */
withDefaults(
  defineProps<{
    /** `dark` sits on the chrome bar; `light` on a white ground. */
    tone?: 'dark' | 'light'
  }>(),
  { tone: 'dark' },
)

const currency = useCurrencyStore()

function cellClass(code: Currency, tone: 'dark' | 'light') {
  const active = currency.active === code
  if (tone === 'light') {
    return active ? 'bg-graphite text-white' : 'text-graphite hover:bg-surface'
  }
  return active ? 'bg-white text-chrome' : 'text-white/70 hover:text-white'
}
</script>

<template>
  <div
    class="flex shrink-0 items-center overflow-hidden rounded-[2px] border"
    :class="tone === 'light' ? 'border-line' : 'border-white/25'"
    role="group"
    aria-label="Display currency"
  >
    <button
      v-for="code in CURRENCIES"
      :key="code"
      type="button"
      class="flex min-h-[44px] items-center justify-center px-2.5 text-caption transition-colors lg:min-h-0 lg:py-1"
      :class="cellClass(code, tone)"
      :aria-pressed="currency.active === code"
      :aria-label="`Show prices in ${code}`"
      @click="currency.setCurrency(code)"
    >
      {{ code }}
    </button>
  </div>
</template>
