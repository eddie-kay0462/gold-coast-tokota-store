<script setup lang="ts">
import { initials } from '~/utils/formatters'

/**
 * Avatar with a deterministic initials fallback.
 *
 * Fixtures supply data-URI SVGs rather than remote images, so nothing here
 * depends on a network fetch or a third-party avatar service.
 */
const props = withDefaults(defineProps<{
  name: string
  src?: string | null
  size?: number
  /** Presence dot, used in the inbox conversation list. */
  online?: boolean
}>(), { size: 32, src: null, online: false })

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

const px = computed(() => `${props.size}px`)
const showImage = computed(() => !!props.src && !failed.value)
</script>

<template>
  <span class="relative inline-block shrink-0" :style="{ width: px, height: px }">
    <img
      v-if="showImage"
      :src="src!"
      :alt="name"
      class="size-full rounded-pill object-cover"
      @error="failed = true"
    >
    <span
      v-else
      class="flex size-full items-center justify-center rounded-pill bg-bg-inset font-medium text-fg-subtle"
      :style="{ fontSize: `${Math.round(size * 0.38)}px` }"
      :aria-label="name"
    >{{ initials(name) }}</span>

    <span
      v-if="online"
      class="absolute bottom-0 right-0 block rounded-pill bg-success ring-2 ring-bg-elevated"
      :style="{ width: `${Math.max(8, size * 0.28)}px`, height: `${Math.max(8, size * 0.28)}px` }"
      aria-label="Online"
    />
  </span>
</template>
