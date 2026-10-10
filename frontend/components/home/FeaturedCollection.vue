<script setup lang="ts">
type ApiProduct = { slug: string, name: string, color?: string | null, images?: string[] }

const props = defineProps<{ products?: ApiProduct[] | null }>()

/**
 * Featured products from the catalogue as a horizontal rail, after the
 * Crockett & Jones product rail the client gave as the reference: tall
 * portrait photos edge to edge, the style name in tracked capitals and the
 * pictured colourway beneath it. See `pages/index.vue` for how the ten are
 * picked.
 *
 * There is no design fallback. The five Figma tiles this once fell back to
 * were not real products and every one linked to a 404. With no products the
 * rail is left out, and the heading's "Shop all" link remains.
 */
const tiles = computed(() =>
  (props.products ?? []).map(product => ({
    name: product.name,
    slug: product.slug,
    color: product.color || null,
    image: product.images?.find(Boolean) ?? null,
  })),
)

const { railEl, canScrollPrev, canScrollNext, scrollByPage, activeSlide, slideCount, thumb }
  = useScrollRail()
</script>

<template>
  <section class="section-y flex w-full flex-col gap-6 lg:gap-8">
    <div class="page-gutter w-full">
      <HomeSectionHeading title="Locally Made, Top Quality" to="/shop" link-label="Shop all" />
    </div>

    <template v-if="tiles.length">
      <!-- Bleeds off the right edge: the left padding is the page gutter, the
           right has none, so a cut-off card says "there's more". The matching
           `scroll-pl-*` makes snapping land a card on the gutter rather than
           flush against the viewport. -->
      <ul
        ref="railEl"
        class="flex w-full snap-x snap-mandatory gap-1.5 overflow-x-auto scroll-smooth pl-5 scroll-pl-5 md:pl-10 md:scroll-pl-10 lg:pl-[60px] lg:scroll-pl-[60px] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        aria-label="Featured products"
      >
        <li
          v-for="tile in tiles"
          :key="tile.slug"
          class="w-[72%] shrink-0 snap-start sm:w-[40%] lg:w-[23%]"
        >
          <NuxtLink :to="`/shop/${tile.slug}`" class="group flex w-full flex-col items-center gap-4">
            <img
              v-if="tile.image"
              :src="tile.image"
              :alt="tile.color ? `${tile.name} in ${tile.color}` : tile.name"
              class="aspect-[3/4] w-full bg-surface object-cover"
              loading="lazy"
            >
            <!-- No photo yet: a plain frame that keeps the row aligned. -->
            <span v-else class="aspect-[3/4] w-full bg-surface" aria-hidden="true" />
            <span class="flex w-full flex-col items-center gap-1 px-2 text-center">
              <span class="text-label font-bold uppercase tracking-[1.4px] text-ink group-hover:underline">
                {{ tile.name }}
              </span>
              <span v-if="tile.color" class="text-label tracking-[0.4px] text-subtle">{{ tile.color }}</span>
            </span>
          </NuxtLink>
        </li>
      </ul>

      <div class="page-gutter mx-auto w-full max-w-[1440px]">
        <CommonRailControls
          :can-prev="canScrollPrev"
          :can-next="canScrollNext"
          :thumb="thumb"
          :current="activeSlide"
          :total="slideCount"
          label="products"
          @prev="scrollByPage(-1)"
          @next="scrollByPage(1)"
        />
      </div>
    </template>
  </section>
</template>
