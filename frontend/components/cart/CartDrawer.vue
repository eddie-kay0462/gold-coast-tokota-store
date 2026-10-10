<script setup lang="ts">
import { PhX } from '@phosphor-icons/vue'
import { useCartStore } from '~/stores/cart'
import { formatMoney } from '~/utils/formatters'
import { whatsappMessage } from '~/utils/whatsapp'
import type { ApiProduct } from '~/utils/catalog'
import { DESIGN_PRODUCTS } from '~/utils/designCatalogue'

const cart = useCartStore()
const router = useRouter()
const { beginCheckout } = useAnalytics()

const panel = ref<HTMLElement | null>(null)
const closeButton = ref<InstanceType<typeof PhX> | null>(null)
/** Restored when the drawer closes, so focus doesn't jump to the top of the page. */
let previouslyFocused: HTMLElement | null = null

/**
 * Suggestions from `GET /products/recommendations`, ranked against what is in
 * the cart, which the API also excludes, since suggesting what someone just
 * added reads as broken. Fetched when the drawer opens or the cart's products
 * change, not on every page. The design catalogue stands in only if the API
 * can't be reached.
 */
const api = useApi()
const apiRecommendations = ref<ApiProduct[] | null>(null)
const cartSlugs = computed(() => [...new Set(cart.items.map((item) => item.slug))].sort().join(','))
/** The cart the current suggestions were ranked for; null until a fetch succeeds. */
let fetchedFor: string | null = null

watch(
  [() => cart.isDrawerOpen, cartSlugs],
  async ([open, slugs]) => {
    // Reopening with the same cart reuses what was already fetched.
    if (!open || slugs === fetchedFor) return
    const response = await api<{ data: ApiProduct[] }>('/products/recommendations', {
      query: { for: slugs, limit: 4 },
    }).catch(() => null)
    apiRecommendations.value = response?.data ?? null
    fetchedFor = response ? slugs : null
  },
  { immediate: true },
)

const recommendations = computed(() => {
  if (apiRecommendations.value) return apiRecommendations.value
  const inCart = new Set(cart.items.map((item) => item.slug))
  return DESIGN_PRODUCTS.filter((product) => !inCart.has(product.slug)).slice(0, 4)
})

const savingsGhs = computed(() => cart.compareAtSubtotalGhs - cart.subtotalGhs)

/**
 * The whole-basket handoff to WhatsApp.
 *
 * The approved mockup puts a WhatsApp order button on the product card and the
 * product page, but has nothing here, which leaves a customer with three pairs
 * in the basket re-typing all three into a chat. This composes the basket into
 * one message instead.
 *
 * Prices are always quoted in cedis regardless of the display currency: the
 * conversation ends in a real order, and USD on the storefront is a derived
 * display figure, not a price anyone is committing to.
 */
const whatsappOrderMessage = computed(() =>
  whatsappMessage.cart(
    cart.items.map((item) => {
      const variant = item.variantLabel ? ` (${item.variantLabel})` : ''
      return `• ${item.name}${variant} × ${item.quantity}`
    }),
    formatMoney(cart.subtotalGhs, 'GHS', { compact: true }),
  ),
)



function close() {
  cart.closeDrawer()
}

/**
 * The design's "ADD" has no size picker, but footwear can't be added without a
 * size. Single-size products add straight to the cart; anything with a size
 * choice opens its detail page so the customer picks, rather than us guessing.
 */
function addRecommendation(product: ApiProduct) {
  const sizes = product.sizes ?? []

  if (sizes.length === 1) {
    cart.addItem({
      productId: product.slug,
      inventoryItemId: `${product.slug}:${sizes[0]}:${product.color ?? ''}`,
      slug: product.slug,
      name: product.name,
      image: product.images?.[0],
      variantLabel: [sizes[0], product.color].filter(Boolean).join(' | '),
      quantity: 1,
      unitPriceGhs: product.base_price_ghs,
      compareAtGhs: product.compare_at_ghs,
    })
    return
  }

  close()
  router.push(`/shop/${product.slug}`)
}

function goToCheckout() {
  beginCheckout({
    currency: 'GHS',
    value: cart.subtotalGhs / 100,
    items: cart.items.map((item) => ({
      item_id: item.productId,
      item_name: item.name,
      quantity: item.quantity,
      price: item.unitPriceGhs / 100,
    })),
  })
  close()
  router.push('/checkout')
}

// Escape closes, and the page behind must not scroll while the drawer is open.
// The lock lives in `useBodyScrollLock`; setting `body { overflow: hidden }`
// alone does not hold on iOS Safari, which is where a full-bleed drawer matters
// most.
useBodyScrollLock(() => cart.isDrawerOpen)

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}

watch(
  () => cart.isDrawerOpen,
  async (open) => {
    if (!import.meta.client) return

    if (open) {
      previouslyFocused = document.activeElement as HTMLElement
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      panel.value?.focus()
    } else {
      document.removeEventListener('keydown', onKeydown)
      previouslyFocused?.focus()
      previouslyFocused = null
    }
  },
)

onBeforeUnmount(() => {
  if (!import.meta.client) return
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <ClientOnly>
    <Teleport to="body">
      <!-- Scrim -->
      <Transition
        enter-active-class="motion-safe:transition-opacity motion-safe:duration-200"
        leave-active-class="motion-safe:transition-opacity motion-safe:duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="cart.isDrawerOpen"
          class="fixed inset-0 z-[60] bg-black/60"
          aria-hidden="true"
          @click="close"
        />
      </Transition>

      <!-- Panel -->
      <Transition
        enter-active-class="motion-safe:transition-transform motion-safe:duration-300 motion-safe:ease-out"
        leave-active-class="motion-safe:transition-transform motion-safe:duration-200 motion-safe:ease-in"
        enter-from-class="translate-x-full"
        leave-to-class="translate-x-full"
      >
        <div
          v-if="cart.isDrawerOpen"
          ref="panel"
          class="fixed inset-y-0 right-0 z-[70] flex w-full max-w-[477px] flex-col justify-between bg-white outline-none"
          role="dialog"
          aria-modal="true"
          aria-labelledby="cart-drawer-title"
          tabindex="-1"
        >
          <!-- Title and close on one hairline-ruled row, the header's own
               white-and-hairline look. -->
          <div class="flex w-full shrink-0 items-center justify-between gap-4 border-b border-line py-2 pl-5 pr-3.5">
            <h2 id="cart-drawer-title" class="caps-title">
              Your cart<span v-if="!cart.isEmpty" class="font-light text-subtle"> ({{ cart.itemCount }})</span>
            </h2>
            <button
              ref="closeButton"
              type="button"
              class="flex size-11 shrink-0 items-center justify-center text-ink transition-opacity hover:opacity-60"
              aria-label="Close cart"
              @click="close"
            >
              <PhX :size="20" />
            </button>
          </div>

          <!-- Scrollable body -->
          <div class="flex min-h-0 flex-1 flex-col gap-8 overflow-y-auto px-5 py-2">
            <div class="flex w-full flex-col">
              <ul v-if="!cart.isEmpty" class="flex w-full flex-col divide-y divide-line">
                <li v-for="item in cart.items" :key="item.inventoryItemId" class="py-5">
                  <CartLineItem
                    :item="item"
                    @quantity="cart.setQuantity(item.inventoryItemId, $event)"
                    @remove="cart.removeItem(item.inventoryItemId)"
                  />
                </li>
              </ul>

              <div v-else class="flex w-full flex-col items-center gap-3 py-16 text-center">
                <p class="caps-title">Your cart is empty</p>
                <p class="max-w-[320px] text-label text-subtle">
                  Every pair is cut and stitched by hand in Accra. Start with the collection.
                </p>
                <CommonBrandButton to="/shop" class="mt-3" @click="close">Shop Sandals</CommonBrandButton>
              </div>
            </div>

            <CartRecommendations
              v-if="recommendations.length"
              :products="recommendations"
              @add="addRecommendation"
            />
          </div>

          <!-- Sticky checkout footer -->
          <div
            v-if="!cart.isEmpty"
            class="flex w-full shrink-0 flex-col gap-4 border-t border-line bg-white px-5 py-6"
          >
            <div class="flex w-full items-center justify-between whitespace-nowrap">
              <p class="flex items-center gap-1.5">
                <span class="caps-label text-ink">Subtotal</span>
                <span class="text-caption text-subtle">
                  ({{ cart.itemCount }} {{ cart.itemCount === 1 ? 'item' : 'items' }})
                </span>
              </p>
              <CommonPriceDisplay
                class="text-right text-body font-normal text-ink"
                :base-price-ghs="cart.subtotalGhs"
                compact
              />
            </div>

            <p v-if="savingsGhs > 0" class="-mt-3 w-full text-right text-caption text-sale">
              You save <CommonPriceDisplay :base-price-ghs="savingsGhs" compact />
            </p>

            <CommonBrandButton full @click="goToCheckout">
              Continue to Checkout
            </CommonBrandButton>

            <CommonWhatsAppLink source="cart" full :message="whatsappOrderMessage">
              Order on WhatsApp instead
            </CommonWhatsAppLink>

            <p class="w-full text-center text-caption text-muted">
              Psst, get it now before it sells out.
            </p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ClientOnly>
</template>
