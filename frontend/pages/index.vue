<script setup lang="ts">
type ApiProduct = { slug: string, name: string, base_price_ghs: number, images?: string[] }
type ApiPost = { slug: string, title: string, excerpt?: string, published_at?: string, cover_image?: string }

const config = useRuntimeConfig()

// Both fetches run during SSR so the rendered HTML is crawlable. A failing or
// not-yet-built endpoint resolves to an empty list rather than throwing, so each
// section falls back to the design's own content while Features 2/9 land.
const { data: featuredProducts } = await useAsyncData('home-featured-products', () =>
  $fetch<{ data: ApiProduct[] }>(`${config.public.apiBase}/products?featured=true`)
    .catch(() => ({ data: [] as ApiProduct[] })),
)

const { data: latestPosts } = await useAsyncData('home-latest-posts', () =>
  $fetch<{ data: ApiPost[] }>(`${config.public.apiBase}/posts?limit=5`)
    .catch(() => ({ data: [] as ApiPost[] })),
)

useSeoMeta({
  title: 'Gold Coast Tokota — Handmade Sandals from Ghana',
  description:
    'Your step into heritage. Get your pair of authentic locally-made Ghanaian footwear, handcrafted by Gold Coast Tokota.',
  ogTitle: 'Gold Coast Tokota — Handmade Sandals from Ghana',
  ogDescription: 'Authentic locally-made Ghanaian footwear, handcrafted in Accra.',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div>
    <MotionGsapHeroIntro>
      <HomeHeroSection />
    </MotionGsapHeroIntro>

    <MotionGsapScrollReveal>
      <HomeFeaturedCollection :products="featuredProducts?.data ?? null" />
    </MotionGsapScrollReveal>

    <MotionGsapScrollReveal>
      <HomeSustainabilityBanner />
    </MotionGsapScrollReveal>

    <HomeNewsCarousel :posts="latestPosts?.data ?? null" />

    <MotionGsapScrollReveal>
      <HomeTestimonialCarousel />
    </MotionGsapScrollReveal>

    <MotionGsapScrollReveal>
      <HomeEditorialPair />
    </MotionGsapScrollReveal>

    <HomeUgcGallery />

    <MotionGsapScrollReveal>
      <HomeValueProps />
    </MotionGsapScrollReveal>
  </div>
</template>
