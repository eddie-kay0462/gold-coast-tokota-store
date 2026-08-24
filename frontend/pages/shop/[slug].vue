<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()
const { data: product } = await useAsyncData(`product-${route.params.slug}`, () =>
  $fetch(`${config.public.apiBase}/products/${route.params.slug}`),
)

const productName = computed(() => (product.value as any)?.data?.name)

useSeoMeta({
  title: () => productName.value ?? 'Product — Gold Coast Tokota',
  description: () => (product.value as any)?.data?.description ?? undefined,
})

const { href: whatsappHref } = useWhatsApp(
  () => (productName.value ? `Hi! I'm interested in ${productName.value}.` : undefined),
)
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-12">
    <div v-if="product">
      {{ productName }}
      <p class="mt-4 text-sm">
        <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener noreferrer" class="text-green-600 underline">
          Prefer to order via WhatsApp?
        </a>
      </p>
    </div>
    <p v-else>Loading...</p>
  </div>
</template>
