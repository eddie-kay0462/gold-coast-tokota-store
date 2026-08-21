<script setup lang="ts">
withDefaults(defineProps<{
  /** Anchor target — Footer and Header deep-link into these sections. */
  id?: string
  eyebrow: string
  /** Newlines are preserved, matching the designed line breaks. */
  heading: string
  body: string
  image: string
  alt: string
  /** Places the photograph after the copy instead of before it. */
  reverse?: boolean
  /** The warm Timberwolf ground; the prices section sits on white. */
  tinted?: boolean
  /** `contain` for flat artwork (the cost breakdown), `cover` for photography. */
  contain?: boolean
  /** Desktop section height in px, per the Figma frame. */
  height?: number
}>(), {
  tinted: true,
  height: 733,
})
</script>

<template>
  <!-- Figma 6:645 / 6:648 / 6:656 — the same two-up story block, mirrored. -->
  <section
    :id="id"
    class="flex w-full flex-col items-stretch lg:flex-row"
    :class="[tinted && 'bg-timberwolf', reverse && 'lg:flex-row-reverse']"
    :style="{ '--about-section-height': `${height}px` }"
  >
    <!-- Image half is intentionally full-bleed; only the text half takes the gutter. -->
    <div class="h-[320px] w-full shrink-0 lg:h-[var(--about-section-height)] lg:w-1/2">
      <img
        :src="image"
        :alt="alt"
        class="size-full"
        :class="contain ? 'object-contain' : 'object-cover'"
        loading="lazy"
      >
    </div>

    <div
      class="page-gutter flex min-w-0 flex-1 flex-col items-center justify-center gap-5 py-12 font-light text-black lg:h-[var(--about-section-height)] lg:py-0"
    >
      <div class="flex w-full flex-col items-start">
        <p class="w-full font-normal text-caption">{{ eyebrow }}</p>
        <h2 class="w-full whitespace-pre-line text-display-sm lg:text-display-section">{{ heading }}</h2>
      </div>
      <p class="w-full text-label">{{ body }}</p>
    </div>
  </section>
</template>
