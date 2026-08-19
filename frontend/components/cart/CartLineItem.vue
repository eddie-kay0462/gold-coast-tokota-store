<script setup lang="ts">
import { PhMinus, PhPlus, PhTrash } from '@phosphor-icons/vue'
import type { CartItem } from '~/stores/cart'

const props = defineProps<{ item: CartItem }>()

const emit = defineEmits<{
  quantity: [quantity: number]
  remove: []
}>()

const isDiscounted = computed(
  () => !!props.item.compareAtGhs && props.item.compareAtGhs > props.item.unitPriceGhs,
)

const discountLabel = computed(() => {
  if (!isDiscounted.value) return null
  const off = 1 - props.item.unitPriceGhs / props.item.compareAtGhs!
  return `(${Math.round(off * 100)}% Off)`
})
</script>

<template>
  <div class="flex w-full items-start gap-4">
    <NuxtLink :to="`/shop/${item.slug}`" class="shrink-0">
      <img
        :src="item.image || '/design/product-kentehene.png'"
        :alt="item.name"
        class="h-[100px] w-[70px] object-cover"
        loading="lazy"
      >
    </NuxtLink>

    <div class="flex min-w-0 flex-1 flex-col justify-between gap-3 self-stretch">
      <div class="flex w-full items-center gap-3">
        <div class="flex min-w-0 flex-1 flex-col font-light">
          <NuxtLink :to="`/shop/${item.slug}`" class="w-full text-label text-black hover:underline">
            {{ item.name }}
          </NuxtLink>
          <p v-if="item.variantLabel" class="w-full text-caption text-muted">
            {{ item.variantLabel }}
          </p>
        </div>

        <button
          type="button"
          class="shrink-0 p-1 text-graphite transition-opacity hover:opacity-60"
          :aria-label="`Remove ${item.name} from cart`"
          @click="emit('remove')"
        >
          <PhTrash :size="14" />
        </button>
      </div>

      <div class="flex w-full items-center justify-between gap-3">
        <div class="flex min-w-0 flex-1 flex-col text-caption">
          <CommonPriceDisplay
            class="whitespace-nowrap text-graphite"
            :base-price-ghs="item.unitPriceGhs"
            :compare-at-ghs="item.compareAtGhs"
            compact
          />
          <p v-if="discountLabel" class="w-full font-light text-sale">{{ discountLabel }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-[15px] border border-line p-3">
          <button
            type="button"
            class="shrink-0 text-graphite transition-opacity hover:opacity-60"
            :aria-label="item.quantity === 1 ? `Remove ${item.name}` : `Decrease quantity of ${item.name}`"
            @click="emit('quantity', item.quantity - 1)"
          >
            <PhMinus :size="12" />
          </button>

          <span class="min-w-3 text-center text-caption font-light text-black">
            {{ item.quantity }}
          </span>

          <button
            type="button"
            class="shrink-0 text-graphite transition-opacity hover:opacity-60"
            :aria-label="`Increase quantity of ${item.name}`"
            @click="emit('quantity', item.quantity + 1)"
          >
            <PhPlus :size="12" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
