<script setup lang="ts">
import { PhArrowLeft, PhArrowRight, PhInstagramLogo, PhPlay, PhX, PhYoutubeLogo } from '@phosphor-icons/vue'
import { ABOUT_POSTS, BRAND_SOCIALS, type SocialPost } from '~/utils/aboutContent'

/**
 * "From the workshop": the brand's Instagram reels and YouTube videos, and
 * press and TV features about it, in one swipeable row.
 *
 * Nothing third-party loads until someone asks for it. A card is a still (the
 * video's own YouTube thumbnail, or for Instagram, whose images expire, a
 * black tile with the post's subject); tapping it opens the real player in a
 * lightbox at the post's own shape: 9:16 for Shorts and reels, 16:9 for
 * interviews. YouTube plays from youtube-nocookie.com.
 */
const track = ref<HTMLElement | null>(null)
const playing = ref<SocialPost | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
let opener: HTMLElement | null = null

useBodyScrollLock(computed(() => playing.value !== null))

function thumb(post: SocialPost) {
  return `https://i.ytimg.com/vi/${post.id}/${post.thumb ?? 'hqdefault'}.jpg`
}

function playerSrc(post: SocialPost) {
  return post.platform === 'youtube'
    ? `https://www.youtube-nocookie.com/embed/${post.id}?autoplay=1&playsinline=1&rel=0`
    : `https://www.instagram.com/reel/${post.id}/embed/`
}

function sourceUrl(post: SocialPost) {
  return post.platform === 'youtube'
    ? `https://www.youtube.com/watch?v=${post.id}`
    : `https://www.instagram.com/reel/${post.id}/`
}

async function open(post: SocialPost, event: Event) {
  opener = event.currentTarget as HTMLElement
  playing.value = post
  await nextTick()
  closeButton.value?.focus()
}

function close() {
  playing.value = null
  opener?.focus()
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && playing.value) close()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

/** One card's width per press, so the arrows step through the row. */
function scrollBy(direction: 1 | -1) {
  const el = track.value
  if (!el) return
  const card = el.querySelector('li')
  const step = card ? card.getBoundingClientRect().width + 12 : el.clientWidth * 0.8
  el.scrollBy({ left: direction * step, behavior: 'smooth' })
}
</script>

<template>
  <section id="watch" class="w-full scroll-mt-8 overflow-hidden py-12 md:py-16 lg:py-[90px]">
    <div class="page-gutter mx-auto flex w-full max-w-[1440px] flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="flex max-w-[640px] flex-col gap-4">
        <p class="caps-label text-graphite">From the workshop</p>
        <h2 class="text-display-section font-light text-ink">See the work for yourself.</h2>
        <p class="text-body font-light text-graphite">
          Pairs being made, classrooms on a visit, Sip &amp; Paint afternoons, and the founder in
          conversation, from our Instagram and YouTube.
        </p>
      </div>
      <div class="hidden shrink-0 gap-2 sm:flex">
        <button type="button" class="btn-outline flex size-11 items-center justify-center" aria-label="Previous posts" @click="scrollBy(-1)">
          <PhArrowLeft :size="18" />
        </button>
        <button type="button" class="btn-outline flex size-11 items-center justify-center" aria-label="Next posts" @click="scrollBy(1)">
          <PhArrowRight :size="18" />
        </button>
      </div>
    </div>

    <!-- The row starts at the page gutter and runs off the right edge, so it
         reads as more to swipe to. Snap keeps each card squarely in view. -->
    <ul
      ref="track"
      class="carousel-track mt-8 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2"
      aria-label="Instagram and YouTube posts"
    >
      <li v-for="post in ABOUT_POSTS" :key="post.id" class="w-[68vw] max-w-[300px] shrink-0 snap-start sm:w-[300px]">
        <button
          type="button"
          class="group flex w-full flex-col gap-3 text-left"
          :aria-label="`Play: ${post.title}, ${post.meta}`"
          @click="open(post, $event)"
        >
          <span class="relative block aspect-[4/5] w-full overflow-hidden bg-ink">
            <img
              v-if="post.platform === 'youtube'"
              :src="thumb(post)"
              alt=""
              class="size-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-[1.03]"
              loading="lazy"
              referrerpolicy="no-referrer"
            >
            <span v-else class="flex size-full flex-col justify-end gap-3 p-5 text-white">
              <PhInstagramLogo :size="28" weight="light" aria-hidden="true" />
              <span class="text-display-sm font-light leading-snug">{{ post.title }}</span>
            </span>

            <span class="absolute left-3 top-3 flex items-center gap-1.5 bg-ink px-2 py-1 text-tag uppercase text-white">
              <component :is="post.platform === 'youtube' ? PhYoutubeLogo : PhInstagramLogo" :size="12" weight="fill" aria-hidden="true" />
              {{ post.platform === 'youtube' ? 'YouTube' : 'Instagram' }}
            </span>
            <span class="absolute bottom-3 right-3 flex size-11 items-center justify-center bg-white text-ink transition-colors group-hover:bg-ink group-hover:text-white" aria-hidden="true">
              <PhPlay :size="18" weight="fill" />
            </span>
          </span>
          <span class="flex flex-col gap-1">
            <span class="caps-title text-ink group-hover:underline">{{ post.title }}</span>
            <span class="text-caption text-muted">{{ post.meta }}</span>
          </span>
        </button>
      </li>
      <!-- Keeps the last card clear of the screen edge when scrolled to the end. -->
      <li aria-hidden="true" class="w-px shrink-0" />
    </ul>

    <div class="page-gutter mx-auto mt-6 flex w-full max-w-[1440px] flex-wrap gap-x-6 gap-y-2">
      <a :href="BRAND_SOCIALS.instagram" target="_blank" rel="noopener noreferrer" class="caps-label -my-3 flex min-h-[44px] items-center gap-2 py-3 text-ink underline underline-offset-4 hover:no-underline">
        <PhInstagramLogo :size="16" aria-hidden="true" />@goldcoasttokota on Instagram
      </a>
      <a :href="BRAND_SOCIALS.youtube" target="_blank" rel="noopener noreferrer" class="caps-label -my-3 flex min-h-[44px] items-center gap-2 py-3 text-ink underline underline-offset-4 hover:no-underline">
        <PhYoutubeLogo :size="16" aria-hidden="true" />Gold Coast Tokota on YouTube
      </a>
    </div>

    <!-- The player. Black, full screen, the post at its own shape. -->
    <Teleport to="body">
      <div
        v-if="playing"
        class="fixed inset-0 z-[80] flex flex-col items-center justify-center gap-4 bg-black/95 p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="playing.title"
        @click.self="close"
      >
        <button
          ref="closeButton"
          type="button"
          class="absolute right-3 top-3 flex size-11 items-center justify-center text-white hover:text-white/70"
          aria-label="Close video"
          @click="close"
        >
          <PhX :size="26" />
        </button>

        <!-- Sized to the post: Instagram's embed in a phone-width column, a Short
             at 9:16 (its height capped to the screen, width following), an
             interview at 16:9. -->
        <div
          class="bg-black"
          :class="playing.platform === 'instagram'
            ? 'h-[min(760px,calc(100dvh-140px))] w-full max-w-[420px]'
            : playing.shape === 'portrait'
              ? 'aspect-[9/16] h-[min(760px,calc(100dvh-140px))] max-w-full'
              : 'aspect-video w-full max-w-[min(1100px,calc((100dvh-140px)*16/9))]'"
        >
          <iframe
            :key="playing.id"
            :src="playerSrc(playing)"
            :title="playing.title"
            class="size-full border-0"
            allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
            allowfullscreen
          />
        </div>

        <p class="flex max-w-[640px] flex-wrap items-center justify-center gap-x-3 text-center text-caption text-white">
          <span>{{ playing.title }} · {{ playing.meta }}</span>
          <a :href="sourceUrl(playing)" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2 hover:no-underline">
            Open on {{ playing.platform === 'youtube' ? 'YouTube' : 'Instagram' }}
          </a>
        </p>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
/* The row lines up with the page gutter and snaps cards to it. */
.carousel-track {
  padding-inline: 20px;
  scroll-padding-inline: 20px;
  scrollbar-width: none;
}
.carousel-track::-webkit-scrollbar {
  display: none;
}
@media (min-width: 768px) {
  .carousel-track { padding-inline: 40px; scroll-padding-inline: 40px; }
}
@media (min-width: 1024px) {
  .carousel-track { padding-inline: 60px; scroll-padding-inline: 60px; }
}
@media (min-width: 1440px) {
  .carousel-track {
    padding-inline: calc((100vw - 1440px) / 2 + 60px);
    scroll-padding-inline: calc((100vw - 1440px) / 2 + 60px);
  }
}
</style>
