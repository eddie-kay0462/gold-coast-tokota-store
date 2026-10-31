<script setup lang="ts">
import type { NuxtError } from '#app'
import type { ApiProduct } from '~/utils/catalog'

/**
 * Every error the storefront shows: a wrong link, a product or story that
 * doesn't exist, or the API being unreachable (see utils/apiError.ts). It used
 * to be Nuxt's own screen: a large "404" on a gradient, titled "| Nuxt", with
 * no header, footer or way back into the shop.
 *
 * A 404 offers a few pairs to carry on with, fetched in the browser after the
 * page shows and simply left out if that fails. Any other error offers a
 * retry instead, since asking the API for products is exactly what just failed.
 */
const props = defineProps<{ error: NuxtError }>()

const notFound = computed(() => props.error.statusCode === 404)

const heading = computed(() =>
  notFound.value ? 'We couldn’t find that page' : 'Something went wrong on our side',
)
const lede = computed(() =>
  notFound.value
    ? 'The link may be old, or the page may have moved. Our sandals are all still here.'
    : 'This page didn’t load. Please try again in a moment.',
)

useSeoMeta({
  title: () => `${notFound.value ? 'Page not found' : 'Something went wrong'} | Gold Coast Tokota`,
  robots: 'noindex',
})

const apiFetch = useApiFetch()
const suggestions = ref<ApiProduct[]>([])

onMounted(async () => {
  if (!notFound.value) return
  suggestions.value = await apiFetch<{ data: ApiProduct[] }>('/products', { query: { per_page: 4 } })
    .then((response) => response.data)
    .catch(() => [])
})

function retry() {
  window.location.reload()
}
</script>

<template>
  <NuxtLayout>
    <div class="w-full bg-white">
      <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-6">
        <header class="flex w-full flex-col items-start gap-3">
          <p class="text-caption text-muted">{{ notFound ? 'Error 404' : `Error ${error.statusCode}` }}</p>
          <h1 class="w-full text-display-section font-normal text-black">{{ heading }}</h1>
          <p class="w-full max-w-[720px] text-body text-graphite">{{ lede }}</p>
        </header>

        <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start">
          <CommonBrandButton v-if="!notFound" @click="retry">Try again</CommonBrandButton>
          <CommonBrandButton to="/shop">Shop sandals</CommonBrandButton>
          <CommonBrandButton to="/help">Help centre</CommonBrandButton>
        </div>
      </section>

      <section
        v-if="suggestions.length"
        class="page-gutter mx-auto flex w-full max-w-[1044px] flex-col items-start gap-2 pb-16"
      >
        <h2 class="w-full text-body font-normal text-graphite">You might like</h2>
        <ul class="grid w-full grid-cols-2 gap-x-6 gap-y-8 lg:grid-cols-4">
          <li v-for="item in suggestions" :key="item.slug" class="min-w-0">
            <ShopProductCard :product="item" />
          </li>
        </ul>
      </section>
    </div>
  </NuxtLayout>
</template>
