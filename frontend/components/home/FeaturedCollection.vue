<script setup lang="ts">
type ApiProduct = { slug: string, name: string, images?: string[] }

const props = defineProps<{ products?: ApiProduct[] | null }>()

// The five lines drawn in the Figma design. These act as the fallback when the
// catalogue API has no featured products yet (Feature 2 is a later phase), so
// the section never renders as an empty row.
const designFallback = [
  { name: 'Adehye', slug: 'adehye', image: '/design/cat-adehye.png' },
  { name: 'Sikapa', slug: 'sikapa', image: '/design/cat-sikapa.png' },
  { name: 'Obrempong', slug: 'obrempong', image: '/design/cat-obrempong.png' },
  { name: 'Kentehene', slug: 'kentehene', image: '/design/cat-kentehene.png' },
  { name: 'Osagyefo', slug: 'osagyefo', image: '/design/cat-osagyefo.png' },
]

const tiles = computed(() => {
  const fromApi = props.products ?? []
  if (!fromApi.length) return designFallback

  return fromApi.slice(0, 5).map((product, index) => ({
    name: product.name,
    slug: product.slug,
    image: product.images?.[0] || designFallback[index]?.image || '/design/cat-adehye.png',
  }))
})
</script>

<template>
  <section class="page-gutter section-y mx-auto flex w-full max-w-[1560px] flex-col items-center gap-[25px]">
    <h2 class="w-full text-center text-display-sm text-graphite">Locally Made, Top Quality</h2>

    <ul class="grid w-full grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
      <li v-for="tile in tiles" :key="tile.slug" class="flex min-w-0 flex-col items-center gap-3">
        <NuxtLink :to="`/shop/${tile.slug}`" class="group flex w-full flex-col items-center gap-3">
          <img
            :src="tile.image"
            :alt="`${tile.name} sandals`"
            class="aspect-[3/4] w-full object-cover"
            loading="lazy"
          >
          <span class="w-full text-center text-label uppercase text-graphite underline group-hover:no-underline">
            {{ tile.name }}
          </span>
        </NuxtLink>
      </li>
    </ul>

    <CommonBrandButton to="/shop" variant="ink">Shop Your Favorites</CommonBrandButton>
  </section>
</template>
