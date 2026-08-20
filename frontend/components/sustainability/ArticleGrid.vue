<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

const props = defineProps<{ posts: ApiPost[] }>()

/** Two rows of three, as drawn (10:962). */
const PAGE_SIZE = 6

const shown = ref(PAGE_SIZE)
const visible = computed(() => props.posts.slice(0, shown.value))
const hasMore = computed(() => shown.value < props.posts.length)

function loadMore() {
  shown.value += PAGE_SIZE
}

// A shorter list (or a filter change) must not leave the page stuck on a
// count larger than what's there.
watch(() => props.posts, () => (shown.value = PAGE_SIZE))
</script>

<template>
  <!-- Figma 10:976 -->
  <section class="flex w-full flex-col items-start gap-3 px-5 py-16 lg:px-[60px] lg:py-[120px]">
    <h2 class="w-full font-normal text-display-md text-black lg:text-display-heading">The Latest</h2>

    <div class="flex w-full flex-col items-center gap-10">
      <ul class="grid w-full grid-cols-1 gap-x-6 gap-y-16 sm:grid-cols-2 lg:grid-cols-3 lg:gap-y-[120px]">
        <li v-for="post in visible" :key="post.slug" class="min-w-0">
          <BlogFeatureCard :post="post" />
        </li>
      </ul>

      <CommonBrandButton v-if="hasMore" shape="soft" @click="loadMore">
        Load More Articles
      </CommonBrandButton>
    </div>
  </section>
</template>
