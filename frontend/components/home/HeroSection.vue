<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()

// Hero copy and imagery are admin-editable via SiteSetting; the Figma values
// are the fallback so the page is never empty before the CMS is populated.
const headline = computed(() => siteSettings.heroHeadline || 'Your Step into Heritage')
const heroImage = computed(() => siteSettings.heroImage || '/design/hero.png')
</script>

<template>
  <!-- Shop Now has to land above the fold on a laptop, not only on a phone.
       Two things make that happen at desktop sizes, where the sticky header
       alone is 174px tall:

       - **Less top padding** (`pt-10 lg:pt-12`, was 64/96px), so the headline
         sits closer to the header.
       - **An image capped to the space left**:
         `max-h-[calc(100svh-540px)]`, where 540px is everything else in the
         first view (header, padding, headline, subtitle, image margins,
         button, and ~40px of breathing room under it). On a tall screen the
         cap is above the image's natural height and does nothing; on a
         1280×720 laptop the image shrinks to fit. `object-contain` keeps the
         sandals whole as the box shrinks, and the 160px floor stops it
         collapsing on very short windows.

       Below `lg` the button already fits on every phone and tablet size checked,
       so only the padding changes there. -->
  <section class="page-gutter flex w-full flex-col items-center gap-2.5 overflow-hidden pb-16 pt-10 text-center lg:pb-24 lg:pt-12">
    <h1 class="w-full text-display-lg text-ink">{{ headline }}</h1>

    <p class="w-full text-display-sm text-graphite">
      Get your pair of authentic locally-made<br class="hidden sm:inline">
      Ghanaian footwear.
    </p>

    <img
      :src="heroImage"
      alt="Three pairs of handmade Gold Coast Tokota sandals in rust, white and black leather"
      class="my-8 w-full max-w-[1351px] object-contain lg:my-10 lg:max-h-[max(160px,calc(100svh-540px))]"
      width="1170"
      height="350"
    >

    <CommonBrandButton to="/shop" variant="ink">Shop Now</CommonBrandButton>
  </section>
</template>
