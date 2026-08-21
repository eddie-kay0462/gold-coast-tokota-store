<script setup lang="ts">
import { PhFunnel, PhMagnifyingGlass, PhSortAscending, PhX } from '@phosphor-icons/vue'

/**
 * The list toolbar from the Products frame: search, the sort/filter icon
 * cluster, then a slot for page actions (Import, Add Product).
 */
const search = defineModel<string>('search', { default: '' })
defineProps<{ activeFilterCount?: number; placeholder?: string }>()
const emit = defineEmits<{ (e: 'clear-filters'): void }>()

const filtersOpen = defineModel<boolean>('filtersOpen', { default: false })
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <div class="relative order-first w-full min-w-0 sm:order-none sm:w-auto sm:flex-1 sm:max-w-xs">
      <PhMagnifyingGlass :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-fg-faint" />
      <input
        v-model="search" type="search" :placeholder="placeholder ?? 'Search…'"
        class="field min-h-[40px] pl-9" :class="search && 'pr-9'"
      >
      <button
        v-if="search" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 toolbar-btn size-8"
        aria-label="Clear search" @click="search = ''"
      >
        <PhX :size="14" />
      </button>
    </div>

    <button
      type="button" class="toolbar-btn relative" :class="filtersOpen && 'bg-bg-sunken text-fg-strong'"
      aria-label="Filters" :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen"
    >
      <PhFunnel :size="18" />
      <span
        v-if="activeFilterCount"
        class="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-pill
               bg-accent text-micro font-medium text-accent-fg"
      >{{ activeFilterCount }}</span>
    </button>

    <button v-if="activeFilterCount" type="button" class="text-meta text-accent-text hover:underline" @click="emit('clear-filters')">
      Clear
    </button>

    <div class="ml-auto flex min-w-0 flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
