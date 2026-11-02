<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

/**
 * A story's picture, wherever one is shown: the cover photo when the story has
 * one, and otherwise a designed tile in the site's own type, instead of the
 * logo on grey every photo-less story used to get (the launch audit's
 * "Stories don't fit the site's look").
 *
 * The tile is quiet on purpose: the light surface the rest of the site uses
 * behind product photos, the category in tracked capitals, the title set in
 * display type, and the small brand mark. Real photos, uploaded from the post
 * editor, still make the better cover.
 */
const props = withDefaults(defineProps<{
  post: ApiPost
  /** Tailwind aspect class for the box, e.g. `aspect-[4/5]`. */
  aspect?: string
  /** `hero` uses the article's own wide image when there is one. */
  variant?: 'card' | 'hero'
  /** Title size inside the tile. */
  size?: 'sm' | 'lg'
}>(), { aspect: 'aspect-[4/5]', variant: 'card', size: 'sm' })

const src = computed(() =>
  props.variant === 'hero' ? props.post.hero_image || props.post.cover_image : props.post.cover_image,
)
</script>

<template>
  <img
    v-if="src"
    :src="src"
    :alt="post.title"
    class="w-full bg-surface object-cover"
    :class="aspect"
    loading="lazy"
  >
  <div
    v-else
    class="relative flex w-full flex-col justify-between overflow-hidden border border-line bg-surface p-5 text-left sm:p-6"
    :class="aspect"
    role="img"
    :aria-label="post.title"
  >
    <p class="caps-label text-muted">{{ post.category || 'Stories' }}</p>
    <p
      class="max-w-[22ch] font-light text-ink"
      :class="size === 'lg' ? 'text-display-section' : 'text-display-sm'"
      aria-hidden="true"
    >
      {{ post.title }}
    </p>
    <img src="/brand/logo-mark.png" alt="" class="size-7 opacity-70" aria-hidden="true">
  </div>
</template>
