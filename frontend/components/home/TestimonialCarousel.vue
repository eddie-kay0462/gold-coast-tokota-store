<script setup lang="ts">
import { PhStar as Star } from '@phosphor-icons/vue'

// The Figma frame shows four indicator dots but supplies a single real
// testimonial. Only that one is included here — the rest of the carousel fills
// in once more genuine reviews exist, rather than shipping invented ones.
const testimonials = [
  {
    id: 'aseye-bakah',
    rating: 5,
    quote: 'Gold Coast Tokota gave me an amazing customer service experience, I placed an order for two customised ahenemaa, hoping it was going to take the usual 3 days delivery period. This was delivered in less than 24hours. You guys deserve my hugs and roses. Keep it up.',
    author: 'Aseye Bakah',
    product: 'The Original Ahenema',
    productTo: '/shop/the-original-ahenema',
    image: '/design/testimonial.png',
  },
]

const activeIndex = ref(0)
const active = computed(() => testimonials[activeIndex.value]!)
const hasMultiple = computed(() => testimonials.length > 1)

function step(direction: -1 | 1) {
  activeIndex.value = (activeIndex.value + direction + testimonials.length) % testimonials.length
}
</script>

<template>
  <section class="flex w-full flex-col items-center gap-[30px]">
    <div class="page-gutter flex w-full flex-col items-center gap-8 lg:flex-row lg:gap-[74px]">
      <CommonCarouselArrow
        v-if="hasMultiple"
        direction="left"
        :size="24"
        class="hidden lg:flex"
        @click="step(-1)"
      />

      <div class="flex min-w-0 flex-1 flex-col items-start gap-8 lg:gap-10 lg:px-[62px]">
        <h2 class="w-full text-body text-ink">People Are Talking</h2>

        <div class="flex w-full flex-col items-start gap-[15px]">
          <p class="flex items-center gap-0.5" :aria-label="`${active.rating} out of 5 stars`">
            <Star v-for="star in active.rating" :key="star" :size="14" weight="fill" />
          </p>
          <blockquote class="w-full text-display-sm text-ink">
            &ldquo;{{ active.quote }}&rdquo;
          </blockquote>
        </div>

        <p class="w-full text-label text-ink">
          <span>&#45;&#45; {{ active.author }}, </span>
          <NuxtLink :to="active.productTo" class="text-label-link underline">
            {{ active.product }}
          </NuxtLink>
        </p>

        <CommonBrandButton to="/shop" variant="graphite">Get Yours Now</CommonBrandButton>
      </div>

      <div class="min-w-0 flex-1">
        <img
          :src="active.image"
          :alt="`Sandals worn by ${active.author}`"
          class="max-h-[695px] w-full object-contain"
          loading="lazy"
        >
      </div>

      <CommonCarouselArrow
        v-if="hasMultiple"
        direction="right"
        :size="24"
        class="hidden lg:flex"
        @click="step(1)"
      />
    </div>

    <div class="page-gutter mx-auto flex w-full max-w-[1406px] flex-col items-center gap-12 lg:gap-[73px]">
      <CommonCarouselDots
        v-if="hasMultiple"
        :count="testimonials.length"
        :active-index="activeIndex"
        label="Customer testimonials"
        @select="(index) => (activeIndex = index)"
      />
      <div class="h-px w-full bg-ink" />
    </div>
  </section>
</template>
