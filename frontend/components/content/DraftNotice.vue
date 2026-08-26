<script setup lang="ts">
import { PhWarningCircle } from '@phosphor-icons/vue'

/**
 * Shown above any policy or help page whose copy has not been approved.
 *
 * This is the most important sentence on an unreviewed page, so it is never
 * `aria-hidden`, never visually muted into a footnote, and never conditional on
 * anything but `usePageContent`'s `isDraft`. Do not remove it to make a page
 * look finished — the page is not finished.
 */
</script>

<template>
  <div
    role="note"
    class="flex w-full min-w-0 items-start gap-3 border border-line bg-surface px-5 py-4"
  >
    <PhWarningCircle :size="20" class="mt-px shrink-0 text-sale" aria-hidden="true" />
    <p class="min-w-0 flex-1 text-caption text-graphite">
      <span class="font-normal text-black">Draft — awaiting review.</span>
      This is placeholder copy written to give the page its shape. It has not been
      reviewed or approved by Gold Coast Tokota and is not a statement of policy.
      The approved version replaces it as soon as it is published in the CMS.
    </p>
  </div>
</template>
