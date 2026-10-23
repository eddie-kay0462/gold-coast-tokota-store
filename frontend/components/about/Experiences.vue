<script setup lang="ts">
import type { AboutContent } from '~/utils/aboutContent'

defineProps<{ content: AboutContent['experiences'] }>()
</script>

<template>
  <!-- The workshops (Content › Pages › About, experiences); the defaults are
       GOLD_COAST_TOKOTA.md §15's schedule. Black, like the announcement bar
       and the closing band: the page's second dark beat. -->
  <section id="experiences" class="w-full scroll-mt-8 bg-ink text-white">
    <div class="page-gutter section-y mx-auto flex w-full max-w-[1440px] flex-col gap-10">
      <div class="flex flex-col items-start gap-4 md:flex-row md:items-end md:justify-between">
        <div class="flex max-w-[640px] flex-col gap-4">
          <p v-if="content.eyebrow" class="caps-label text-white">{{ content.eyebrow }}</p>
          <h2 class="text-display-section font-light text-white">{{ content.heading }}</h2>
          <p v-if="content.body" class="text-body font-light text-white/80">{{ content.body }}</p>
        </div>
        <CommonBrandButton to="/booking" variant="white" class="shrink-0">Book an experience</CommonBrandButton>
      </div>

      <ul v-if="content.items.length" class="grid w-full grid-cols-1 border-t border-white/25 sm:grid-cols-2 lg:grid-cols-4">
        <li
          v-for="(experience, index) in content.items"
          :key="experience.name + index"
          class="flex flex-col gap-2 border-b border-white/25 py-6 sm:odd:pr-6 sm:even:pl-6 lg:border-b-0 lg:border-r lg:px-6 lg:first:pl-0 lg:last:border-r-0 lg:odd:pr-6 lg:even:pl-6"
        >
          <h3 class="caps-title text-white">{{ experience.name }}</h3>
          <p v-if="experience.when" class="text-caption text-gold">{{ experience.when }}</p>
          <p class="text-body font-light text-white/80">{{ experience.detail }}</p>
        </li>
      </ul>
    </div>
  </section>
</template>
