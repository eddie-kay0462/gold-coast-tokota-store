<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS, SUSTAINABILITY_POSTS } from '~/utils/newsPosts'

type ApiPage = { title: string, body: string }

const config = useRuntimeConfig()

// The About page is CMS-backed (Feature 9): the admin owns the opening
// manifesto, everything else is the designed brand story. A missing or failing
// endpoint falls back to the design's own copy rather than throwing — the same
// pattern the home, shop and blog pages use.
const { data: page } = await useAsyncData('about-page', () =>
  $fetch<{ data: ApiPage }>(`${config.public.apiBase}/pages/about`)
    .catch(() => null),
)

const statementBody = computed(() => page.value?.data?.body ?? null)

/**
 * "The people, stories, and ideas that will help us get there" — the programme
 * strand of the feed, not the whole thing. Moved here from `/sustainability`
 * when the two pages merged.
 */
const PROGRAMME_CATEGORIES = ['Sustainability', 'Careers', 'Partnerships', 'Advocacy', 'Processes', 'Style', 'Community']

const { data: posts } = await useAsyncData('about-programme-posts', () =>
  $fetch<{ data: ApiPost[] }>(`${config.public.apiBase}/blog-posts`)
    .catch(() => ({ data: [] as ApiPost[] })),
)

/** One row of three; the rest live on `/blog?category=Sustainability`. */
const programmePosts = computed(() => {
  const fromApi = posts.value?.data ?? []

  const pool = fromApi.length
    ? [...fromApi].sort((a, b) => b.published_at.localeCompare(a.published_at))
    : [...SUSTAINABILITY_POSTS, ...DESIGN_POSTS]

  const seen = new Set<string>()
  return pool
    .filter((post) => {
      if (seen.has(post.slug)) return false
      if (!PROGRAMME_CATEGORIES.includes(post.category ?? '')) return false
      seen.add(post.slug)
      return true
    })
    .slice(0, 3)
})

useSeoMeta({
  title: 'About — Gold Coast Tokota',
  description:
    'Exceptional quality, ethical factories, radical transparency — the story behind Gold Coast Tokota, and our mission to clean up a dirty industry.',
  ogTitle: 'About — Gold Coast Tokota',
  ogDescription: 'Exceptional quality. Ethical factories. Radical Transparency.',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <!--
    About and Sustainability were two routes telling one story; they merged on
    27 Aug. `/sustainability` is a 301 to this page (see `nuxt.config.ts`), and
    the section nav at the top is now what moves between the parts of it —
    which is what that tab row was always for.
  -->
  <div class="w-full bg-white">
    <AboutSectionNav />

    <AboutHeroSection />

    <MotionGsapScrollReveal>
      <AboutStatementSection :body="statementBody" />
    </MotionGsapScrollReveal>

    <AboutFeatureSection
      id="factories"
      eyebrow="OUR FACTORIES"
      heading="Our ethical approach."
      body="We spend months finding the best factories around the world—the same ones that produce your favorite designer labels. We visit them often and build strong personal relationships with the owners. Each factory is given a compliance audit to evaluate factors like fair wages, reasonable hours, and environment. Our goal? A score of 90 or above for every factory."
      image="/design/about-factories.png"
      alt="An artisan hand-cutting leather at the workbench"
      :height="733"
    />

    <img
      src="/design/about-workshop-wide.png"
      alt="Inside the Gold Coast Tokota workshop, tools and hides laid out at the bench"
      class="aspect-[16/9] max-h-[637px] w-full object-cover"
      loading="lazy"
    >

    <!-- `id="quality"`, not `id="sustainability"` as it used to be: this section
         is about how long a pair lasts, and the anchor now belongs to the real
         sustainability block further down. -->
    <AboutFeatureSection
      id="quality"
      eyebrow="OUR QUALITY"
      :heading="'Designed\nto last.'"
      body="We're not big on trends. We want you to wear our pieces for years, even decades, to come. That's why we source the finest materials and the most skilled hands for our timeless products—like our full-grain leather ahenema, hand-stitched soles, and locally woven kente trims."
      image="/design/about-quality.png"
      alt="A pair of blue and black leather sandals photographed from above"
      :reverse="true"
      :height="552"
    />

    <img
      src="/design/about-materials-wide.png"
      alt="Woven material and finishing supplies arranged across the workshop floor"
      class="aspect-[16/9] max-h-[560px] w-full object-cover"
      loading="lazy"
    >

    <AboutFeatureSection
      id="prices"
      eyebrow="OUR PRICES"
      heading="Radically Transparent."
      body="We believe our customers have a right to know how much their shoes cost to make. We reveal the true costs behind all of our products—from materials to labor to transportation—then offer them to you, minus the traditional retail markup."
      image="/design/about-price-breakdown.png"
      alt="A cost breakdown comparing the Gold Coast Tokota price with traditional retail"
      :tinted="false"
      :contain="true"
      :height="660"
    />

    <!-- Everything below came from `/sustainability`. -->
    <MotionGsapScrollReveal>
      <AboutSustainabilitySection />
    </MotionGsapScrollReveal>

    <AboutSloganTicker />

    <MotionGsapScrollReveal>
      <AboutProgressGrid />
    </MotionGsapScrollReveal>

    <MotionGsapScrollReveal>
      <AboutStoriesGrid :posts="programmePosts" />
    </MotionGsapScrollReveal>

    <MotionGsapScrollReveal>
      <AboutExploreGrid />
    </MotionGsapScrollReveal>

    <AboutSocialCta />
  </div>
</template>
