<script setup lang="ts">
import { useCartStore } from '~/stores/cart'
import { useCurrencyStore } from '~/stores/currency'
import { whatsappMessage } from '~/utils/whatsapp'

/**
 * The order summary, laid out the way a standard Shopify checkout lays it out:
 * thumbnails carrying a quantity badge on the corner (portrait 3:4, like the
 * home page's product tiles), a gift card / discount code row, then subtotal /
 * shipping / gift card / total with the currency code set small against the
 * total.
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

const giftCard = useGiftCardCode()

const savingsGhs = computed(() => cart.compareAtSubtotalGhs - cart.subtotalGhs)
const grossGhs = computed(() => cart.subtotalGhs + (props.deliveryGhs ?? 0))
const giftCardGhs = computed(() => giftCard.takenOff(grossGhs.value))
const totalGhs = computed(() => grossGhs.value - giftCardGhs.value)

// --- Gift card or discount code ------------------------------------------
// A gift card or store credit code is checked against the API and comes off
// the total (the payment step sends it with the order). There are no discount
// codes online, so a code that isn't a gift card gets the old explanation:
// send it on WhatsApp and it's applied by hand.
const code = ref('')
const applying = ref(false)
const codeNotice = ref<{ text: string, discount: boolean } | null>(null)

async function apply() {
  if (!code.value.trim() || applying.value) return
  codeNotice.value = null
  applying.value = true
  const result = await giftCard.apply(code.value)
  applying.value = false
  if (result.ok) {
    code.value = ''
    return
  }
  codeNotice.value = result.notAGiftCard
    ? { text: 'That isn’t a gift card code, and discount codes can’t be applied online yet. Send it to us and we’ll apply it by hand.', discount: true }
    : { text: result.message, discount: false }
}
</script>

<template>
  <div class="flex w-full flex-col gap-6">
    <h2 class="sr-only">Order summary</h2>

    <ul v-if="!cart.isEmpty" class="flex flex-col gap-4">
      <li v-for="item in cart.items" :key="item.inventoryItemId" class="flex items-center gap-4">
        <!-- Thumbnail with the quantity on its corner, the Shopify treatment.
             It reads at a glance and keeps the variant line for the variant. -->
        <div class="relative shrink-0">
          <div class="aspect-[3/4] w-12 overflow-hidden bg-white">
            <img
              v-if="item.image"
              :src="item.image"
              :alt="item.name"
              class="size-full object-cover"
              loading="lazy"
            >
          </div>
          <span
            class="absolute -right-2 -top-2 flex size-5 items-center justify-center rounded-full bg-ink text-[11px] leading-none text-white"
            aria-hidden="true"
          >{{ item.quantity }}</span>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
          <span class="text-caption font-bold uppercase tracking-[1.4px] text-ink">{{ item.name }}</span>
          <span v-if="item.variantLabel" class="text-caption text-subtle">{{ item.variantLabel }}</span>
          <span class="sr-only">Quantity {{ item.quantity }}</span>
        </div>

        <CommonPriceDisplay
          class="shrink-0 whitespace-nowrap text-caption text-ink"
          :base-price-ghs="item.unitPriceGhs * item.quantity"
          :compare-at-ghs="item.compareAtGhs ? item.compareAtGhs * item.quantity : null"
          compact
        />
      </li>
    </ul>

    <p v-else class="text-caption text-muted">Your cart is empty.</p>

    <template v-if="!cart.isEmpty">
      <!-- Gift card or discount code -->
      <div class="flex flex-col gap-2 border-t border-line pt-6">
        <div v-if="giftCard.applied.value" class="flex w-full items-center justify-between gap-3 text-caption text-graphite">
          <span>Gift card ending {{ giftCard.applied.value.lastFour }} applied</span>
          <button type="button" class="-my-3 min-h-[44px] py-3 text-caption text-muted underline hover:text-graphite" @click="giftCard.clear()">
            Remove
          </button>
        </div>
        <form v-else class="flex w-full min-w-0 items-stretch gap-2" @submit.prevent="apply">
          <label class="sr-only" for="discount-code">Gift card or discount code</label>
          <input
            id="discount-code"
            v-model="code"
            name="discount-code"
            autocomplete="off"
            placeholder="Gift card or discount code"
            class="min-h-[44px] w-full min-w-0 flex-1 border border-line bg-white px-3 text-body text-graphite placeholder:text-muted"
          >
          <button
            type="submit"
            :disabled="!code.trim() || applying"
            class="btn-outline caps-label min-h-[44px] shrink-0 px-5 disabled:cursor-not-allowed disabled:opacity-40"
          >
            {{ applying ? 'Applying…' : 'Apply' }}
          </button>
        </form>
        <CommonInlineNotice v-if="codeNotice" variant="warning">
          {{ codeNotice.text }}
          <template v-if="codeNotice.discount" #action>
            <CommonWhatsAppLink
              source="discount-code"
              variant="quiet"
              :message="whatsappMessage.discountCode(code)"
            >
              Send the code on WhatsApp
            </CommonWhatsAppLink>
          </template>
        </CommonInlineNotice>
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

        <div v-if="giftCardGhs > 0" class="flex items-center justify-between text-graphite">
          <span>Gift card</span>
          <span class="flex items-baseline gap-0.5">−<CommonPriceDisplay :base-price-ghs="giftCardGhs" compact /></span>
        </div>

        <div class="flex items-end justify-between border-t border-line pt-3">
          <span class="caps-label text-ink">Total</span>
          <span class="flex items-baseline gap-2">
            <span class="text-caption text-muted">{{ currency.displayCurrency }}</span>
            <CommonPriceDisplay class="text-display-sm font-normal text-ink" :base-price-ghs="totalGhs" compact />
          </span>
        </div>

        <p v-if="deliveryGhs === null || deliveryGhs === undefined" class="text-caption text-muted">
          Shipping is added once you choose a delivery method.
        </p>
      </div>
    </template>
  </div>
</template>
