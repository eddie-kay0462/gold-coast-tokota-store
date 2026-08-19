<script setup lang="ts">
import { PhStar } from '@phosphor-icons/vue'
import { MAX_RATING } from '~/utils/catalog'

const props = withDefaults(
  defineProps<{
    /** Score out of 5. */
    value: number
    /** Star size in px — the design uses 12, 18, 20 and 22. */
    size?: number
    /** Omit the accessible label when an adjacent element already states it. */
    labelled?: boolean
  }>(),
  { size: 12, labelled: true },
)

const filled = computed(() => Math.round(props.value))
</script>

<template>
  <div
    class="flex shrink-0 items-center gap-1"
    :role="labelled ? 'img' : undefined"
    :aria-label="labelled ? `${value} out of ${MAX_RATING} stars` : undefined"
    :aria-hidden="labelled ? undefined : 'true'"
  >
    <PhStar
      v-for="star in MAX_RATING"
      :key="star"
      :size="size"
      :weight="star <= filled ? 'fill' : 'regular'"
      class="text-graphite"
    />
  </div>
</template>
