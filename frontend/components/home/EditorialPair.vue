<script setup lang="ts">
const features = [
  {
    heading: 'Our Holiday Gift Picks',
    image: '/design/editorial-gift-picks.png',
    alt: 'Gold Coast Tokota gift packaging',
    caption: 'The best presents for everyone on your list.',
    linkLabel: 'Read More',
    to: '/blog/holiday-gift-picks',
  },
  {
    heading: 'Cleaner Footwear',
    image: '/design/editorial-cleaner-footwear.png',
    alt: 'Recycling symbol rendered in green beside a pair of leather shoes',
    caption: 'See the sustainability efforts behind each of our products.',
    linkLabel: 'Learn More',
    to: '/about#sustainability',
  },
]
</script>

<template>
  <section class="flex w-full flex-col items-start gap-5 px-5 py-16 lg:flex-row lg:px-[185px] lg:py-[90px]">
    <article
      v-for="feature in features"
      :key="feature.heading"
      class="flex min-w-0 flex-1 flex-col items-center gap-5"
    >
      <h2 class="w-full text-center text-display-sm text-graphite">{{ feature.heading }}</h2>

      <img
        :src="feature.image"
        :alt="feature.alt"
        class="h-[626px] w-full object-cover"
        loading="lazy"
      >

      <p class="w-full text-center text-label text-graphite">{{ feature.caption }}</p>

      <NuxtLink
        :to="feature.to"
        class="flex items-center justify-center px-5 py-[15px] text-center text-label-link text-graphite underline hover:no-underline"
      >
        {{ feature.linkLabel }}
      </NuxtLink>
    </article>
  </section>
</template>
