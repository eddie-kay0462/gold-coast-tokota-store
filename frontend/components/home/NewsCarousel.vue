<script setup lang="ts">
type ApiPost = { slug: string, title: string, excerpt?: string, published_at?: string, cover_image?: string }

const props = defineProps<{ posts?: ApiPost[] | null }>()

// Editorial fallback drawn from the Figma design, used until the blog API
// (Feature 9) returns posts.
const designFallback = [
  { slug: 'tyred-of-waste', title: 'Tyred of Waste', excerpt: 'Partnership with Fita Autotech to Tackle Tyre Waste', meta: 'Posted on 21st March 2025', image: '/design/news-tyred.png' },
  { slug: 'sandal-sip-and-paint', title: 'Sandal Sip and Paint Session', excerpt: 'Happening on 1st May 2026', meta: 'Posted on 1st April 2026', image: '/design/news-sip-paint.png' },
  { slug: 'celebrating-au-day', title: 'Celebrating AU Day', excerpt: 'A Memo on The African Union Day', meta: 'Posted on 25th May 2026', image: '/design/news-au-day.png' },
  { slug: 'impact-over-profit', title: 'Impact Over Profit', excerpt: 'Our Bold Step Towards a Greener Future', meta: 'Posted on 27th April 2025', image: '/design/news-impact.png' },
  { slug: 'a-new-partnership', title: 'Gold Coast Tokota x', excerpt: 'A New Partnership', meta: 'Posted', image: '/design/news-partnership.png' },
]

const items = computed(() => {
  const fromApi = props.posts ?? []
  if (!fromApi.length) return designFallback

  return fromApi.map((post, index) => ({
    slug: post.slug,
    title: post.title,
    excerpt: post.excerpt ?? '',
    meta: post.published_at
      ? `Posted on ${new Date(post.published_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}`
      : 'Posted',
    image: post.cover_image || designFallback[index % designFallback.length]!.image,
  }))
})

const { railEl, pageCount, activeIndex, canScrollPrev, canScrollNext, scrollToPage, scrollByPage }
  = useScrollRail()
</script>

<template>
  <section class="flex w-full flex-col items-start gap-[30px] pb-[73px] pt-16 lg:pt-[90px]">
    <div class="flex w-full flex-col items-center gap-3 px-5 text-center text-ink lg:px-[42px]">
      <h2 class="w-full text-display-sm">News &amp; Events</h2>
      <p class="w-full text-body">
        Learn more about our brand, our sustainability journey and upcoming community events
      </p>
    </div>

    <div class="flex w-full items-stretch justify-center gap-3 px-2 lg:px-5">
      <CommonCarouselArrow
        direction="left"
        class="hidden lg:flex"
        :disabled="!canScrollPrev"
        @click="scrollByPage(-1)"
      />

      <ul
        ref="railEl"
        class="flex flex-1 snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        <li
          v-for="item in items"
          :key="item.slug"
          class="flex w-[70%] shrink-0 snap-start flex-col items-center gap-1.5 sm:w-[45%] lg:w-[calc(25%-0.5625rem)]"
        >
          <NuxtLink :to="`/blog/${item.slug}`" class="group flex w-full flex-col gap-1.5">
            <img
              :src="item.image"
              :alt="item.title"
              class="h-[420px] w-full object-cover"
              loading="lazy"
            >
            <div class="flex w-full flex-col items-start gap-[3px]">
              <p class="w-full text-caption text-graphite group-hover:underline">{{ item.title }}</p>
              <p class="w-full text-caption text-muted">{{ item.excerpt }}</p>
              <p class="w-full text-right text-caption text-graphite">{{ item.meta }}</p>
            </div>
          </NuxtLink>
        </li>
      </ul>

      <CommonCarouselArrow
        direction="right"
        class="hidden lg:flex"
        :disabled="!canScrollNext"
        @click="scrollByPage(1)"
      />
    </div>

    <div class="flex w-full justify-center py-5">
      <CommonCarouselDots
        :count="pageCount"
        :active-index="activeIndex"
        label="News and events"
        @select="scrollToPage"
      />
    </div>
  </section>
</template>
