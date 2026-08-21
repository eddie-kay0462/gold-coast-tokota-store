<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()

// Hero copy and imagery are admin-editable via SiteSetting; the Figma values
// are the fallback so the page is never empty before the CMS is populated.
const headline = computed(() => siteSettings.heroHeadline || 'Your Step into Heritage')
const heroImage = computed(() => siteSettings.heroImage || '/design/hero.png')
</script>

<template>
  <section class="page-gutter flex w-full flex-col items-center gap-2.5 overflow-hidden py-16 text-center lg:py-24">
    <h1 class="w-full text-display-lg text-ink">{{ headline }}</h1>

    <p class="w-full text-display-sm text-graphite">
      Get your pair of authentic locally-made<br class="hidden sm:inline">
      Ghanaian footwear.
    </p>

    <img
      :src="heroImage"
      alt="Three pairs of handmade Gold Coast Tokota sandals in rust, white and black leather"
      class="my-8 w-full max-w-[1351px] object-contain lg:my-12"
      width="1170"
      height="350"
    >

    <CommonBrandButton to="/shop" variant="graphite">Shop Now</CommonBrandButton>
  </section>
</template>
