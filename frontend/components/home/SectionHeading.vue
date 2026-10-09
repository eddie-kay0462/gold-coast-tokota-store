<script setup lang="ts">
/**
 * The one heading treatment for home-page rails: title on the left, an
 * optional "see all" link on the right on the same baseline. The link wears the
 * header's uppercase, tracked label style so the page and its chrome read as
 * one system.
 */
defineProps<{
  title: string
  to?: string
  linkLabel?: string
}>()
</script>

<template>
  <div class="flex w-full flex-col items-start gap-2 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
    <h2 class="text-display-sm text-ink">{{ title }}</h2>
    <NuxtLink
      v-if="to && linkLabel"
      :to="to"
      class="-my-3 flex min-h-[44px] shrink-0 items-center py-3 text-caption uppercase tracking-[1.4px] text-ink underline underline-offset-4 hover:no-underline"
    >
      {{ linkLabel }}
    </NuxtLink>
  </div>
</template>
