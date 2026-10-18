<script setup lang="ts">
import type { ShippingQuote } from '~/composables/useShippingQuote'

/**
 * The delivery options the API quoted for this cart and address.
 *
 * One per zone, priced by the server: an on-demand courier inside the Accra
 * zone, a regional flat rate elsewhere in Ghana, an international courier
 * abroad. Nothing here is an estimate any more. The price shown is the one
 * charged, because the order is placed against this quote's id.
 */
const props = defineProps<{
  quote: ShippingQuote | null
  loading: boolean
  error: string | null
}>()
const emit = defineEmits<{ retry: [] }>()
const selected = defineModel<string | null>({ default: null })

const international = computed(() => props.quote?.zone === 'international')
</script>

<template>
  <fieldset class="flex w-full flex-col items-start gap-3">
    <!-- The visible heading is the page's ("Shipping method"); repeating it
         here read as two headings for one control. The legend stays for screen
         readers, which do not get the page's heading as the fieldset's name. -->
    <legend class="sr-only">Shipping method</legend>

    <p v-if="loading" class="w-full border border-line p-4 text-caption text-muted" aria-live="polite">
      Working out delivery…
    </p>

    <template v-else-if="error">
      <CommonInlineNotice variant="warning" title="Delivery couldn’t be priced" role="alert">
        {{ error }}
      </CommonInlineNotice>
      <button
        type="button"
        class="caps-label -my-2.5 flex min-h-[44px] items-center py-2.5 text-ink underline underline-offset-4 hover:no-underline"
        @click="emit('retry')"
      >
        Try again
      </button>
    </template>

    <template v-else-if="quote">
      <label
        v-for="option in quote.options"
        :key="option.id"
        class="flex w-full min-w-0 cursor-pointer items-start gap-3 border p-4"
        :class="selected === option.id ? 'border-ink' : 'border-line hover:border-ink'"
      >
        <input
          v-model="selected"
          type="radio"
          name="delivery-option"
          :value="option.id"
          class="mt-1 size-4 shrink-0 accent-ink"
        >
        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
          <span class="caps-title">{{ option.label }}</span>
          <span v-if="option.eta" class="text-caption text-subtle">
            {{ option.eta }} after our 48-hour processing time
          </span>
        </span>
        <span class="shrink-0 text-body text-ink">
          <template v-if="option.free">Free</template>
          <CommonPriceDisplay v-else :base-price-ghs="option.amount_ghs" compact />
        </span>
      </label>

      <!-- §8: duties are the customer's, paid to the courier on arrival. -->
      <p v-if="international" class="w-full text-caption text-muted">
        Customs duties, taxes or import fees charged by your country are not included and are
        paid on arrival<template v-if="quote.landed_cost">
          (estimated
          <CommonPriceDisplay :base-price-ghs="quote.landed_cost.amount" compact />)</template>.
      </p>
    </template>
  </fieldset>
</template>
