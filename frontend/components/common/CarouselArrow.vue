<script setup lang="ts">
import { PhCaretLeft as CaretLeft, PhCaretRight as CaretRight } from '@phosphor-icons/vue'

withDefaults(
  defineProps<{
    direction: 'left' | 'right'
    disabled?: boolean
    size?: number
  }>(),
  { size: 40 },
)
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    :aria-label="direction === 'left' ? 'Previous' : 'Next'"
    class="flex shrink-0 items-center justify-center text-graphite transition-opacity disabled:opacity-25"
  >
    <component
      :is="direction === 'left' ? CaretLeft : CaretRight"
      :size="size"
      weight="regular"
    />
  </button>
</template>
