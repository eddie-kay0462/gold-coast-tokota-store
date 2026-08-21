<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'

const props = defineProps<{ products: ApiProduct[] }>()
const emit = defineEmits<{ add: [product: ApiProduct] }>()

// One card at a time with dot pagination, as drawn. Cards are cheap, so all of
// them stay mounted and the active one is shown rather than re-rendering.
const active = ref(0)

// Adding the visible product removes it from the list, which can leave the
// index past the end.
watch(
  () => props.products.length,
  (length) => {
    if (active.value >= length) active.value = Math.max(0, length - 1)
  },
)

const current = computed(() => props.products[active.value])

/** Size range across the product's variants, e.g. "38-45". */
function sizeRange(product: ApiProduct) {
  const sizes = product.sizes ?? []
  if (!sizes.length) return null
  return sizes.length === 1 ? sizes[0] : `${sizes[0]}-${sizes[sizes.length - 1]}`
}

const subtitle = computed(() => {
  if (!current.value) return null
  return [sizeRange(current.value), current.value.color].filter(Boolean).join(' | ')
})
</script>

<template>
  <div v-if="current" class="flex w-full flex-col gap-2">
    <h3 class="w-full text-filter-heading font-normal text-black">Before You Go</h3>

    <div class="flex w-full items-start gap-4 border border-line p-2.5">
      <NuxtLink :to="`/shop/${current.slug}`" class="shrink-0">
        <img
          :src="current.images?.[0]"
          :alt="current.name"
          class="h-[100px] w-[70px] object-cover"
          loading="lazy"
        >
      </NuxtLink>

      <div class="flex min-w-0 flex-1 flex-col justify-between gap-3 self-stretch">
        <div class="flex w-full flex-col font-light">
          <NuxtLink :to="`/shop/${current.slug}`" class="w-full text-label text-black hover:underline">
            {{ current.name }}
          </NuxtLink>
          <p v-if="subtitle" class="w-full text-caption text-muted">{{ subtitle }}</p>
        </div>

        <div class="flex w-full items-end justify-between gap-3">
          <CommonPriceDisplay
            class="text-caption text-graphite"
            :base-price-ghs="current.base_price_ghs"
            compact
          />
          <button
            type="button"
            class="w-[81px] shrink-0 bg-graphite py-3 text-center text-label uppercase text-white transition-opacity hover:opacity-80"
            @click="emit('add', current)"
          >
            Add
          </button>
        </div>
      </div>
    </div>

    <!-- These dots are the only way to change card on a touch device, so the
         button is 44px even though the dot it draws stays 7px. -->
    <div v-if="products.length > 1" class="-my-4 flex w-full items-center">
      <button
        v-for="(product, index) in products"
        :key="product.slug"
        type="button"
        class="flex size-11 shrink-0 items-center justify-center"
        :aria-label="`Show recommendation ${index + 1} of ${products.length}`"
        :aria-current="index === active"
        @click="active = index"
      >
        <span
          class="size-[7px] rounded-full transition-colors"
          :class="index === active ? 'bg-graphite' : 'bg-line'"
        />
      </button>
    </div>
  </div>
</template>
