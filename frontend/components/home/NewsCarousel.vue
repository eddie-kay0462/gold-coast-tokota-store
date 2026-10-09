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

const { railEl, canScrollPrev, canScrollNext, scrollByPage, activeSlide, slideCount, thumb }
  = useScrollRail()
</script>

<template>
  <!-- The same rail as the featured products above — heading, right-bleeding
       row, control bar — so the two read as one system. -->
  <section class="section-y flex w-full flex-col gap-6 lg:gap-8">
    <div class="page-gutter flex w-full flex-col gap-2">
      <HomeSectionHeading title="Stories" to="/blog" link-label="All stories" />
      <p class="max-w-[560px] text-label text-subtle">
        Our brand, our sustainability journey and upcoming community events.
      </p>
    </div>

    <ul
      ref="railEl"
      class="flex w-full snap-x snap-mandatory gap-1.5 overflow-x-auto scroll-smooth pl-5 scroll-pl-5 md:pl-10 md:scroll-pl-10 lg:pl-[60px] lg:scroll-pl-[60px] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      aria-label="Stories"
    >
      <li
        v-for="item in items"
        :key="item.slug"
        class="w-[72%] shrink-0 snap-start sm:w-[40%] lg:w-[23%]"
      >
        <NuxtLink :to="`/blog/${item.slug}`" class="group flex w-full flex-col gap-4">
          <img
            :src="item.image"
            :alt="item.title"
            class="aspect-[4/5] w-full bg-surface object-cover"
            loading="lazy"
          >
          <span class="flex w-full flex-col items-start gap-1 pr-2">
            <span class="text-label font-bold uppercase tracking-[1.4px] text-ink group-hover:underline">{{ item.title }}</span>
            <span v-if="item.excerpt" class="text-label tracking-[0.4px] text-subtle">{{ item.excerpt }}</span>
            <span class="text-caption text-muted">{{ item.meta }}</span>
          </span>
        </NuxtLink>
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
