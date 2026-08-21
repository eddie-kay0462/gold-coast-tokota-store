<script setup lang="ts">
const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

const router = useRouter()
const query = ref('')
const input = ref<HTMLInputElement | null>(null)

/**
 * Popular Categories as drawn (Figma 6:552). Each tile resolves to a filtered
 * shop listing rather than a bespoke landing page, so the tiles keep working as
 * the catalogue grows.
 */
const categories = [
  { label: 'Slip-On Sandals', image: '/design/search-slip-on.png', to: '/shop?type=slippers' },
  { label: 'Closed-Toe Shoes', image: '/design/search-closed-toe.png', to: '/shop?type=closed-toe' },
  { label: 'Women’s Sandals', image: '/design/search-womens.png', to: '/shop?category=womens' },
  {
    label: 'Men’s Best Sellers',
    image: '/design/search-mens-best.png',
    to: '/shop?category=mens&sort=best-selling',
  },
]

function close() {
  query.value = ''
  emit('close')
}

function submit() {
  const term = query.value.trim()
  if (!term) return
  router.push(`/shop?q=${encodeURIComponent(term)}`)
  close()
}

// Focus the field as soon as the panel opens — a search box you have to click
// into defeats the point of the shortcut.
watch(
  () => props.open,
  async (open) => {
    if (!open) return
    await nextTick()
    input.value?.focus()
  },
)
</script>

<template>
  <Transition
    enter-active-class="motion-safe:transition-opacity motion-safe:duration-150"
    leave-active-class="motion-safe:transition-opacity motion-safe:duration-150"
    enter-from-class="opacity-0"
    leave-to-class="opacity-0"
  >
    <div v-if="open" class="w-full bg-white" @keydown.esc="close">
      <!-- Search field -->
      <form
        class="flex w-full items-start border-t border-line px-5 py-6 md:px-10 lg:px-[120px] xl:px-[326px]"
        role="search"
        @submit.prevent="submit"
      >
        <input
          ref="input"
          v-model="query"
          type="search"
          name="q"
          placeholder="Search"
          aria-label="Search products"
          class="min-w-0 flex-1 rounded bg-surface p-4 text-caption text-graphite outline-none placeholder:text-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-graphite"
        >
        <button
          type="button"
          class="shrink-0 rounded p-4 text-caption text-muted transition-opacity hover:opacity-60"
          @click="close"
        >
          Cancel
        </button>
      </form>

      <!-- Popular categories -->
      <div class="flex w-full flex-col items-start gap-4 border-t border-line px-5 py-8 md:px-10 lg:px-[60px] xl:px-[156px]">
        <h2 class="w-full text-label font-light text-subtle">Popular Categories</h2>

        <ul class="grid w-full grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
          <li v-for="category in categories" :key="category.label" class="min-w-0">
            <NuxtLink :to="category.to" class="group flex w-full flex-col gap-2.5" @click="close">
              <img
                :src="category.image"
                :alt="category.label"
                class="aspect-[3/4] w-full object-cover"
                loading="lazy"
              >
              <span class="w-full text-body font-light text-subtle underline group-hover:no-underline">
                {{ category.label }}
              </span>
            </NuxtLink>
          </li>
        </ul>
      </div>
    </div>
  </Transition>
</template>
