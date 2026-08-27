<script setup lang="ts">
import { PhWhatsappLogo as WhatsappLogo } from '@phosphor-icons/vue'
import type { ApiProduct } from '~/utils/catalog'

const props = defineProps<{
  product: ApiProduct
  breadcrumb: string
  /**
   * Live stock from `useInventoryPolling`, keyed by size. Null while the first
   * poll is in flight — the panel then falls back to the product payload.
   */
  liveStock?: Record<string, number> | null
}>()

const emit = defineEmits<{ add: [{ size: string, color: string }] }>()

const selectedColor = ref(props.product.color ?? props.product.colors?.[0]?.name ?? '')
const selectedSize = ref<string | null>(null)

const isOnSale = computed(
  () => !!props.product.compare_at_ghs && props.product.compare_at_ghs > props.product.base_price_ghs,
)

const availability = computed(() => props.liveStock ?? props.product.size_availability)

/**
 * With a stock map present, a size missing from it is out of stock. With no map
 * at all the API simply isn't reporting per-size stock yet, so listed sizes stay
 * selectable — the server still rejects an unavailable size at checkout.
 *
 * Pre-order products are exempt: they have no stock on hand by definition, and
 * gating them on it would make every size unselectable.
 */
function stockFor(size: string) {
  if (props.product.is_pre_order) return 1
  if (!availability.value) return 1
  return availability.value[size] ?? 0
}

const selectedInStock = computed(() => !!selectedSize.value && stockFor(selectedSize.value) > 0)

// Selecting a colour can change which sizes exist, so a now-invalid size is
// cleared rather than left silently selected.
watch(selectedColor, () => {
  if (selectedSize.value && !stockFor(selectedSize.value)) selectedSize.value = null
})

function submit() {
  if (!selectedSize.value || !selectedInStock.value) return
  emit('add', { size: selectedSize.value, color: selectedColor.value })
}

const rating = computed(() => props.product.rating)

/** "Obrempong Collection" — the eyebrow the approved mockup sets above the name. */
const collectionLabel = computed(() => {
  const collection = props.product.collection?.name
  return collection ? `${collection} Collection` : null
})

// Prefilled with the product and, once picked, the size.
const { href: whatsappHref } = useWhatsApp(() => {
  const sizePart = selectedSize.value ? ` in size ${selectedSize.value}` : ''
  return `Hi Gold Coast Tokota, I'd like to order the ${props.product.name}${sizePart}.`
})

// Drives the phone-only sticky CTA: visible exactly while the inline button is
// off screen. An IntersectionObserver rather than a scroll listener so there is
// no work on the main thread between crossings.
const ctaEl = ref<HTMLElement | null>(null)
const showStickyCta = ref(false)
let ctaObserver: IntersectionObserver | null = null

onMounted(() => {
  if (!ctaEl.value || typeof IntersectionObserver === 'undefined') return
  ctaObserver = new IntersectionObserver(
    ([entry]) => {
      showStickyCta.value = !entry!.isIntersecting
    },
    { rootMargin: '0px 0px -80px 0px' },
  )
  ctaObserver.observe(ctaEl.value)
})

onBeforeUnmount(() => ctaObserver?.disconnect())
</script>

<template>
  <div
    class="flex w-full flex-col gap-px md:sticky md:top-4 md:max-h-[calc(100dvh-2rem)] md:w-[340px] md:shrink-0 md:overflow-y-auto lg:w-[384px]"
  >
    <!-- Identity -->
    <div class="flex w-full flex-col gap-1 border-b border-surface pb-4">
      <p v-if="collectionLabel" class="text-tag uppercase tracking-[1px] text-muted">
        {{ collectionLabel }}
      </p>
      <p class="text-caption text-muted">{{ breadcrumb }}</p>

      <div class="flex w-full items-start gap-2.5 text-display-sm font-light">
        <h1 class="min-w-0 flex-1 text-black">{{ product.name }}</h1>
        <CommonPriceDisplay
          class="shrink-0 whitespace-nowrap text-graphite [&_s]:opacity-50"
          :base-price-ghs="product.base_price_ghs"
          :compare-at-ghs="product.compare_at_ghs"
          compact
        />
      </div>

      <div v-if="rating" class="flex w-full items-center gap-2.5">
        <CommonStarRating :value="rating.average" :size="12" :labelled="false" />
        <p class="text-caption text-muted">
          {{ rating.average.toFixed(1) }} ({{ rating.count }}
          {{ rating.count === 1 ? 'Review' : 'Reviews' }})
        </p>
      </div>
    </div>

    <!-- Colour -->
    <div v-if="product.colors?.length" class="flex w-full flex-col gap-2.5 py-[18px]">
      <div class="flex w-full gap-3 text-caption text-black">
        <span class="font-normal">Color</span>
        <span class="font-light">{{ selectedColor }}</span>
      </div>
      <div class="-m-1.5 flex w-full flex-wrap">
        <button
          v-for="color in product.colors"
          :key="color.name"
          type="button"
          class="flex size-11 shrink-0 items-center justify-center rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-graphite"
          :aria-pressed="color.name === selectedColor"
          @click="selectedColor = color.name"
        >
          <!-- The swatch stays 32px as drawn; the button around it is 44px. -->
          <span
            class="block size-8 rounded-full border border-black/10"
            :class="color.name === selectedColor ? 'ring-1 ring-graphite ring-offset-2' : ''"
            :style="{ backgroundColor: color.hex }"
          />
          <span class="sr-only">{{ color.name }}</span>
        </button>
      </div>
    </div>

    <!-- Size -->
    <div v-if="product.sizes?.length" class="flex w-full flex-col gap-2.5 py-[18px]">
      <div class="flex w-full items-start justify-between text-caption">
        <span class="font-normal text-black">Select size (EU)</span>
        <NuxtLink to="/size-guide" class="-my-3 flex min-h-[44px] items-center py-3 font-light text-graphite underline">Size Guide</NuxtLink>
      </div>

      <!-- Same three-state selector the product card uses, one size up. The
           unavailable state is struck through rather than hidden, so the size
           range stays legible. -->
      <ShopSizeSelector
        v-model="selectedSize"
        size="lg"
        :sizes="product.sizes"
        :availability="availability"
        :ignore-stock="product.is_pre_order"
      />

      <p v-if="selectedSize && !selectedInStock" class="text-caption text-sale">
        Size {{ selectedSize }} is out of stock.
      </p>
      <p v-else-if="product.is_pre_order" class="text-caption text-muted">
        Made to order — pre-order pairs ship within three weeks.
      </p>
    </div>

    <!-- Add to cart -->
    <div ref="ctaEl" class="flex w-full flex-col items-center justify-center gap-2 py-8">
      <CommonBrandButton full :disabled="!selectedInStock" @click="submit">
        {{ product.is_pre_order ? 'Pre-Order' : 'Add to Cart' }}
      </CommonBrandButton>
      <p v-if="!selectedSize" class="text-caption text-muted">Select a size to continue.</p>

      <!-- The handoff to WhatsApp, from the approved mockup. Prefilled with the
           product and the chosen size so the shop can answer in one message.
           Hidden entirely when no number is configured — `useWhatsApp` returns
           null rather than an invalid wa.me link (README Feature 6). -->
      <a
        v-if="whatsappHref"
        :href="whatsappHref"
        target="_blank"
        rel="noopener noreferrer"
        class="flex min-h-[44px] w-full items-center justify-center gap-2 border border-graphite bg-white px-4 text-center text-label uppercase text-graphite transition-colors hover:bg-graphite hover:text-white"
      >
        <WhatsappLogo :size="18" weight="fill" />
        Prefer to order via WhatsApp?
      </a>
    </div>

    <!-- Phone-only sticky CTA. The inline button above is far below the fold on
         a phone, and there was nothing to bring it back. Shown only once that
         button has scrolled out of view, so the two never appear together. -->
    <ClientOnly>
      <Transition
        enter-active-class="motion-safe:transition-transform motion-safe:duration-200"
        leave-active-class="motion-safe:transition-transform motion-safe:duration-200"
        enter-from-class="translate-y-full"
        leave-to-class="translate-y-full"
      >
        <div
          v-if="showStickyCta"
          class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white px-5 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 md:hidden"
        >
          <CommonBrandButton full :disabled="!selectedInStock" @click="submit">
            {{ product.is_pre_order ? 'Pre-Order' : 'Add to Cart' }}
          </CommonBrandButton>
        </div>
      </Transition>
    </ClientOnly>

    <!-- Service promises -->
    <div class="flex w-full flex-col gap-6 border-t border-line py-6">
      <div class="flex w-full items-center gap-4">
        <ShopBenefitIcon name="shipping" />
        <div class="flex min-w-0 flex-1 flex-col text-black">
          <p class="text-filter-heading font-normal">Free Shipping</p>
          <p class="text-caption font-light">
            On all Ghana orders over ₵1,500
            <NuxtLink to="/help/shipping" class="underline">Learn more.</NuxtLink>
          </p>
        </div>
      </div>

      <div class="flex w-full items-center gap-4">
        <ShopBenefitIcon name="returns" />
        <div class="flex min-w-0 flex-1 flex-col text-black">
          <p class="text-filter-heading font-normal">Easy Returns &amp; Modifications</p>
          <p class="text-caption font-light">
            Extended returns through January 31.
            <NuxtLink to="/help/returns" class="underline">Returns Details.</NuxtLink>
          </p>
        </div>
      </div>

      <div class="flex w-full items-center gap-4">
        <ShopBenefitIcon name="gift" />
        <div class="flex min-w-0 flex-1 flex-col text-black">
          <p class="text-filter-heading font-normal">Send It As A Gift</p>
          <p class="text-caption font-light">
            Add a free personalized note or marking during checkout.
          </p>
        </div>
      </div>
    </div>

    <!-- Description -->
    <div
      v-if="product.description"
      class="flex w-full flex-col gap-4 border-t border-line pb-3 pt-10 text-black"
    >
      <h2 v-if="product.description_heading" class="text-body font-normal">
        {{ product.description_heading }}
      </h2>
      <p class="text-label font-light">{{ product.description }}</p>
    </div>

    <div
      v-if="product.model_note"
      class="flex w-full items-center border-b border-line py-5 text-black"
    >
      <h2 class="w-[106px] shrink-0 text-body font-normal">Model</h2>
      <p class="min-w-0 flex-1 text-label font-light">{{ product.model_note }}</p>
    </div>

    <div class="flex w-full items-start border-b border-line py-5 text-black">
      <h2 class="w-[106px] shrink-0 text-body font-normal">Fit</h2>
      <div class="flex min-w-0 flex-1 flex-col text-label font-light">
        <p>Questions about fit?</p>
        <!-- These stack as their own rows rather than sitting inside a
             sentence, so they take the 44px floor. -->
        <NuxtLink to="/contact" class="-my-3 flex min-h-[44px] items-center py-3 underline">Contact Us</NuxtLink>
        <NuxtLink to="/size-guide" class="-my-3 flex min-h-[44px] items-center py-3 underline">Size Guide</NuxtLink>
      </div>
    </div>

    <div class="flex w-full flex-col items-start border-b border-line py-5">
      <h2 class="w-full text-body font-normal text-black">Sustainability</h2>
      <img
        src="/design/pdp-sustainability.png"
        alt="Renewed materials and cleaner chemistry certifications"
        class="h-[63px] w-full object-contain object-left"
        loading="lazy"
      >
    </div>
  </div>
</template>
