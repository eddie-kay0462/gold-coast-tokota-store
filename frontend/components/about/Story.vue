<script setup lang="ts">
import type { AboutContent } from '~/utils/aboutContent'

defineProps<{ content: AboutContent['story'] }>()
</script>

<template>
  <!-- The founding story and a quote (Content › Pages › About, story). The
       default copy's sources are in AboutPage::defaults(). -->
  <section id="story" class="page-gutter section-y mx-auto grid w-full max-w-[1440px] scroll-mt-8 grid-cols-1 gap-10 border-t border-line lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-16">
    <div class="flex flex-col items-start gap-4">
      <p v-if="content.eyebrow" class="caps-label text-graphite">{{ content.eyebrow }}</p>
      <h2 class="text-display-section font-light text-ink">{{ content.heading }}</h2>
    </div>

    <div class="flex flex-col gap-8">
      <div class="flex max-w-[640px] flex-col gap-5 text-body font-light text-graphite">
        <p v-for="(paragraph, index) in content.paragraphs" :key="index" class="whitespace-pre-line">{{ paragraph }}</p>
      </div>

      <figure v-if="content.quote" class="flex max-w-[640px] flex-col gap-3 border-l-2 border-gold pl-6">
        <blockquote class="text-display-sm font-light text-ink">“{{ content.quote }}”</blockquote>
        <figcaption v-if="content.quoteBy || content.quoteSource" class="text-caption text-muted">
          {{ content.quoteBy }}<template v-if="content.quoteBy && content.quoteSource">, on </template>
          <a
            v-if="content.quoteSource && content.quoteUrl"
            :href="content.quoteUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="underline underline-offset-2 hover:no-underline"
          >{{ content.quoteSource }}</a>
          <template v-else>{{ content.quoteSource }}</template>
        </figcaption>
      </figure>
    </div>
  </section>
</template>
