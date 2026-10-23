<script setup lang="ts">
import type { AboutContent } from '~/utils/aboutContent'

defineProps<{ content: AboutContent['figures'] }>()
</script>

<template>
  <!-- The figures row, each with its source and date (Content › Pages ›
       About, figures). -->
  <section id="impact" class="page-gutter section-y mx-auto w-full max-w-[1440px] scroll-mt-8">
    <h2 class="sr-only">Our impact so far</h2>
    <ul class="grid w-full grid-cols-1 border-t border-ink sm:grid-cols-2 lg:grid-cols-4">
      <li
        v-for="figure in content.items"
        :key="figure.value + figure.label"
        class="flex flex-col gap-3 border-b border-line py-8 sm:pr-8 lg:border-b-0 lg:border-r lg:px-8 lg:first:pl-0 lg:last:border-r-0"
      >
        <p class="text-display-heading font-light text-ink">{{ figure.value }}</p>
        <p class="text-label text-graphite">{{ figure.label }}</p>
        <a v-if="figure.source && figure.href" :href="figure.href" target="_blank" rel="noopener noreferrer" class="text-caption text-muted underline-offset-2 hover:underline">
          {{ figure.source }}
        </a>
        <p v-else-if="figure.source" class="text-caption text-muted">{{ figure.source }}</p>
      </li>
    </ul>
  </section>
</template>
