<script setup lang="ts">
import type { ApiProduct } from '~/utils/catalog'

const props = defineProps<{ products: ApiProduct[] }>()
const emit = defineEmits<{ add: [product: ApiProduct] }>()

// One card at a time, paged with the home page's rail controls. Cards are cheap, so all of
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

/** Pager state for `CommonRailControls`: one card per page, so the thumb is one nth of the bar. */
const count = computed(() => props.products.length)
const thumb = computed(() => ({
  left: count.value ? active.value / count.value : 0,
  width: count.value ? 1 / count.value : 1,
}))

const subtitle = computed(() => {
  if (!current.value) return null
  return [sizeRange(current.value), current.value.color].filter(Boolean).join(' | ')
})
</script>

<template>
  <div v-if="current" class="flex w-full flex-col gap-4 border-t border-line pt-6">
    <h3 class="caps-label w-full text-ink">Before you go</h3>

    <div class="flex w-full items-start gap-4">
      <NuxtLink :to="`/shop/${current.slug}`" class="shrink-0">
        <img
          :src="current.images?.[0]"
          :alt="current.name"
          class="aspect-[3/4] w-[75px] bg-surface object-cover"
          loading="lazy"
        >
      </NuxtLink>

      <div class="flex min-w-0 flex-1 flex-col justify-between gap-3 self-stretch">
        <div class="flex w-full flex-col gap-1">
          <NuxtLink :to="`/shop/${current.slug}`" class="caps-title w-full hover:underline">
            {{ current.name }}
          </NuxtLink>
          <p v-if="subtitle" class="w-full text-caption text-subtle">{{ subtitle }}</p>
        </div>

        <div class="flex w-full items-end justify-between gap-3">
          <CommonPriceDisplay
            class="text-label text-ink"
            :base-price-ghs="current.base_price_ghs"
            compact
          />
          <button
            type="button"
            class="btn-outline min-h-[44px] w-[81px] shrink-0 text-center text-label uppercase"
            @click="emit('add', current)"
          >
            Add
          </button>
        </div>
      </div>
    </div>

    <!-- The home page's rail controls in place of dots: arrows, a progress
         bar and an n/N count. One card shows at a time, so the arrows step
         the index rather than scrolling a rail. -->
    <CommonRailControls
      v-if="count > 1"
      :can-prev="active > 0"
      :can-next="active < count - 1"
      :thumb="thumb"
      :current="active"
      :total="count"
      label="recommendation"
      @prev="active = Math.max(0, active - 1)"
      @next="active = Math.min(count - 1, active + 1)"
    />
  </div>
</template>
