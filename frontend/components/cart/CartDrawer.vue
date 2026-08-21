<script setup lang="ts">
import { PhX } from '@phosphor-icons/vue'
import { useCartStore } from '~/stores/cart'
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
 * Recommendations come from the design catalogue until Feature 2 exposes a
 * recommendations endpoint. Anything already in the cart is filtered out —
 * suggesting what someone just added reads as broken.
 */
const recommendations = computed(() => {
  const inCart = new Set(cart.items.map((item) => item.slug))
  return DESIGN_PRODUCTS.filter((product) => !inCart.has(product.slug)).slice(0, 4)
})

const savingsGhs = computed(() => cart.compareAtSubtotalGhs - cart.subtotalGhs)

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
// The lock lives in `useBodyScrollLock` — setting `body { overflow: hidden }`
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
          <!-- Scrollable body -->
          <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-5 py-2">
            <button
              ref="closeButton"
              type="button"
              class="-m-1.5 flex size-11 shrink-0 items-center justify-center self-end text-graphite transition-opacity hover:opacity-60"
              aria-label="Close cart"
              @click="close"
            >
              <PhX :size="24" />
            </button>

            <div class="flex w-full flex-col gap-4">
              <h2 id="cart-drawer-title" class="w-full text-display-sm font-normal text-black">
                Your Cart
              </h2>

              <template v-if="!cart.isEmpty">
                <CartLineItem
                  v-for="item in cart.items"
                  :key="item.inventoryItemId"
                  :item="item"
                  @quantity="cart.setQuantity(item.inventoryItemId, $event)"
                  @remove="cart.removeItem(item.inventoryItemId)"
                />
              </template>

              <div v-else class="flex w-full flex-col items-start gap-4 py-8">
                <p class="text-body text-graphite">Your cart is empty.</p>
                <p class="text-caption text-muted">
                  Every pair is cut and stitched by hand in Accra — start with the collection.
                </p>
                <CommonBrandButton to="/shop" @click="close">Shop Sandals</CommonBrandButton>
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
            class="flex w-full shrink-0 flex-col gap-8 bg-white px-5 py-[30px] shadow-[0px_-6px_18px_rgba(0,0,0,0.25)]"
          >
            <div class="flex w-full items-center justify-between whitespace-nowrap text-black">
              <p class="flex items-center gap-1">
                <span class="text-body font-normal">Subtotal</span>
                <span class="text-label font-light">
                  ({{ cart.itemCount }} {{ cart.itemCount === 1 ? 'item' : 'items' }})
                </span>
              </p>
              <CommonPriceDisplay
                class="text-right text-body font-normal"
                :base-price-ghs="cart.subtotalGhs"
                compact
              />
            </div>

            <p v-if="savingsGhs > 0" class="-mt-6 w-full text-right text-caption text-sale">
              You save <CommonPriceDisplay :base-price-ghs="savingsGhs" compact />
            </p>

            <CommonBrandButton full @click="goToCheckout">
              Continue to Checkout
            </CommonBrandButton>

            <p class="w-full text-center text-caption font-normal text-black">
              Psst, get it now before it sells out.
            </p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ClientOnly>
</template>
