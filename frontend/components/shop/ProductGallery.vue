<script setup lang="ts">
const props = defineProps<{
  images: string[]
  name: string
  /** Sale badge pinned to the first frame, as in the design. */
  discountLabel?: string | null
}>()

// The design lays out a 2-up grid of 508px frames. A product with an odd
// number of photos simply ends on a single frame rather than padding the grid
// with repeats.
const frames = computed(() => props.images.filter(Boolean))
</script>

<template>
  <div class="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
    <div
      v-for="(image, index) in frames"
      :key="image + index"
      class="relative h-[360px] w-full overflow-hidden lg:h-[508px]"
      :class="frames.length === 1 ? 'sm:col-span-2' : ''"
    >
      <img
        :src="image"
        :alt="index === 0 ? name : `${name} — view ${index + 1}`"
        class="size-full object-cover"
        :loading="index === 0 ? 'eager' : 'lazy'"
      >
      <span
        v-if="index === 0 && discountLabel"
        class="absolute left-2 top-2 bg-white px-1.5 py-1 text-center text-caption text-sale"
      >
        {{ discountLabel }}
      </span>
    </div>
  </div>
</template>
