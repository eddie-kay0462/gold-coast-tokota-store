<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { formatPostDate } from '~/utils/newsPosts'

const props = defineProps<{ post: ApiPost }>()

/**
 * The CMS will return a single rich-text `body`; the design lays an article out
 * as alternating prose and full-bleed images. Structured `blocks` are used when
 * present, and a plain `body` is wrapped as one block so both shapes render.
 */
const blocks = computed(() => {
  if (props.post.blocks?.length) return props.post.blocks
  if (props.post.body) return [{ type: 'html' as const, html: props.post.body }]
  return []
})
</script>

<template>
  <!-- Simplified to the approved Template B article layout: one narrow centred
       column, a quiet meta line, the cover image, then the body.

       What it replaces: a 691px full-bleed hero with a gradient scrim and the
       title reversed out over the photo, then a 14px solid rule, then a
       two-column band with a floating share rail 148px away from the lede. Four
       different arrangements before a reader reached the first paragraph. -->
  <article class="page-gutter mx-auto flex w-full max-w-[calc(52rem+120px)] flex-col items-center py-10 lg:py-16">
    <header class="flex w-full flex-col items-center gap-4 text-center">
      <p class="caps-label text-muted">
        <time :datetime="post.published_at">{{ formatPostDate(post.published_at) }}</time>
        <template v-if="post.category">
          &nbsp;&middot;&nbsp;{{ post.category }}
        </template>
      </p>

      <h1 class="w-full text-display-section font-normal text-ink">{{ post.title }}</h1>

      <p v-if="post.subtitle" class="w-full max-w-[640px] text-lede font-light text-muted">
        {{ post.subtitle }}
      </p>

      <p v-if="post.author" class="text-label text-graphite">By {{ post.author }}</p>

      <BlogShare :title="post.title" />
    </header>

    <!-- The cover, when the story has a photo. Without one the article goes
         straight to its words: a stand-in picture at this size read as a
         missing image, not a design. -->
    <div v-if="post.hero_image || post.cover_image" class="mt-10 w-full overflow-hidden bg-surface">
      <BlogCover :post="post" variant="hero" aspect="aspect-[16/9]" />
    </div>
    <div v-else class="mt-10 w-full max-w-[680px] border-t border-line" aria-hidden="true" />

    <p v-if="post.lede" class="mt-10 w-full max-w-[680px] text-article-lg font-normal text-black">
      {{ post.lede }}
    </p>

    <!-- Body -->
    <div class="mt-10 flex w-full flex-col items-center gap-10">
      <template v-for="(block, index) in blocks" :key="index">
        <img
          v-if="block.type === 'image'"
          :src="block.src"
          :alt="block.alt"
          class="w-full object-cover"
          loading="lazy"
        >
        <div
          v-else
          class="post-body flex w-full max-w-[680px] flex-col items-start gap-6 text-black"
          v-html="block.html"
        />
      </template>
    </div>

    <p v-if="!blocks.length" class="w-full py-10 text-center text-body text-muted">
      This story hasn’t been published in full yet.
    </p>
  </article>
</template>

<style scoped>
/* The article body arrives as CMS rich text, so its elements are styled here
   rather than with utility classes. Sizes follow the Figma editorial scale and
   use the same clamp() form as the display tokens in `tailwind.config.ts`;
   each reaches its exact Figma pixel value at the 1440px frame and scales
   smoothly below it, replacing the hard @media step this block used to carry. */
.post-body :deep(h2) {
  /* 40px at 1440 */
  font-size: clamp(24px, 19.429px + 1.429vw, 40px);
  line-height: 1.2;
  width: 100%;
}

.post-body :deep(h3) {
  /* 24px at 1440 */
  font-size: clamp(20px, 18.857px + 0.357vw, 24px);
  line-height: 1.385;
  width: 100%;
}

.post-body :deep(p) {
  /* Body copy grows from 16px to 20px at 1440. It used to reach the editorial
     24px, which was set for a 984px measure; this column is 680px, and 24px
     across 680px runs to about 45 characters a line, too short to read
     comfortably. */
  font-size: clamp(16px, 14.857px + 0.357vw, 20px);
  line-height: 1.6;
  font-weight: 300;
  letter-spacing: 0.64px;
  width: 100%;
}

.post-body :deep(a) {
  text-decoration: underline;
}

.post-body :deep(a:hover) {
  text-decoration: none;
}

.post-body :deep(ul),
.post-body :deep(ol) {
  padding-left: 1.5rem;
  list-style: revert;
  font-size: clamp(16px, 14.857px + 0.357vw, 20px);
  line-height: 1.6;
  font-weight: 300;
  letter-spacing: 0.64px;
  width: 100%;
}

.post-body :deep(li + li) {
  margin-top: 0.5em;
}

/* Pulled quotes, as the editor makes them: a rule on the left, the site's
   display type, no italics. */
.post-body :deep(blockquote) {
  border-left: 1px solid currentColor;
  padding-left: 1.25rem;
  font-size: clamp(20px, 18.857px + 0.357vw, 24px);
  line-height: 1.4;
  font-weight: 300;
  width: 100%;
}

/* Images dropped into the text: the column's width, no rounding. */
.post-body :deep(img) {
  width: 100%;
  height: auto;
}
</style>
