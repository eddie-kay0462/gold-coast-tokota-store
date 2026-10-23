<script setup lang="ts">
import type { AboutContent } from '~/utils/aboutContent'

defineProps<{ content: AboutContent['materials'] }>()
</script>

<template>
  <!-- What the pairs are made of (Content › Pages › About, materials).
       `#sustainability` is kept here: the home banner and the old
       /sustainability URL both land on it. -->
  <section id="sustainability" class="w-full scroll-mt-8 bg-timberwolf">
    <div class="page-gutter section-y mx-auto grid w-full max-w-[1440px] grid-cols-1 gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-16">
      <div class="flex flex-col items-start gap-4">
        <p v-if="content.eyebrow" class="caps-label text-graphite">{{ content.eyebrow }}</p>
        <h2 class="text-display-section font-light text-ink">{{ content.heading }}</h2>
        <p class="max-w-[480px] whitespace-pre-line text-body font-light text-graphite">{{ content.body }}</p>
        <CommonBrandButton to="/shop" class="mt-2">See what we’ve made</CommonBrandButton>
      </div>

      <ol v-if="content.items.length" class="grid w-full grid-cols-1 border-t border-ink sm:grid-cols-2">
        <li
          v-for="(material, index) in content.items"
          :key="material.name + index"
          class="flex flex-col gap-2 border-b border-ink/15 py-6 sm:odd:pr-8 sm:even:pl-8"
        >
          <span class="text-caption text-muted">{{ String(index + 1).padStart(2, '0') }}</span>
          <h3 class="caps-title text-ink">{{ material.name }}</h3>
          <p class="text-body font-light text-graphite">{{ material.body }}</p>
        </li>
      </ol>
    </div>
  </section>
</template>
