<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'
import { DESIGN_POSTS, SUSTAINABILITY_POSTS } from '~/utils/newsPosts'
import { aboutImage, withAboutDefaults, type AboutContent } from '~/utils/aboutContent'

type ApiPage = { title: string, body: string, content: Partial<AboutContent> | null }

const config = useRuntimeConfig()

// Every section is edited in the admin (Content › Pages › About) and arrives
// as `content`, already merged over its defaults by the API. If the endpoint
// fails, the same defaults (utils/aboutContent.ts) render the page, the same
// pattern the home, shop and blog pages use.
const { data: page } = await useAsyncData('about-page', () =>
  $fetch<{ data: ApiPage }>(`${config.public.apiBase}/pages/about`)
    .catch(() => null),
)

const content = computed(() => withAboutDefaults(page.value?.data?.content))

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
    Redesigned 23 Oct from the brand's own material (sources in
    AboutPage::defaults()), and editable section by section in the admin
    since the same day: copy, lists, images, and a switch to hide any section.
    `/sustainability` still 301s to `#sustainability` (the materials section).
  -->
  <div class="w-full bg-white">
    <AboutSectionNav />

    <AboutHeroSection v-if="content.hero.visible" :content="content.hero" />

    <MotionGsapScrollReveal v-if="content.statement.visible">
      <AboutStatementSection :text="content.statement.text" />
    </MotionGsapScrollReveal>

    <AboutStory v-if="content.story.visible" :content="content.story" />

    <AboutStoryNumbers v-if="content.figures.visible && content.figures.items.length" :content="content.figures" />

    <AboutMaterials v-if="content.materials.visible" :content="content.materials" />

    <AboutPeople v-if="content.people.visible" :content="content.people" />

    <AboutFeatureSection
      v-if="content.ahenema.visible"
      id="ahenema"
      :eyebrow="content.ahenema.eyebrow"
      :heading="content.ahenema.heading"
      :body="content.ahenema.body"
      :image="aboutImage(content.ahenema)"
      :alt="content.ahenema.imageAlt"
      :reverse="true"
      :height="620"
    />

    <AboutExperiences v-if="content.experiences.visible" :content="content.experiences" />

    <AboutSocialCarousel
      v-if="content.watch.visible && content.watch.posts.length"
      :content="content.watch"
      :socials="content.socials"
    />

    <AboutSloganTicker v-if="content.slogans.visible && content.slogans.items.length" :items="content.slogans.items" />

    <AboutRecognition v-if="content.recognition.visible && content.recognition.items.length" :content="content.recognition" />

    <MotionGsapScrollReveal v-if="content.mission.visible">
      <AboutSustainabilitySection :content="content.mission" />
    </MotionGsapScrollReveal>

    <MotionGsapScrollReveal>
      <AboutStoriesGrid :posts="programmePosts" />
    </MotionGsapScrollReveal>

    <AboutSocialCta :instagram="content.socials.instagram" />
  </div>
</template>
