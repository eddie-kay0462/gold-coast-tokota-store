<script setup lang="ts" generic="T">
import { PhArrowDown, PhArrowUp, PhPlus, PhTrash } from '@phosphor-icons/vue'

/**
 * An editable list: each item in a bordered row with move up, move down and
 * remove, and an "Add" button underneath. The item's own fields come from the
 * default slot. Used by the About page editor for figures, posts, team, …
 */
const props = defineProps<{
  /** "figure", "video"… for the buttons' labels. */
  noun: string
  /** A fresh item for "Add". */
  blank: () => T
  max?: number
  disabled?: boolean
}>()

const items = defineModel<T[]>({ required: true })

function move(index: number, by: -1 | 1) {
  const next = [...items.value]
  const [item] = next.splice(index, 1)
  next.splice(index + by, 0, item as T)
  items.value = next
}

function remove(index: number) {
  items.value = items.value.filter((_, i) => i !== index)
}

function add() {
  items.value = [...items.value, props.blank()]
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div v-for="(item, index) in items" :key="index" class="flex gap-3 rounded-lg border border-border p-3">
      <span class="mt-2 w-5 shrink-0 text-meta text-fg-faint">{{ index + 1 }}</span>
      <div class="min-w-0 flex-1">
        <slot :item="item" :index="index" />
      </div>
      <div v-if="!disabled" class="flex shrink-0 flex-col gap-1">
        <UiButton variant="ghost" size="sm" :disabled="index === 0" :aria-label="`Move ${noun} ${index + 1} up`" @click="move(index, -1)">
          <PhArrowUp :size="14" />
        </UiButton>
        <UiButton variant="ghost" size="sm" :disabled="index === items.length - 1" :aria-label="`Move ${noun} ${index + 1} down`" @click="move(index, 1)">
          <PhArrowDown :size="14" />
        </UiButton>
        <UiButton variant="ghost" size="sm" :aria-label="`Remove ${noun} ${index + 1}`" @click="remove(index)">
          <PhTrash :size="14" />
        </UiButton>
      </div>
    </div>
    <p v-if="!items.length" class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-meta text-fg-faint">
      None yet.
    </p>
    <div v-if="!disabled && (!max || items.length < max)">
      <UiButton variant="secondary" size="sm" @click="add">
        <PhPlus :size="14" />Add {{ noun }}
      </UiButton>
    </div>
  </div>
</template>
