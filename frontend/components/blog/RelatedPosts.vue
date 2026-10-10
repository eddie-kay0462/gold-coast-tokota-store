<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ posts: ApiPost[] }>()

const { railEl, canScrollPrev, canScrollNext, scrollByPage, activeSlide, slideCount, thumb }
  = useScrollRail()
</script>

<template>
  <!-- The home page's Stories rail: heading with an "All stories" link, a row
       bleeding off the right edge, and the arrow/progress bar beneath. Owns its
       own section chrome so it drops straight into a page. -->
  <section v-if="posts.length" class="section-y flex w-full flex-col gap-6 lg:gap-8">
    <div class="page-gutter w-full">
      <HomeSectionHeading title="Related stories" to="/blog" link-label="All stories" />
    </div>

    <ul
      ref="railEl"
      class="flex w-full snap-x snap-mandatory gap-1.5 overflow-x-auto scroll-smooth pl-5 scroll-pl-5 md:pl-10 md:scroll-pl-10 lg:pl-[60px] lg:scroll-pl-[60px] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      aria-label="Related stories"
    >
      <li
        v-for="post in posts"
        :key="post.slug"
        class="w-[72%] shrink-0 snap-start sm:w-[40%] lg:w-[23%]"
      >
        <BlogCard :post="post" />
      </li>
    </ul>

    <div class="page-gutter mx-auto w-full max-w-[1440px]">
      <CommonRailControls
        :can-prev="canScrollPrev"
        :can-next="canScrollNext"
        :thumb="thumb"
        :current="activeSlide"
        :total="slideCount"
        label="stories"
        @prev="scrollByPage(-1)"
        @next="scrollByPage(1)"
      />
    </div>
  </section>
</template>
