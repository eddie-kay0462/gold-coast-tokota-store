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
  <article class="flex w-full flex-col items-start">
    <!-- Hero -->
    <header
      class="page-gutter relative flex min-h-[420px] w-full flex-col items-start justify-end overflow-hidden py-12 lg:min-h-[691px] lg:py-[70px]"
    >
      <img
        :src="post.hero_image || post.cover_image || '/design/news-placeholder.png'"
        :alt="post.title"
        class="absolute inset-0 size-full object-cover"
      >
      <!-- Scrim: the design's hero photography is dark, but CMS-uploaded art
           won't always be, and the title must stay legible either way. -->
      <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent" />

      <div class="relative flex w-full max-w-[940px] flex-col items-start justify-end gap-2.5">
        <p
          v-if="post.category"
          class="flex items-center justify-center rounded-[30px] border border-white px-5 py-2 text-center text-caption font-light text-white"
        >
          {{ post.category }}
        </p>
        <div class="flex w-full flex-col items-start gap-[18px] text-white">
          <h1 class="w-full text-display-md font-normal lg:text-article-hero">{{ post.title }}</h1>
          <p v-if="post.subtitle" class="w-full text-body font-light lg:text-display-sm">
            {{ post.subtitle }}
          </p>
        </div>
      </div>
    </header>

    <!-- Lede, with the share rail alongside -->
    <div class="page-gutter flex w-full flex-col gap-10 py-16 lg:py-[115px]">
      <div class="h-3.5 w-full bg-black lg:h-[14px]" />

      <div class="flex w-full flex-col items-start gap-8 lg:flex-row lg:gap-[148px]">
        <div class="flex shrink-0 flex-col gap-2">
          <BlogShare :title="post.title" />
          <time :datetime="post.published_at" class="text-caption text-muted">
            {{ formatPostDate(post.published_at) }}
          </time>
        </div>

        <p v-if="post.lede" class="min-w-0 flex-1 text-display-sm font-normal text-black lg:text-article-lg">
          {{ post.lede }}
        </p>
      </div>
    </div>

    <!-- Body -->
    <template v-for="(block, index) in blocks" :key="index">
      <div
        v-if="block.type === 'image'"
        class="page-gutter flex w-full items-center justify-center overflow-hidden py-12 lg:py-[100px]"
      >
        <img
          :src="block.src"
          :alt="block.alt"
          class="max-h-[1054px] w-full max-w-[1046px] object-cover"
          loading="lazy"
        >
      </div>

      <div v-else class="page-gutter w-full py-12 lg:py-[100px]">
        <div
          class="post-body mx-auto flex w-full max-w-[984px] flex-col items-start gap-8 text-black lg:gap-11"
          v-html="block.html"
        />
      </div>
    </template>

    <p v-if="!blocks.length" class="page-gutter mx-auto w-full max-w-[984px] py-16 text-center text-body text-muted">
      This story hasn’t been published in full yet.
    </p>
  </article>
</template>

<style scoped>
/* The article body arrives as CMS rich text, so its elements are styled here
   rather than with utility classes. Sizes follow the Figma editorial scale. */
.post-body :deep(h2) {
  font-size: 32px;
  line-height: 40px;
  width: 100%;
}

.post-body :deep(h3) {
  font-size: 24px;
  line-height: 33.24px;
  width: 100%;
}

.post-body :deep(p) {
  font-size: 16px;
  line-height: 24px;
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
}

@media (min-width: 1024px) {
  .post-body :deep(h2) {
    font-size: 40px;
    line-height: 48px;
  }

  .post-body :deep(h3),
  .post-body :deep(p) {
    font-size: 24px;
    line-height: 33.24px;
  }
}
</style>
