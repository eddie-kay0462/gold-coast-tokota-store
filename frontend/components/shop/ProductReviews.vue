<script setup lang="ts">
import { PhCaretDown, PhCheckCircle, PhList, PhStar } from '@phosphor-icons/vue'
import { MAX_RATING } from '~/utils/catalog'
import type { ProductRating, ProductReview } from '~/utils/catalog'

const props = defineProps<{
  rating: ProductRating
  reviews: ProductReview[]
}>()

type SortKey = 'rating-desc' | 'rating-asc' | 'newest'

const SORTS: { value: SortKey, label: string }[] = [
  { value: 'rating-desc', label: 'Highest to Lowest Rating' },
  { value: 'rating-asc', label: 'Lowest to Highest Rating' },
  { value: 'newest', label: 'Most Recent' },
]

const sort = ref<SortKey>('rating-desc')
/** Star value to filter on, or null for all reviews. */
const filterStars = ref<number | null>(null)

const sortLabel = computed(() => SORTS.find((entry) => entry.value === sort.value)!.label)

const visibleReviews = computed(() => {
  const filtered = filterStars.value
    ? props.reviews.filter((review) => Math.round(review.rating) === filterStars.value)
    : [...props.reviews]

  return filtered.sort((a, b) => {
    if (sort.value === 'newest') return b.created_at.localeCompare(a.created_at)
    return sort.value === 'rating-asc' ? a.rating - b.rating : b.rating - a.rating
  })
})

/** Bars are drawn relative to the most-used star, so one bar always fills. */
const distribution = computed(() => {
  const counts = props.rating.distribution ?? {}
  const max = Math.max(1, ...Object.values(counts))
  return Array.from({ length: MAX_RATING }, (_, index) => {
    const stars = MAX_RATING - index
    const count = counts[String(stars)] ?? 0
    return { stars, count, percent: (count / max) * 100 }
  })
})

/** Fit meter: five segments, the one matching the skew filled. 3 = true to size. */
const fitSegments = computed(() => {
  const fit = props.rating.fit
  return Array.from({ length: 5 }, (_, index) => fit != null && Math.round(fit) === index + 1)
})

const fitLabel = computed(() => {
  const fit = props.rating.fit
  if (fit == null) return null
  if (fit <= 1.5) return 'Runs small'
  if (fit < 2.5) return 'Runs slightly small'
  if (fit <= 3.5) return 'True to size'
  if (fit < 4.5) return 'Runs slightly large'
  return 'Runs large'
})

/** "14 days ago" — the design's relative stamp. */
function relativeAge(iso: string) {
  const days = Math.round((Date.now() - new Date(iso).getTime()) / 86_400_000)
  if (days < 1) return 'Today'
  if (days === 1) return 'Yesterday'
  if (days < 30) return `${days} days ago`
  const months = Math.round(days / 30)
  if (months < 12) return `${months} ${months === 1 ? 'month' : 'months'} ago`
  const years = Math.round(months / 12)
  return `${years} ${years === 1 ? 'year' : 'years'} ago`
}
</script>

<template>
  <section class="flex w-full flex-col gap-10 px-5 lg:px-[196px]">
    <h2 class="w-full text-center text-display-sm font-normal text-graphite">Reviews</h2>

    <!-- Summary -->
    <div class="flex w-full flex-col gap-8 bg-surface px-6 pb-16 pt-9 lg:flex-row lg:gap-[55px] lg:px-14 lg:pb-[84px]">
      <div class="flex min-w-0 flex-1 flex-col gap-[15px]">
        <p class="w-full text-body font-normal text-graphite">
          {{ rating.average.toFixed(1) }} Overall Rating
        </p>
        <CommonStarRating :value="rating.average" :size="22" />
      </div>

      <div class="flex min-w-0 flex-1 flex-col gap-2">
        <button
          v-for="row in distribution"
          :key="row.stars"
          type="button"
          class="flex w-full items-center gap-1 text-left"
          :aria-pressed="filterStars === row.stars"
          :aria-label="`Show only ${row.stars}-star reviews (${row.count})`"
          @click="filterStars = filterStars === row.stars ? null : row.stars"
        >
          <span class="text-caption text-muted">{{ row.stars }}</span>
          <PhStar :size="18" weight="fill" class="shrink-0 text-graphite" />
          <span class="h-1.5 min-w-0 flex-1 bg-line">
            <span class="block h-full bg-graphite" :style="{ width: `${row.percent}%` }" />
          </span>
          <span class="text-caption text-muted">{{ row.count }}</span>
        </button>
      </div>

      <div v-if="fitLabel" class="flex min-w-0 flex-1 flex-col">
        <p class="w-full text-body font-normal text-graphite">{{ fitLabel }}</p>
        <div class="flex w-full items-center gap-1 pb-2 pt-4" role="img" :aria-label="fitLabel">
          <span
            v-for="(active, index) in fitSegments"
            :key="index"
            class="h-2 min-w-0 flex-1"
            :class="active ? 'bg-graphite' : 'bg-line'"
          />
        </div>
        <div class="flex w-full items-center justify-between text-caption font-light text-graphite">
          <span>Run small</span>
          <span class="text-right">Run large</span>
        </div>
      </div>
    </div>

    <!-- Controls -->
    <div class="flex w-full flex-col items-stretch justify-between gap-4 sm:flex-row sm:items-center">
      <button
        type="button"
        class="flex items-center gap-2.5 border border-line p-4 sm:w-[240px]"
        :aria-pressed="filterStars !== null"
        @click="filterStars = null"
      >
        <span class="min-w-0 flex-1 text-left text-display-sm font-normal text-black">
          {{ filterStars ? `${filterStars} Star` : 'Filter' }}
        </span>
        <PhList :size="24" class="shrink-0" />
      </button>

      <label class="relative flex items-center gap-2.5 border border-line p-4 sm:w-[240px]">
        <span class="flex min-w-0 flex-1 flex-col justify-center">
          <span class="w-full text-display-sm font-normal text-black">Sort by:</span>
          <span class="w-full text-caption font-light text-muted">{{ sortLabel }}</span>
        </span>
        <PhCaretDown :size="24" class="shrink-0" />
        <select v-model="sort" class="absolute inset-0 cursor-pointer opacity-0" aria-label="Sort reviews">
          <option v-for="entry in SORTS" :key="entry.value" :value="entry.value">
            {{ entry.label }}
          </option>
        </select>
      </label>
    </div>

    <!-- List -->
    <ol v-if="visibleReviews.length" class="flex w-full flex-col gap-px">
      <li
        v-for="(review, index) in visibleReviews"
        :key="review.id"
        class="flex w-full flex-col gap-6 pb-[57px] lg:flex-row lg:gap-2.5"
        :class="index < visibleReviews.length - 1 ? 'border-b border-line' : ''"
      >
        <div class="flex w-full flex-col lg:w-[230px] lg:shrink-0">
          <p class="w-full text-body font-normal text-black">{{ review.author }}</p>
          <p v-if="review.verified" class="flex w-full items-center gap-1 pb-5 pt-2">
            <PhCheckCircle :size="18" class="shrink-0" />
            <span class="min-w-0 flex-1 text-caption font-light text-graphite">Verified</span>
          </p>
          <p
            v-for="attribute in review.attributes"
            :key="attribute.label"
            class="flex w-full items-center gap-1 text-caption text-graphite"
          >
            <span class="shrink-0 whitespace-nowrap font-normal">{{ attribute.label }}:</span>
            <span class="min-w-0 flex-1 font-light">{{ attribute.value }}</span>
          </p>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-3">
          <CommonStarRating :value="review.rating" :size="20" />
          <p class="w-full text-body font-normal text-black">{{ review.title }}</p>
          <p class="w-full text-label font-light text-black">{{ review.body }}</p>
        </div>

        <p class="shrink-0 whitespace-nowrap text-caption font-light text-muted">
          {{ relativeAge(review.created_at) }}
        </p>
      </li>
    </ol>

    <p v-else class="py-8 text-body text-muted">No reviews match this filter yet.</p>
  </section>
</template>
