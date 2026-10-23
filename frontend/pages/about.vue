<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS, SUSTAINABILITY_POSTS } from '~/utils/newsPosts'

type ApiPage = { title: string, body: string }

const config = useRuntimeConfig()

// The About page is CMS-backed (Feature 9): the admin owns the opening
// manifesto, everything else is the designed brand story. A missing or failing
// endpoint falls back to the design's own copy rather than throwing, the same
// pattern the home, shop and blog pages use.
const { data: page } = await useAsyncData('about-page', () =>
  $fetch<{ data: ApiPage }>(`${config.public.apiBase}/pages/about`)
    .catch(() => null),
)

const statementBody = computed(() => page.value?.data?.body ?? null)

/**
 * "The people, stories, and ideas that will help us get there", the programme
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
  const unique = pool.filter((post) => !seen.has(post.slug) && seen.add(post.slug))
  const programme = unique.filter((post) => PROGRAMME_CATEGORIES.includes(post.category ?? ''))
  // The newest posts of any kind when none are in the programme categories,
  // rather than a heading over an empty row.
  return (programme.length ? programme : unique).slice(0, 3)
})

useSeoMeta({
  title: 'About | Gold Coast Tokota',
  description:
    'Gold Coast Tokota turns discarded tyres, water sachets and denim into handcrafted ahenema and sandals in Accra, and opens its workshop to schools, companies and visitors.',
  ogTitle: 'About | Gold Coast Tokota',
  ogDescription: 'Crafted with purpose. Inspired by culture. Handmade in Ghana from materials other people throw away.',
  ogImage: '/design/post-tyred-hero.png',
  ogType: 'website',
})
</script>

<template>
  <!--
    Redesigned 23 Oct from the brand's own material: GOLD_COAST_TOKOTA.md, its
    Instagram and YouTube, and press (sources in utils/aboutContent.ts). The
    page it replaced was largely template copy (ethical factories "around the
    world", compliance-audit scores, radically transparent pricing) over stock
    photography; none of that was this brand.

    Order: who we are, what we make it from, who makes it, the ahenema itself,
    come and see, watch, who backs us, where we're going, the latest.
    `/sustainability` still 301s to `#sustainability` (the materials section).
  -->
  <div class="w-full bg-white">
    <AboutSectionNav />

    <AboutHeroSection />

    <MotionGsapScrollReveal>
      <AboutStatementSection :body="statementBody" />
    </MotionGsapScrollReveal>

    <AboutStory />

    <AboutStoryNumbers />

    <AboutMaterials />

    <AboutPeople />

    <AboutFeatureSection
      id="ahenema"
      eyebrow="THE AHENEMA"
      :heading="'Fit for royalty,\nmade for every day.'"
      body="Ahenema have been worn by Ghanaian royalty for generations. We keep the shape, the crowns and the Adinkra symbols, and build them from reclaimed materials, leather and velvet. Now we’re making Orthopaedic Ahenema too, so the royal sandal can be worn all day."
      image="/design/about-ahenema-kentehene.webp"
      alt="The Kentehene ahenema: a blue suede footbed with a woven kente strap and leather crown"
      :reverse="true"
      :height="620"
    />

    <AboutExperiences />

    <AboutSocialCarousel />

    <AboutSloganTicker />

    <AboutRecognition />

    <MotionGsapScrollReveal>
      <AboutSustainabilitySection />
    </MotionGsapScrollReveal>

    <MotionGsapScrollReveal>
      <AboutStoriesGrid :posts="programmePosts" />
    </MotionGsapScrollReveal>

    <AboutSocialCta />
  </div>
</template>
