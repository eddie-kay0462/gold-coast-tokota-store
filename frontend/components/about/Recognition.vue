<script setup lang="ts">
import { ABOUT_RECOGNITION } from '~/utils/aboutContent'
</script>

<template>
  <!-- Programmes and awards, each linked to the published source. Text rather
       than partner logos: we have no permission to use their marks. -->
  <section id="recognition" class="page-gutter section-y mx-auto flex w-full max-w-[1440px] scroll-mt-8 flex-col gap-8">
    <div class="flex max-w-[640px] flex-col gap-4">
      <p class="caps-label text-graphite">Recognition</p>
      <h2 class="text-display-section font-light text-ink">Backed by people who build businesses.</h2>
    </div>

    <ul class="flex w-full flex-col border-t border-ink">
      <li v-for="item in ABOUT_RECOGNITION" :key="item.name" class="border-b border-line">
        <a
          :href="item.href"
          target="_blank"
          rel="noopener noreferrer"
          class="group grid w-full grid-cols-1 gap-1 py-5 sm:grid-cols-[minmax(0,4fr)_minmax(0,7fr)_5rem] sm:items-baseline sm:gap-6"
        >
          <span class="caps-title text-ink group-hover:underline">{{ item.name }}</span>
          <span class="text-label text-graphite">{{ item.detail }}</span>
          <span class="text-caption text-muted sm:text-right">{{ item.date }}</span>
        </a>
      </li>
    </ul>
  </section>
</template>
