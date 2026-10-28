<script setup lang="ts">
import type { AboutContent } from '~/utils/aboutContent'

defineProps<{ content: AboutContent['recognition'] }>()
</script>

<template>
  <!-- Programmes and awards, each linked to the published source. Text rather
       than partner logos: we have no permission to use their marks. -->
  <section id="recognition" class="page-gutter section-y mx-auto flex w-full max-w-[1440px] scroll-mt-8 flex-col gap-8">
    <div class="flex max-w-[640px] flex-col gap-4">
      <p v-if="content.eyebrow" class="caps-label text-graphite">{{ content.eyebrow }}</p>
      <h2 class="text-display-section font-light text-ink">{{ content.heading }}</h2>
    </div>

    <ul class="flex w-full flex-col border-t border-ink">
      <li v-for="(item, index) in content.items" :key="item.name + index" class="border-b border-line">
        <component
          :is="item.href ? 'a' : 'div'"
          :href="item.href || undefined"
          :target="item.href ? '_blank' : undefined"
          :rel="item.href ? 'noopener noreferrer' : undefined"
          class="group grid w-full grid-cols-1 gap-1 py-5 sm:grid-cols-[minmax(0,4fr)_minmax(0,7fr)_5rem] sm:items-baseline sm:gap-6"
        >
          <span class="caps-title text-ink group-hover:underline">{{ item.name }}</span>
          <span class="text-label text-graphite">{{ item.detail }}</span>
          <span class="text-caption text-muted sm:text-right">{{ item.date }}</span>
        </component>
      </li>
    </ul>
  </section>
</template>
