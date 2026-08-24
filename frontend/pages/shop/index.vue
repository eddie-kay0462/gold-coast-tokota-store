<script setup lang="ts">
const config = useRuntimeConfig()

const page = ref(1)
const selectedCategory = ref<string | null>(null)

const { data: categoriesData } = await useAsyncData('shop-categories', () =>
  $fetch(`${config.public.apiBase}/categories`),
)
const categories = computed(() => (categoriesData.value as any)?.data ?? [])

const { data: products } = await useAsyncData(
  'shop-products',
  () =>
    $fetch(`${config.public.apiBase}/products`, {
      query: { page: page.value, category_id: selectedCategory.value ?? undefined },
    }),
  { watch: [page, selectedCategory] },
)
const meta = computed(() => (products.value as any)?.meta)

function selectCategory(categoryId: string | null) {
  selectedCategory.value = categoryId
  page.value = 1
}

useSeoMeta({
  title: 'Shop — Gold Coast Tokota',
  description: 'Browse our full catalogue of handmade sandals and accessories.',
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-12">
    <h1 class="text-2xl font-semibold">Shop</h1>
    <ShopProductFilter :categories="categories" class="mt-4" @select="selectCategory" />
    <ShopProductGrid :products="(products as any)?.data ?? []" class="mt-6" />
    <div v-if="meta && meta.last_page > 1" class="mt-8 flex items-center justify-center gap-4 text-sm">
      <button type="button" :disabled="page <= 1" class="disabled:opacity-40" @click="page--">Previous</button>
      <span class="text-gray-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button type="button" :disabled="page >= meta.last_page" class="disabled:opacity-40" @click="page++">Next</button>
    </div>
  </div>
</template>
