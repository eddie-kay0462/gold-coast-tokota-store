<script setup lang="ts">
import { PhCaretUp as CaretUp } from '@phosphor-icons/vue'
import {
  COLOR_COLLAPSED_ROWS,
  COLOR_FACETS,
  SIZE_GROUPS,
  TYPE_COLLAPSED_COUNT,
  TYPE_FACETS,
  WIDTH_FACETS,
} from '~/utils/catalog'

const props = defineProps<{
  /** Count shown above the facets — the filtered result count, not the catalogue total. */
  productCount: number
  selected: {
    type: string[]
    color: string[]
    size: string[]
    width: string[]
  }
}>()

const emit = defineEmits<{
  toggle: [facet: 'type' | 'color' | 'size' | 'width', value: string]
}>()

// Section open/closed state. All three groups start expanded, as drawn.
const open = reactive({ type: true, color: true, size: true })

const showAllTypes = ref(false)
const showAllColors = ref(false)

const visibleTypes = computed(() =>
  showAllTypes.value ? TYPE_FACETS : TYPE_FACETS.slice(0, TYPE_COLLAPSED_COUNT),
)

// Colours lay out three per row; "View More +" reveals the rows beyond the third.
const visibleColors = computed(() =>
  showAllColors.value ? COLOR_FACETS : COLOR_FACETS.slice(0, COLOR_COLLAPSED_ROWS * 3),
)

function isSelected(facet: keyof typeof props.selected, value: string) {
  return props.selected[facet].includes(value)
}
</script>

<template>
  <aside class="flex w-full shrink-0 flex-col gap-px md:w-[196px]" aria-label="Product filters">
    <div class="flex w-full items-center justify-center border-b border-line py-4">
      <p class="flex-1 text-caption text-black" aria-live="polite">
        {{ productCount }} {{ productCount === 1 ? 'Product' : 'Products' }}
      </p>
    </div>

    <!-- Category -->
    <button
      type="button"
      class="flex w-full items-center justify-between py-4 text-left"
      :aria-expanded="open.type"
      aria-controls="filter-type"
      @click="open.type = !open.type"
    >
      <span class="text-filter-heading font-normal text-graphite">Category</span>
      <CaretUp :size="12" class="transition-transform" :class="open.type ? '' : 'rotate-180'" />
    </button>

    <div v-show="open.type" id="filter-type" class="flex w-full flex-col gap-1.5">
      <label
        v-for="type in visibleTypes"
        :key="type.value"
        class="flex min-h-[44px] w-full cursor-pointer items-center gap-2"
      >
        <input
          type="checkbox"
          class="size-8 shrink-0 cursor-pointer appearance-none rounded border-[0.5px] border-black bg-white checked:bg-graphite focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-graphite"
          :checked="isSelected('type', type.value)"
          @change="emit('toggle', 'type', type.value)"
        >
        <span class="min-w-0 flex-1 text-caption text-black">{{ type.label }}</span>
      </label>

      <button
        v-if="TYPE_FACETS.length > TYPE_COLLAPSED_COUNT"
        type="button"
        class="flex min-h-[44px] w-full items-center pb-5 pt-1 text-left text-caption text-subtle"
        @click="showAllTypes = !showAllTypes"
      >
        {{ showAllTypes ? 'View Less −' : 'View More +' }}
      </button>
    </div>

    <!-- Color -->
    <button
      type="button"
      class="flex w-full items-center justify-between border-t border-line pb-4 pt-6 text-left"
      :aria-expanded="open.color"
      aria-controls="filter-color"
      @click="open.color = !open.color"
    >
      <span class="text-filter-heading font-normal text-graphite">Color</span>
      <CaretUp :size="12" class="transition-transform" :class="open.color ? '' : 'rotate-180'" />
    </button>

    <div v-show="open.color" id="filter-color" class="flex w-full flex-col gap-4">
      <ul class="grid grid-cols-3 gap-y-4">
        <li v-for="color in visibleColors" :key="color.value" class="flex min-w-0 justify-center">
          <button
            type="button"
            class="flex min-h-[44px] w-full flex-col items-center gap-2 py-1"
            :aria-pressed="isSelected('color', color.value)"
            @click="emit('toggle', 'color', color.value)"
          >
            <span
              class="size-6 rounded-full border border-line"
              :class="isSelected('color', color.value) ? 'ring-1 ring-graphite ring-offset-2' : ''"
              :style="{ backgroundColor: color.hex }"
            />
            <span class="text-center text-caption text-black">{{ color.label }}</span>
          </button>
        </li>
      </ul>

      <button
        v-if="COLOR_FACETS.length > COLOR_COLLAPSED_ROWS * 3"
        type="button"
        class="flex min-h-[44px] w-full items-center pb-5 pt-1 text-left text-caption text-subtle"
        @click="showAllColors = !showAllColors"
      >
        {{ showAllColors ? 'View Less −' : 'View More +' }}
      </button>
    </div>

    <!-- Size + Width -->
    <button
      type="button"
      class="flex w-full items-center justify-between border-t border-line pb-4 pt-6 text-left"
      :aria-expanded="open.size"
      aria-controls="filter-size"
      @click="open.size = !open.size"
    >
      <span class="text-filter-heading font-normal text-graphite">Size</span>
      <CaretUp :size="12" class="transition-transform" :class="open.size ? '' : 'rotate-180'" />
    </button>

    <div v-show="open.size" id="filter-size" class="flex w-full flex-col">
      <fieldset
        v-for="group in SIZE_GROUPS"
        :key="group.label"
        class="flex w-full flex-col gap-2 pb-6"
      >
        <legend class="w-full pb-2 text-caption text-subtle">{{ group.label }}</legend>
        <div class="grid grid-cols-4 gap-1">
          <button
            v-for="size in group.sizes"
            :key="size"
            type="button"
            class="flex min-h-[44px] min-w-0 items-center justify-center p-3 text-caption"
            :class="isSelected('size', size) ? 'bg-graphite text-white' : 'bg-surface text-graphite'"
            :aria-pressed="isSelected('size', size)"
            @click="emit('toggle', 'size', size)"
          >
            {{ size }}
          </button>
        </div>
      </fieldset>

      <fieldset class="flex w-full flex-col gap-2 pb-6">
        <legend class="w-full pb-2 text-caption text-subtle">Width</legend>
        <div class="grid grid-cols-3 gap-1">
          <button
            v-for="width in WIDTH_FACETS"
            :key="width.value"
            type="button"
            class="flex min-h-[44px] min-w-0 items-center justify-center p-3 text-caption"
            :class="isSelected('width', width.value) ? 'bg-graphite text-white' : 'bg-surface text-graphite'"
            :aria-pressed="isSelected('width', width.value)"
            @click="emit('toggle', 'width', width.value)"
          >
            {{ width.label }}
          </button>
        </div>
      </fieldset>
    </div>
  </aside>
</template>
