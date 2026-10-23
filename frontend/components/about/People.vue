<script setup lang="ts">
import { aboutImage, type AboutContent } from '~/utils/aboutContent'

defineProps<{ content: AboutContent['people'] }>()
</script>

<template>
  <!-- The people: a photo, the text, and named roles (Content › Pages ›
       About, people). -->
  <section id="people" class="page-gutter section-y mx-auto grid w-full max-w-[1440px] scroll-mt-8 grid-cols-1 items-center gap-10 md:grid-cols-2 lg:gap-16">
    <figure class="flex w-full max-w-[640px] flex-col gap-3 md:justify-self-end">
      <img
        :src="aboutImage(content)"
        :alt="content.imageAlt"
        class="aspect-[4/5] w-full bg-surface object-cover object-top"
        loading="lazy"
      >
      <figcaption v-if="content.caption" class="text-caption text-muted">{{ content.caption }}</figcaption>
    </figure>
    <div class="flex flex-col items-start gap-4">
      <p v-if="content.eyebrow" class="caps-label text-graphite">{{ content.eyebrow }}</p>
      <h2 class="text-display-section font-light text-ink">{{ content.heading }}</h2>
      <p class="max-w-[520px] whitespace-pre-line text-body font-light text-graphite">{{ content.body }}</p>
      <dl v-if="content.team.length" class="mt-2 grid w-full max-w-[520px] grid-cols-1 gap-4 border-t border-line pt-5 sm:grid-cols-2">
        <div v-for="person in content.team" :key="person.name + person.role">
          <dt class="text-caption text-muted">{{ person.role }}</dt>
          <dd class="text-label text-ink">{{ person.name }}</dd>
        </div>
      </dl>
    </div>
  </section>
</template>
