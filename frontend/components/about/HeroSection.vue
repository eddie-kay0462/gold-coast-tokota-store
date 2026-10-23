<script setup lang="ts">
import { aboutImage, type AboutContent } from '~/utils/aboutContent'

defineProps<{ content: AboutContent['hero'] }>()
</script>

<template>
  <!-- The brand phrase on the site's deep black, beside a photograph. Edited
       in Content › Pages › About (hero). -->
  <section class="w-full bg-ink text-white">
    <div class="mx-auto grid w-full max-w-[1440px] grid-cols-1 items-stretch md:grid-cols-2">
      <div class="page-gutter flex flex-col justify-center gap-6 py-16 md:py-24 lg:py-32">
        <p v-if="content.eyebrow" class="caps-label text-white">{{ content.eyebrow }}</p>
        <h1 class="whitespace-pre-line text-display-xl font-light text-white">{{ content.heading }}</h1>
        <p class="max-w-[520px] text-lede font-light text-white">{{ content.lede }}</p>
        <div class="flex flex-col items-stretch gap-3 pt-2 sm:flex-row sm:items-start">
          <CommonBrandButton to="/shop" variant="white">Shop the collection</CommonBrandButton>
          <CommonBrandButton to="/booking" variant="white">Visit the workshop</CommonBrandButton>
        </div>
      </div>

      <div class="relative min-h-[360px] md:min-h-[560px]">
        <img
          :src="aboutImage(content)"
          :alt="content.imageAlt"
          class="absolute inset-0 size-full bg-surface object-cover"
          fetchpriority="high"
        >
      </div>
    </div>
  </section>
</template>
