<script setup lang="ts">
defineProps<{
  /**
   * Rich text from the CMS `about` page (Feature 9): the page's one opening
   * statement, which the owner maintains. The seeded line ("Handmade in Ghana,
   * one pair at a time.") is also the fallback. The founder's story is its own
   * section below (`AboutStory`), so a short statement here can't replace it.
   */
  body?: string | null
}>()
</script>

<template>
  <!-- Figma 6:638 -->
  <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] items-center justify-center">
    <div
      v-if="body"
      class="prose-statement min-w-0 flex-1 text-center font-light text-black text-display-statement"
      v-html="body"
    />
    <p
      v-else
      class="min-w-0 flex-1 text-center font-light text-black text-display-statement"
    >
      Handmade in Ghana, one pair at a time.
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
