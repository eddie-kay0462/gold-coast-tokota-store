<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'

const props = defineProps<{ product: ApiProduct }>()

const image = computed(() => props.product.images?.[0] || '/design/product-kentehene.png')

const isOnSale = computed(
  () => !!props.product.compare_at_ghs && props.product.compare_at_ghs > props.product.base_price_ghs,
)

/** Figma prints the saving as a rounded whole percentage ("30% off"). */
const discountLabel = computed(() => {
  if (!isOnSale.value) return null
  const off = 1 - props.product.base_price_ghs / props.product.compare_at_ghs!
  return `${Math.round(off * 100)}% off`
})

// The design shows one swatch pre-selected on each card: the colourway the
// pictured image belongs to. Hovering a swatch is a product-detail concern, so
// here the swatches are indicators, not controls.
const selectedColorIndex = computed(() => {
  const colors = props.product.colors ?? []
  const index = colors.findIndex((color) => color.name === props.product.color)
  return index === -1 ? 0 : index
})
</script>

<template>
  <article class="relative flex min-w-0 flex-col gap-2.5">
    <NuxtLink :to="`/shop/${product.slug}`" class="group flex flex-col gap-2.5">
      <div class="relative aspect-[9/10] w-full overflow-hidden">
        <img
          :src="image"
          :alt="product.name"
          class="size-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-[1.02]"
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
  </article>
</template>
