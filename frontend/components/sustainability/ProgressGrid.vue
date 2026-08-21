<script setup lang="ts">
// Figma 10:978. Each card points at the story that covers that strand of the
// programme; the routes match the Sustainability listing's slugs.
const strands = [
  {
    label: 'Carbon Commitment',
    image: '/design/news-carbon.png',
    alt: 'A globe wrapped in the recycling symbol, rendered in green',
    to: '/blog/our-carbon-commitment',
  },
  {
    label: 'Environmental Initiatives',
    image: '/design/sustainability-environmental.png',
    alt: 'Blue dye circulating behind the porthole of an industrial washing drum',
    to: '/blog/changes-to-packaging',
  },
  {
    label: 'Better Factories',
    image: '/design/sustainability-factories.png',
    alt: 'A maker fitting a cream leather insole into a black shoe by hand',
    to: '/about#factories',
  },
]
</script>

<template>
  <section class="page-gutter section-y flex w-full flex-col items-start gap-3">
    <h2 class="w-full font-normal text-display-md text-black lg:text-display-heading">Our Progress</h2>

    <ul class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="strand in strands" :key="strand.label" class="min-w-0">
        <NuxtLink :to="strand.to" class="group flex w-full flex-col items-start gap-3">
          <img
            :src="strand.image"
            :alt="strand.alt"
            class="h-[220px] w-full object-cover lg:h-[306px]"
            loading="lazy"
          >
          <h3 class="w-full font-light text-display-md text-black group-hover:underline">
            {{ strand.label }}
          </h3>
        </NuxtLink>
      </li>
    </ul>
  </section>
</template>
