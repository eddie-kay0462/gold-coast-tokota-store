<script setup lang="ts">
import { useCartStore } from '~/stores/cart'
import { useCurrencyStore } from '~/stores/currency'

/**
 * The order summary, laid out the way a standard Shopify checkout lays it out:
 * square thumbnails carrying a quantity badge on the corner, a discount-code
 * row, then subtotal / shipping / total with the currency code set small
 * against the total.
 *
 * It replaces `CartSummary.vue`, which listed the same data as a plain bordered
 * box with the quantity written into the variant line.
 */
const props = defineProps<{
  /** Estimated delivery cost in GHS minor units, or null before a method is chosen. */
  deliveryGhs?: number | null
  /** Set once the shipping step has been reached, so the row can explain itself. */
  deliveryLabel?: string | null
}>()

const cart = useCartStore()
const currency = useCurrencyStore()

const savingsGhs = computed(() => cart.compareAtSubtotalGhs - cart.subtotalGhs)
const totalGhs = computed(() => cart.subtotalGhs + (props.deliveryGhs ?? 0))

// --- Discount code --------------------------------------------------------
// Inert by design, the same shape as the payment step and the account pages:
// there is no discounts endpoint and no `Discount` model, so this waits, then
// explains itself, rather than firing a request that would 404. When one lands,
// `apply()` posts the code and the response updates the totals.
const code = ref('')
const applying = ref(false)
const codeNotice = ref<string | null>(null)

async function apply() {
  if (!code.value.trim() || applying.value) return
  codeNotice.value = null
  applying.value = true
  await new Promise((resolve) => setTimeout(resolve, 600))
  applying.value = false
  codeNotice.value =
    'Discount codes aren’t enabled yet — there’s no discounts endpoint on the API. '
    + 'If you were given a code, message us on WhatsApp and we’ll apply it by hand.'
}
</script>

<template>
  <div class="flex w-full flex-col gap-6">
    <h2 class="sr-only">Order summary</h2>

    <ul v-if="!cart.isEmpty" class="flex flex-col gap-4">
      <li v-for="item in cart.items" :key="item.inventoryItemId" class="flex items-center gap-4">
        <!-- Thumbnail with the quantity on its corner — the Shopify treatment.
             It reads at a glance and keeps the variant line for the variant. -->
        <div class="relative shrink-0">
          <div class="size-16 overflow-hidden rounded border border-line bg-white">
            <img
              v-if="item.image"
              :src="item.image"
              :alt="item.name"
              class="size-full object-cover"
              loading="lazy"
            >
          </div>
          <span
            class="absolute -right-2 -top-2 flex size-5 items-center justify-center rounded-full bg-muted text-[11px] leading-none text-white"
            aria-hidden="true"
          >{{ item.quantity }}</span>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
          <span class="text-caption font-normal text-black">{{ item.name }}</span>
          <span v-if="item.variantLabel" class="text-caption text-muted">{{ item.variantLabel }}</span>
          <span class="sr-only">Quantity {{ item.quantity }}</span>
        </div>

        <CommonPriceDisplay
          class="shrink-0 whitespace-nowrap text-caption text-graphite"
          :base-price-ghs="item.unitPriceGhs * item.quantity"
          :compare-at-ghs="item.compareAtGhs ? item.compareAtGhs * item.quantity : null"
          compact
        />
      </li>
    </ul>

    <p v-else class="text-caption text-muted">Your cart is empty.</p>

    <template v-if="!cart.isEmpty">
      <!-- Discount code -->
      <div class="flex flex-col gap-2 border-t border-line pt-6">
        <form class="flex w-full min-w-0 items-stretch gap-2" @submit.prevent="apply">
          <label class="sr-only" for="discount-code">Discount code</label>
          <input
            id="discount-code"
            v-model="code"
            name="discount-code"
            placeholder="Discount code"
            class="min-h-[44px] w-full min-w-0 flex-1 rounded border border-line bg-white px-3 text-caption text-graphite placeholder:text-muted"
          >
          <button
            type="submit"
            :disabled="!code.trim() || applying"
            class="min-h-[44px] shrink-0 rounded border border-line bg-surface px-4 text-caption text-graphite disabled:cursor-not-allowed disabled:text-muted"
          >
            {{ applying ? 'Applying…' : 'Apply' }}
          </button>
        </form>
        <CommonInlineNotice v-if="codeNotice" variant="warning">{{ codeNotice }}</CommonInlineNotice>
      </div>

      <!-- Totals -->
      <div class="flex flex-col gap-3 border-t border-line pt-6 text-caption">
        <div class="flex items-center justify-between text-graphite">
          <span>Subtotal · {{ cart.itemCount }} {{ cart.itemCount === 1 ? 'item' : 'items' }}</span>
          <CommonPriceDisplay :base-price-ghs="cart.subtotalGhs" compact />
        </div>

        <div v-if="savingsGhs > 0" class="flex items-center justify-between text-sale">
          <span>You save</span>
          <CommonPriceDisplay :base-price-ghs="savingsGhs" compact />
        </div>

        <div class="flex items-center justify-between text-graphite">
          <span>Shipping</span>
          <span v-if="deliveryGhs === null || deliveryGhs === undefined" class="text-muted">
            {{ deliveryLabel ?? 'Calculated at the next step' }}
          </span>
          <CommonPriceDisplay v-else :base-price-ghs="deliveryGhs" compact />
        </div>

        <div class="flex items-end justify-between border-t border-line pt-3">
          <span class="text-body font-normal text-black">Total</span>
          <span class="flex items-baseline gap-2">
            <span class="text-caption text-muted">{{ currency.displayCurrency }}</span>
            <CommonPriceDisplay class="text-display-sm font-normal text-black" :base-price-ghs="totalGhs" compact />
          </span>
        </div>

        <p v-if="deliveryGhs === null || deliveryGhs === undefined" class="text-caption text-muted">
          Shipping is added once you choose a delivery method.
        </p>
      </div>
    </template>
  </div>
</template>
