<script setup lang="ts">
// Figma draws the active dot as a filled circle and the rest as outlines.
defineProps<{ count: number; activeIndex: number; label?: string }>()
const emit = defineEmits<{ select: [index: number] }>()
</script>

<template>
  <!-- The dot stays 7px as drawn; the button around it is 44px so it can
       actually be tapped. `-my-*` keeps the row's visual height unchanged. -->
  <div class="-my-4 flex items-center justify-center" role="tablist" :aria-label="label || 'Slides'">
    <button
      v-for="index in count"
      :key="index"
      type="button"
      role="tab"
      :aria-selected="index - 1 === activeIndex"
      :aria-label="`Go to slide ${index}`"
      class="flex size-11 items-center justify-center"
      @click="emit('select', index - 1)"
    >
      <span
        class="size-[7px] rounded-full border border-graphite transition-colors"
        :class="index - 1 === activeIndex ? 'bg-graphite' : 'bg-transparent'"
      />
    </button>
  </div>
</template>
