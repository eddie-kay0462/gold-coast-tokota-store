<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'
import { whatsappMessage } from '~/utils/whatsapp'

const props = defineProps<{ product: ApiProduct }>()

const image = computed(() => props.product.images?.[0] || '/design/product-kentehene.png')
/** The approved design cross-fades to a second shot on hover. Optional: most
 *  products still carry a single image, and the fade is skipped for those. */
const hoverImage = computed(() => props.product.images?.[1] ?? null)

const isOnSale = computed(
  () => !!props.product.compare_at_ghs && props.product.compare_at_ghs > props.product.base_price_ghs,
)

/** Figma prints the saving as a rounded whole percentage ("30% off"). */
const discountLabel = computed(() => {
  if (!isOnSale.value) return null
  const off = 1 - props.product.base_price_ghs / props.product.compare_at_ghs!
  return `${Math.round(off * 100)}% off`
})

/**
 * The stock badge from the approved mockup, bottom-left of the image. Distinct
 * from the top-left price/pre-order chips above, which are the original design's
 * and say something different (a promotion, not availability).
 */
const STOCK_BADGES: Record<string, { label: string, class: string }> = {
  limited_stock: { label: 'Limited stock', class: 'bg-gold text-chrome' },
  back_in_stock: { label: 'Back in stock', class: 'bg-chrome text-white' },
  out_of_stock: { label: 'Out of stock', class: 'bg-line text-muted' },
}

const stockBadge = computed(() =>
  props.product.merchandising_badge ? STOCK_BADGES[props.product.merchandising_badge] ?? null : null,
)

const isSoldOut = computed(() => props.product.merchandising_badge === 'out_of_stock')

// The design shows one swatch pre-selected on each card: the colourway the
// pictured image belongs to. Hovering a swatch is a product-detail concern, so
// here the swatches are indicators, not controls.
const selectedColorIndex = computed(() => {
  const colors = props.product.colors ?? []
  const index = colors.findIndex((color) => color.name === props.product.color)
  return index === -1 ? 0 : index
})

// --- Size + add to cart ---------------------------------------------------
// The approved design puts the size picker on the card itself, so a customer
// can buy from the grid without opening the product. Everything below exists
// for that.

const selectedSize = ref<string | null>(null)
const sizes = computed(() => props.product.sizes ?? [])

function stockFor(size: string) {
  if (props.product.is_pre_order) return 1
  const availability = props.product.size_availability
  if (!availability) return 1
  return availability[size] ?? 0
}

const canAdd = computed(
  () => !isSoldOut.value && !!selectedSize.value && stockFor(selectedSize.value) > 0,
)

const addToCart = useAddToCart()

function add() {
  if (!canAdd.value) return
  addToCart(props.product, { size: selectedSize.value, color: props.product.color })
}

</script>

<template>
  <article class="group/card relative flex min-w-0 flex-col gap-2.5">
    <NuxtLink :to="`/shop/${product.slug}`" class="group flex flex-col gap-2.5">
      <div class="relative aspect-[9/10] w-full overflow-hidden bg-surface">
        <img
          :src="image"
          :alt="product.name"
          class="size-full object-cover"
          loading="lazy"
        >
        <!-- Cross-fade to the second shot. `pointer-events-none` so it never
             steals the hover that is revealing it. -->
        <img
          v-if="hoverImage"
          :src="hoverImage"
          alt=""
          aria-hidden="true"
          class="pointer-events-none absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-500 motion-safe:group-hover/card:opacity-100"
          loading="lazy"
        >

        <!-- Badge stack, top-left of the image (Figma: Tag/price). -->
        <div class="absolute left-2 top-2 flex items-center gap-1">
          <span
            v-if="discountLabel"
            class="bg-white px-1.5 py-1 text-center text-caption text-sale"
          >
            {{ discountLabel }}
          </span>
          <span
            v-if="product.is_pre_order"
            class="bg-white px-1.5 py-1 text-center text-caption text-subtle"
          >
            Pre-Order
          </span>
        </div>

        <!-- Stock badge, bottom-left, from the approved mockup. -->
        <span
          v-if="stockBadge"
          class="absolute bottom-2 left-2 px-2.5 py-1.5 text-tag uppercase"
          :class="stockBadge.class"
        >
          {{ stockBadge.label }}
        </span>
      </div>

      <div class="flex flex-col gap-[3px] text-caption">
        <div class="flex gap-3 py-2 text-graphite">
          <h3 class="min-w-0 flex-1 font-light group-hover:underline">{{ product.name }}</h3>
          <CommonPriceDisplay
            class="shrink-0 whitespace-nowrap text-right"
            :base-price-ghs="product.base_price_ghs"
            :compare-at-ghs="product.compare_at_ghs"
            compact
          />
        </div>
        <p class="h-4 font-light text-muted">{{ product.color }}</p>
      </div>
    </NuxtLink>

    <!-- Wraps: a product with five or more colourways pushed past the card. -->
    <ul v-if="product.colors?.length" class="flex flex-wrap items-center gap-2.5">
      <li
        v-for="(color, index) in product.colors"
        :key="color.name"
        class="size-5 shrink-0 rounded-full"
        :class="index === selectedColorIndex ? 'ring-1 ring-graphite ring-offset-2' : ''"
      >
        <span
          class="block size-full rounded-full border border-black/10"
          :style="{ backgroundColor: color.hex }"
        />
        <span class="sr-only">{{ color.name }}</span>
      </li>
    </ul>

    <ul v-if="product.tags?.length" class="flex flex-wrap items-start gap-2">
      <li
        v-for="tag in product.tags"
        :key="tag"
        class="border border-line px-2 py-1.5 text-center text-tag uppercase text-muted"
      >
        {{ tag }}
      </li>
    </ul>

    <!-- Buy block. Outside the NuxtLink above: these are controls, and nesting
         a button inside an anchor is invalid and unusable with a keyboard. -->
    <div v-if="sizes.length" class="mt-auto flex flex-col gap-2.5 pt-1">
      <ShopSizeSelector
        v-model="selectedSize"
        size="sm"
        :sizes="sizes"
        :availability="product.size_availability"
        :ignore-stock="product.is_pre_order"
      />

      <CommonBrandButton full variant="ink" :disabled="!canAdd" @click="add">
        {{ isSoldOut ? 'Out of stock' : product.is_pre_order ? 'Pre-Order' : 'Add to cart' }}
      </CommonBrandButton>

      <CommonWhatsAppLink
        source="product-card"
        full
        :message="whatsappMessage.product(product.name, selectedSize)"
      >
        Order on WhatsApp
      </CommonWhatsAppLink>
    </div>
  </article>
</template>
