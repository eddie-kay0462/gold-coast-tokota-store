<script setup lang="ts">
// Figma draws the active dot as a filled circle and the rest as outlines.
defineProps<{ count: number; activeIndex: number; label?: string }>()
const emit = defineEmits<{ select: [index: number] }>()
</script>

<template>
  <div class="flex items-start justify-center gap-3" role="tablist" :aria-label="label || 'Slides'">
    <button
      v-for="index in count"
      :key="index"
      type="button"
      role="tab"
      :aria-selected="index - 1 === activeIndex"
      :aria-label="`Go to slide ${index}`"
      class="size-[7px] rounded-full border border-graphite transition-colors"
      :class="index - 1 === activeIndex ? 'bg-graphite' : 'bg-transparent'"
      @click="emit('select', index - 1)"
    />
  </div>
</template>
