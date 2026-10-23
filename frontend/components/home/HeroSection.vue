<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'

const siteSettings = useSiteSettingsStore()

// Hero copy and imagery are admin-editable via SiteSetting; the Figma values
// are the fallback so the page is never empty before the CMS is populated.
const headline = computed(() => siteSettings.heroHeadline || 'Your Step into Heritage')
const heroImage = computed(() => siteSettings.heroImage || '/design/hero.png')
const heroAlt = 'Three pairs of handmade Gold Coast Tokota sandals in rust, white and black leather'
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

    <!-- Phones: one pair at a time. The photo is three pairs side by side, so
         a window one third of its width shows the centre ahenema; on opening
         it pans to the right-hand pair, across to the left-hand one, and back
         to the centre, once. The window's height is capped against the
         viewport so Shop Now stays on screen whatever the phone. -->
    <div class="hero-window relative my-6 w-full overflow-hidden sm:hidden" role="img" :aria-label="heroAlt">
      <img
        :src="heroImage"
        alt=""
        class="hero-pan absolute left-0 top-0 h-full w-[300%] max-w-none object-cover"
        width="1170"
        height="361"
        fetchpriority="high"
      >
    </div>

    <img
      :src="heroImage"
      :alt="heroAlt"
      class="my-8 hidden w-full max-w-[1351px] object-contain sm:block lg:my-10 lg:max-h-[max(160px,calc(100svh-540px))]"
      width="1170"
      height="361"
    >

    <CommonBrandButton to="/shop" variant="ink">Shop Now</CommonBrandButton>
  </section>
</template>

<style scoped>
/* One pair's share of the three-pair photo (390 of 1170 by 361), capped so
   the headline, this and Shop Now fit a short phone: 380px is the header,
   headline, subtitle, margins and button. */
.hero-window {
  height: min(calc((100vw - 40px) * 361 / 390), max(200px, calc(100svh - 380px)));
}

/* The image is three windows wide; -33.333% centres the middle pair,
   -66.667% shows the right-hand pair, 0 the left-hand one. */
.hero-pan {
  transform: translateX(-33.333%);
  animation: hero-pan 6s ease-in-out 0.6s 1 both;
}

@keyframes hero-pan {
  0%, 12% { transform: translateX(-33.333%); }
  32%, 46% { transform: translateX(-66.667%); }
  66%, 80% { transform: translateX(0); }
  100% { transform: translateX(-33.333%); }
}

@media (prefers-reduced-motion: reduce) {
  .hero-pan { animation: none; }
}
</style>
