<script setup lang="ts">
defineProps<{
  /**
   * Rich text from the CMS `about` page (Feature 9). When the admin has
   * written a brand story it replaces the design's copy here — this is the one
   * editorial block on the page the owner is expected to maintain.
   */
  body?: string | null
}>()
</script>

<template>
  <!-- Figma 6:638 -->
  <section class="flex w-full items-center justify-center px-5 py-12 lg:px-[258px] lg:py-[76px]">
    <div
      v-if="body"
      class="prose-statement min-w-0 flex-1 text-center font-light text-black text-body lg:text-display-statement"
      v-html="body"
    />
    <p
      v-else
      class="min-w-0 flex-1 text-center font-light text-black text-body lg:text-display-statement"
    >
      At Gold Coast Tokota, we want the right choice to be as easy as putting on
      a great pair of shoes. That's why we partner with the best, ethical
      factories around the world. Source only the finest materials. And share
      those stories with you—down to the true cost of every product we make.
      It's a new way of doing things. We call it Radical Transparency.
    </p>
  </section>
</template>

<style scoped>
/* The CMS body arrives as HTML; give its paragraphs the same rhythm the
   designed copy has instead of the browser's default margins. */
.prose-statement :deep(p + p) {
  margin-top: 1.25rem;
}
</style>
