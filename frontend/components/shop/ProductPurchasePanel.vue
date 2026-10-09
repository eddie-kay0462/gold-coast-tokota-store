<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'
import { whatsappMessage } from '~/utils/whatsapp'
import { SIZE_GUIDE_BETWEEN_SIZES } from '~/utils/sizeGuide'

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

/** Shared with the page, which swaps the gallery to the chosen colourway. */
const selectedColor = defineModel<string>('color', { default: '' })
if (!selectedColor.value) selectedColor.value = props.product.color ?? props.product.colors?.[0]?.name ?? ''
const selectedSize = ref<string | null>(null)

const isOnSale = computed(
  () => !!props.product.compare_at_ghs && props.product.compare_at_ghs > props.product.base_price_ghs,
)

/**
 * Stock for the chosen colour when the API reports per-colour stock, otherwise
 * the all-colours sum. Without the per-colour map, picking Tan would show
 * size 42 as available just because Black has a pair.
 */
const availability = computed(
  () => props.liveStock
    ?? props.product.variant_availability?.[selectedColor.value]
    ?? props.product.size_availability,
)

/** A colourway with nothing left in any size — its swatch is marked. */
function colourSoldOut(name: string) {
  if (props.product.is_pre_order) return false
  const sizes = props.product.variant_availability?.[name]
  return !!sizes && Object.values(sizes).every((count) => count <= 0)
}

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

// --- Size guide --------------------------------------------------------------
// Opens over the page instead of navigating, so the shopper keeps their colour,
// size and scroll position. The links keep their real `href`, so a modified
// click (new tab or window) still goes to `/size-guide` as usual, and so does
// a visit without JavaScript.
const sizeGuideOpen = ref(false)

function openSizeGuide(event: MouseEvent) {
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return
  event.preventDefault()
  sizeGuideOpen.value = true
}

// For the "Questions about caring for your pair?" link in Materials & Care.
const { href: whatsappHref } = useWhatsApp(() => whatsappMessage.product(props.product.name, null))
const { whatsappClick } = useAnalytics()
</script>

<template>
  <!--
    From `md` the panel is sticky with its own vertical scroll, and a scroll
    container clips on both axes. The colour and size rows use negative margins
    (6px and 4px) so their 44px hit areas line up with the text. Flush against
    the panel's edge, that clipped the selected swatch's ring on the left, and on
    the right it left a few pixels to scroll sideways into. A trackpad swipe or
    tabbing to a size then shifted the whole panel and cut the first letter off
    every line.

    So the panel gets 12px of padding each side for those rows to use, offset by
    an equal negative margin. Widths are 24px wider to match, so the content is
    still 340/400/440px wide and sits exactly where it did. `overflow-x-hidden`
    stops sideways scrolling outright.
  -->
  <div
    class="flex w-full flex-col gap-px md:sticky md:top-4 md:-mx-3 md:max-h-[calc(100dvh-2rem)] md:w-[364px] md:shrink-0 md:overflow-y-auto md:overflow-x-hidden md:px-3 lg:w-[424px] xl:w-[464px]"
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
          class="shrink-0 whitespace-nowrap text-graphite"
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
            class="relative block size-8 overflow-hidden rounded-full border border-black/10"
            :class="[
              color.name === selectedColor ? 'ring-1 ring-graphite ring-offset-2' : '',
              colourSoldOut(color.name) ? 'opacity-40' : '',
            ]"
            :style="{ backgroundColor: color.hex }"
          >
            <!-- Struck through, like an unavailable size: still choosable, so
                 the photos can be seen, but plainly not in stock. -->
            <span
              v-if="colourSoldOut(color.name)"
              class="absolute left-1/2 top-1/2 h-px w-[140%] -translate-x-1/2 -translate-y-1/2 -rotate-45 bg-graphite"
              aria-hidden="true"
            />
          </span>
          <span class="sr-only">{{ color.name }}{{ colourSoldOut(color.name) ? ' (out of stock)' : '' }}</span>
        </button>
      </div>
    </div>

    <!-- Size -->
    <div v-if="product.sizes?.length" class="flex w-full flex-col gap-2.5 py-[18px]">
      <div class="flex w-full items-start justify-between text-caption">
        <span class="font-normal text-black">Select size (EU)</span>
        <a href="/size-guide" class="-my-3 flex min-h-[44px] items-center py-3 font-light text-graphite underline" aria-haspopup="dialog" @click="openSizeGuide">Size Guide</a>
      </div>

      <!-- Same three-state selector the product card uses, one size up. The
           unavailable state is struck through rather than hidden, so the size
           range stays legible. -->
      <ShopSizeSelector
        v-model="selectedSize"
        size="lg"
        hint
        :sizes="product.sizes"
        :availability="availability"
        :ignore-stock="product.is_pre_order"
      />

      <p v-if="selectedSize && !selectedInStock" class="text-caption text-sale">
        Size {{ selectedSize }} is out of stock<template v-if="product.variant_availability"> in {{ selectedColor }}</template>.
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
      <CommonWhatsAppLink
        source="product-detail"
        full
        :message="whatsappMessage.product(product.name, selectedSize)"
      >
        Prefer to order via WhatsApp?
      </CommonWhatsAppLink>
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
          class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white pb-[max(0.75rem,env(safe-area-inset-bottom))] pl-5 pr-[4.75rem] pt-3 md:hidden"
        >
          <CommonBrandButton full :disabled="!selectedInStock" @click="submit">
            {{ product.is_pre_order ? 'Pre-Order' : 'Add to Cart' }}
          </CommonBrandButton>
        </div>
      </Transition>
    </ClientOnly>

    <!--
      Product details as an accordion. This replaces a stack of always-open
      blocks: three service promises with icons, the description, Model, Fit and
      Sustainability.

      Shipping and returns copy comes from GOLD_COAST_TOKOTA.md §8, §9 and §21
      only. The old service promises didn't match it: "Extended returns through
      January 31" contradicted the 7-day window, and "Free shipping over ₵1,500"
      and a free gift note are policies it doesn't state. See FOR_THE_TEAM.md.
    -->
    <div class="w-full border-t border-line">
      <CommonAccordionItem title="Description">
        <div class="flex flex-col gap-3">
          <p v-if="product.description_heading" class="font-normal text-black">
            {{ product.description_heading }}
          </p>
          <!-- No product has a description yet; until one is written in the
               admin, the brand's own line (GOLD_COAST_TOKOTA.md §6) stands in. -->
          <p>
            {{ product.description || 'We handcraft sustainable Ahenema sandals that celebrate Ghanaian heritage while giving discarded materials a second life.' }}
          </p>
        </div>
      </CommonAccordionItem>

      <CommonAccordionItem title="Materials & Care">
        <div class="flex flex-col gap-3">
          <ul v-if="product.materials?.length" class="flex flex-wrap gap-2">
            <li
              v-for="material in product.materials"
              :key="material"
              class="border border-line px-2.5 py-1.5 text-tag uppercase text-graphite"
            >
              {{ material }}
            </li>
          </ul>
          <p>
            Each pair is handcrafted, so slight variations in colour, texture and finish are
            part of what makes it unique, not defects.
          </p>
          <p v-if="whatsappHref">
            Questions about caring for your pair?
            <a
              :href="whatsappHref"
              target="_blank"
              rel="noopener noreferrer"
              class="underline underline-offset-2 hover:no-underline"
              @click="whatsappClick({ source: 'product-detail' })"
            >Ask us on WhatsApp</a>.
          </p>
        </div>
      </CommonAccordionItem>

      <CommonAccordionItem title="Fit">
        <div class="flex flex-col gap-3">
          <p v-if="product.model_note">{{ product.model_note }}</p>
          <p>Sizes are EU. {{ SIZE_GUIDE_BETWEEN_SIZES }}</p>
          <div class="-my-2.5 flex flex-wrap gap-x-6">
            <a
              href="/size-guide"
              class="flex min-h-[44px] items-center text-black underline underline-offset-2 hover:no-underline"
              aria-haspopup="dialog"
              @click="openSizeGuide"
            >Size Guide</a>
            <NuxtLink
              to="/contact"
              class="flex min-h-[44px] items-center text-black underline underline-offset-2 hover:no-underline"
            >
              Contact Us
            </NuxtLink>
          </div>
        </div>
      </CommonAccordionItem>

      <CommonAccordionItem title="Shipping & Returns">
        <div class="flex flex-col gap-3">
          <p>
            Orders are processed within 48 hours of payment. Delivery in Ghana takes 1–2 business
            days. International delivery takes 5–21 business days depending on the destination,
            and any customs duties are paid by the customer.
          </p>
          <p>
            Returns are accepted within 7 days of receiving your order if a pair arrives
            defective, damaged or incorrect. Size exchanges are available subject to stock.
            Custom-made pairs, and sale items unless defective, can’t be returned.
          </p>
          <div class="-my-2.5 flex flex-wrap gap-x-6">
            <NuxtLink
              to="/help/shipping"
              class="flex min-h-[44px] items-center text-black underline underline-offset-2 hover:no-underline"
            >
              Shipping details
            </NuxtLink>
            <NuxtLink
              to="/help/returns"
              class="flex min-h-[44px] items-center text-black underline underline-offset-2 hover:no-underline"
            >
              Returns details
            </NuxtLink>
          </div>
        </div>
      </CommonAccordionItem>

      <CommonAccordionItem title="Sustainability">
        <div class="flex flex-col gap-4">
          <p>
            Gold Coast Tokota transforms discarded materials into handcrafted footwear, preserving
            Ghanaian craftsmanship while advancing sustainability.
          </p>
          <img
            src="/design/pdp-sustainability.png"
            alt="Renewed materials and cleaner chemistry certifications"
            class="h-[63px] w-full object-contain object-left"
            loading="lazy"
          >
        </div>
      </CommonAccordionItem>
    </div>

    <ShopSizeGuideModal :open="sizeGuideOpen" @close="sizeGuideOpen = false" />
  </div>
</template>
