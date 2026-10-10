<script setup lang="ts">
import { useCurrencyStore } from '~/stores/currency'
import { CURRENCIES, type Currency } from '~/utils/constants'

/**
 * The GHS|USD segmented control from the approved Template B mockup: two
 * adjoining cells in a hairline box, the active one filled.
 *
 * It replaces the header's older single button that flipped between the two
 * labels; that showed only the *current* currency, so a visitor could not see
 * that the other one existed without clicking.
 *
 * The highlighted cell is the currency prices are *actually* in
 * (`displayCurrency`), not the one last clicked. Until a rate loads, USD can't
 * be derived and prices stay in cedis. Highlighting USD then made the switch
 * look broken. Choosing USD fetches the rate (`setCurrency`), and the switch
 * moves over when the prices do.
 */
withDefaults(
  defineProps<{
    /** `dark` sits on the black announcement strip; `light` on a white ground. */
    tone?: 'dark' | 'light'
  }>(),
  { tone: 'dark' },
)

const currency = useCurrencyStore()

function cellClass(code: Currency, tone: 'dark' | 'light') {
  const active = currency.displayCurrency === code
  if (tone === 'light') {
    return active ? 'border-ink bg-ink text-white' : 'border-ink/30 text-ink group-hover:border-ink'
  }
  return active ? 'border-white bg-white text-ink' : 'border-white/40 text-white group-hover:border-white'
}
</script>

<template>
  <!-- The tap target and the drawn control are separate. Each button is a
       44px-square hit area with nothing painted on it; the visible segmented
       pill is the small span inside. Before, the 44px box *was* the drawn cell,
       so on a phone the switch filled the announcement strip edge to edge and
       outweighed the message beside it.

       The first button pushes its cell right and the second pushes its cell
       left, so the two cells meet in the middle as one pill however wide the
       hit areas are. -->
  <div class="flex shrink-0 items-center" role="group" aria-label="Display currency">
    <button
      v-for="(code, index) in CURRENCIES"
      :key="code"
      type="button"
      class="group flex min-h-[44px] min-w-[44px] items-center"
      :class="index === 0 ? 'justify-end' : 'justify-start'"
      :aria-pressed="currency.displayCurrency === code"
      :aria-label="`Show prices in ${code}`"
      @click="currency.setCurrency(code)"
    >
      <span
        class="border px-2 py-[5px] text-[11px] leading-none tracking-[0.6px] transition-colors lg:px-2.5 lg:py-1 lg:text-caption"
        :class="[cellClass(code, tone), index === 0 ? 'rounded-l-[2px]' : '-ml-px rounded-r-[2px]']"
      >
        {{ code }}
      </span>
    </button>
  </div>
</template>
