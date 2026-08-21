<script setup lang="ts">
// Figma 6:674. The third card reuses the factory photograph, as drawn.
const cards = [
  {
    label: 'Our Products',
    image: '/design/about-explore-products.png',
    alt: 'Handmade leather sandals arranged on a workbench',
    to: '/shop',
  },
  {
    label: 'Our Processes',
    image: '/design/about-explore-processes.png',
    alt: 'A craftsman cutting leather by hand',
    to: '/about#factories',
  },
  {
    label: 'Careers',
    image: '/design/about-factories.png',
    alt: 'Two artisans working side by side in the workshop',
    to: '/careers',
  },
]
</script>

<template>
  <section class="page-gutter section-y mx-auto flex w-full max-w-[1160px] flex-col items-center gap-5">
    <h2 class="w-full text-center font-light text-black text-display-md">More to Explore</h2>

    <div class="flex w-full flex-col gap-5 sm:flex-row sm:items-start">
      <NuxtLink
        v-for="card in cards"
        :key="card.label"
        :to="card.to"
        class="flex min-w-0 flex-1 flex-col items-start gap-2.5 group"
      >
        <img
          :src="card.image"
          :alt="card.alt"
          class="aspect-[16/9] w-full object-cover"
          loading="lazy"
        >
        <span class="w-full text-center text-body tracking-[0.2px] text-subtle group-hover:underline">
          {{ card.label }}
        </span>
      </NuxtLink>
    </div>
  </section>
</template>
