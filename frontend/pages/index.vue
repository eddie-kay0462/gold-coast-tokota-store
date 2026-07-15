<script setup lang="ts">
const config = useRuntimeConfig()
const { data: featuredProducts } = await useAsyncData('home-featured-products', () =>
  $fetch(`${config.public.apiBase}/products?featured=true`),
)

useSeoMeta({
  title: 'Gold Coast Tokota — Handmade Sandals from Ghana',
  description: 'Discover handmade sandals and accessories from Gold Coast Tokota, crafted in Ghana.',
})
</script>

<template>
  <div>
    <MotionGsapScrollReveal>
      <section class="mx-auto max-w-6xl px-4 py-16">
        <h1 class="text-4xl font-semibold">Gold Coast Tokota</h1>
        <p class="mt-4 text-gray-600">Handmade sandals, crafted with heritage.</p>
        <div class="mt-6 flex gap-4">
          <NuxtLink to="/shop" class="rounded bg-black px-4 py-2 text-white">Shop</NuxtLink>
          <NuxtLink to="/booking" class="rounded border px-4 py-2">Book a Workshop</NuxtLink>
        </div>
      </section>
    </MotionGsapScrollReveal>

    <section class="mx-auto max-w-6xl px-4 py-12">
      <h2 class="text-2xl font-semibold">Featured</h2>
      <ShopProductGrid :products="(featuredProducts as any)?.data ?? []" class="mt-4" />
    </section>
  </div>
</template>
