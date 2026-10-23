<script setup lang="ts">
import { ABOUT_FIGURES } from '~/utils/aboutContent'
</script>

<template>
  <!-- The figures as the founder gave them to the press, each with its date and
       source, so none reads as a claim made today. Update in utils/aboutContent.ts. -->
  <section id="impact" class="page-gutter section-y mx-auto w-full max-w-[1440px] scroll-mt-8">
    <h2 class="sr-only">Our impact so far</h2>
    <ul class="grid w-full grid-cols-1 border-t border-ink sm:grid-cols-2 lg:grid-cols-4">
      <li
        v-for="figure in ABOUT_FIGURES"
        :key="figure.label"
        class="flex flex-col gap-3 border-b border-line py-8 sm:pr-8 lg:border-b-0 lg:border-r lg:px-8 lg:first:pl-0 lg:last:border-r-0"
      >
        <p class="text-display-heading font-light text-ink">{{ figure.value }}</p>
        <p class="text-label text-graphite">{{ figure.label }}</p>
        <a :href="figure.href" target="_blank" rel="noopener noreferrer" class="text-caption text-muted underline-offset-2 hover:underline">
          {{ figure.source }}
        </a>
      </li>
    </ul>
  </section>
</template>
