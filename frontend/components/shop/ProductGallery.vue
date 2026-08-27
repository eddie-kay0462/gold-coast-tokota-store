<script setup lang="ts">
const props = defineProps<{
  images: string[]
  name: string
  /** Sale badge pinned to the main frame, as in the design. */
  discountLabel?: string | null
  /** Stock badge from the approved mockup — bottom-left of the main frame. */
  stockBadge?: { label: string, class: string } | null
}>()

/**
 * The thumbnail rail from the approved Template B mockup: a column of 4:5
 * thumbnails beside one large 4:5 frame, the active one outlined.
 *
 * It replaces the earlier flat 2-up grid of every photo. That grid put the
 * whole set on screen at once, which sounds generous but shrank each shot to
 * roughly half width on a product page that is itself half the viewport — and
 * the catalogue's photography is detail work, stitching and crowns.
 *
 * Frames are sized by aspect ratio, never a pixel height: with a fluid width,
 * a fixed height re-crops the photo at every viewport.
 */
const frames = computed(() => props.images.filter(Boolean))

const activeIndex = ref(0)

/**
 * Hovering the main image previews the next shot and releases back, exactly as
 * the mockup does. Hover only — it is a flourish, and everything it reveals is
 * reachable from the rail below by click and by keyboard.
 */
const hovering = ref(false)

const previewIndex = computed(() => {
  if (!hovering.value || frames.value.length < 2) return activeIndex.value
  return (activeIndex.value + 1) % frames.value.length
})

const mainImage = computed(() => frames.value[previewIndex.value] ?? frames.value[0] ?? '')

// A product whose photo set changes (navigating between products reuses this
// component) must not keep an index past the end of the new set.
watch(frames, () => (activeIndex.value = 0))
</script>

<template>
  <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row-reverse sm:items-start">
    <!-- Main frame -->
    <div
      class="relative w-full overflow-hidden bg-surface"
      :class="frames.length > 1 ? 'aspect-[4/5] sm:min-w-0 sm:flex-1' : 'aspect-[4/5]'"
      @mouseenter="hovering = true"
      @mouseleave="hovering = false"
    >
      <img
        v-if="mainImage"
        :key="mainImage"
        :src="mainImage"
        :alt="previewIndex === 0 ? name : `${name} — view ${previewIndex + 1}`"
        class="size-full object-cover"
        loading="eager"
      >

      <span
        v-if="discountLabel"
        class="absolute left-2 top-2 bg-white px-1.5 py-1 text-center text-caption text-sale"
      >
        {{ discountLabel }}
      </span>

      <span
        v-if="stockBadge"
        class="absolute bottom-3 left-3 px-2.5 py-1.5 text-tag uppercase"
        :class="stockBadge.class"
      >
        {{ stockBadge.label }}
      </span>
    </div>

    <!-- Thumbnail rail. A row under the image on a phone, a column beside it
         from `sm` — a vertical rail on a 375px screen would either shrink the
         main frame or run off the bottom. -->
    <ul
      v-if="frames.length > 1"
      class="flex shrink-0 gap-2 overflow-x-auto sm:w-[78px] sm:flex-col sm:overflow-visible"
    >
      <li v-for="(image, index) in frames" :key="image + index" class="w-[64px] shrink-0 sm:w-full">
        <button
          type="button"
          class="relative block aspect-[4/5] w-full overflow-hidden bg-surface outline-offset-[-1px]"
          :class="index === activeIndex ? 'outline outline-2 outline-graphite' : 'outline outline-1 outline-line'"
          :aria-label="`Show view ${index + 1} of ${frames.length}`"
          :aria-current="index === activeIndex"
          @click="activeIndex = index"
        >
          <img :src="image" alt="" class="size-full object-cover" loading="lazy">
        </button>
      </li>
    </ul>
  </div>
</template>
