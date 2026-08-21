<script setup lang="ts">
/** A titled settings card with an optional footer action row. */
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <section class="card">
    <div class="border-b border-border p-4 md:p-5">
      <h2 class="card-title">{{ title }}</h2>
      <p v-if="description" class="mt-1 text-ui text-fg-muted">{{ description }}</p>
    </div>
    <div class="p-4 md:p-5"><slot /></div>
    <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-border px-4 py-3 md:px-5">
      <slot name="footer" />
    </div>
  </section>
</template>
