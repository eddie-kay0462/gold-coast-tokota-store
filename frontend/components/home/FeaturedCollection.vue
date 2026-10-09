<script setup lang="ts">
type ApiProduct = { slug: string, name: string, images?: string[] }

const props = defineProps<{ products?: ApiProduct[] | null }>()

/**
 * Featured products from the catalogue (see `pages/index.vue` for how the five
 * are picked).
 *
 * This used to fall back to the five tiles drawn in the Figma design whenever
 * the API returned nothing. None of those five (Adehye, Sikapa, Obrempong,
 * Kentehene, Osagyefo) is a real product, so every tile linked to a 404, and
 * a real product with no photo borrowed a design image that wasn't of it.
 * With no products the grid is left out, and the button to the shop remains.
 */
const tiles = computed(() =>
  (props.products ?? []).slice(0, 5).map(product => ({
    name: product.name,
    slug: product.slug,
    image: product.images?.find(Boolean) ?? null,
  })),
)
</script>

<template>
  <section class="page-gutter section-y mx-auto flex w-full max-w-[1560px] flex-col items-center gap-[25px]">
    <h2 class="w-full text-center text-display-sm text-graphite">Locally Made, Top Quality</h2>

    <ul v-if="tiles.length" class="grid w-full grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
      <li v-for="tile in tiles" :key="tile.slug" class="flex min-w-0 flex-col items-center gap-3">
        <NuxtLink :to="`/shop/${tile.slug}`" class="group flex w-full flex-col items-center gap-3">
          <img
            v-if="tile.image"
            :src="tile.image"
            :alt="`${tile.name} sandals`"
            class="aspect-[3/4] w-full object-cover"
            loading="lazy"
          >
          <!-- No photo yet: a plain frame that keeps the row aligned. -->
          <span v-else class="aspect-[3/4] w-full bg-surface" aria-hidden="true" />
          <span class="w-full text-center text-label uppercase text-graphite underline group-hover:no-underline">
            {{ tile.name }}
          </span>
        </NuxtLink>
      </li>
    </ul>

    <CommonBrandButton to="/shop" variant="ink">Shop Your Favorites</CommonBrandButton>
  </section>
</template>
