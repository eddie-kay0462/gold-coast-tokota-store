<script setup lang="ts">
import { PhPlus, PhX } from '@phosphor-icons/vue'
import type { VariantColour } from '~/types'
import { CATALOGUE_COLOURS, NAMED_COLOURS, hexFor, nameFor } from '~/utils/colours'

/**
 * A product's sizes and colourways. Every colour comes in every size, and
 * each pair is a stock row (set to zero when it's new; counts go in
 * Inventory). With no colours, it's one row per size.
 *
 * Sizes can be added one at a time or as a run ("36 to 45").
 *
 * Colours know their names (`utils/colours.ts`): typing "Tan" sets the
 * catalogue's tan swatch, and picking a swatch for an unnamed colour names it
 * after the nearest one. A name typed by hand is never overwritten.
 */
const sizes = defineModel<string[]>('sizes', { required: true })
const colours = defineModel<VariantColour[]>('colours', { required: true })
defineProps<{ disabled?: boolean }>()

const sizeInput = ref('')
const sizeError = ref<string | null>(null)

/** "40", "40, 41", or a run: "36 to 45" / "36-45". */
function addSizes() {
  sizeError.value = null
  const raw = sizeInput.value.trim()
  if (!raw) return
  const run = raw.match(/^(\d+)\s*(?:to|-|–)\s*(\d+)$/i)
  let next: string[]
  if (run) {
    const [from, to] = [Number(run[1]), Number(run[2])]
    if (to < from || to - from > 29) {
      sizeError.value = 'A run goes up, and holds 30 sizes at most.'
      return
    }
    next = Array.from({ length: to - from + 1 }, (_, i) => String(from + i))
  } else {
    next = raw.split(',').map((s) => s.trim()).filter(Boolean)
  }
  const tooLong = next.find((s) => s.length > 10)
  if (tooLong) {
    sizeError.value = `“${tooLong}” is longer than a size label can be.`
    return
  }
  const have = new Set(sizes.value.map((s) => s.toLowerCase()))
  sizes.value = [...sizes.value, ...next.filter((s) => !have.has(s.toLowerCase()))]
  sizeInput.value = ''
}

function removeSize(size: string) {
  sizes.value = sizes.value.filter((s) => s !== size)
}

function addColour() {
  colours.value = [...colours.value, { name: '', hex: '#000000' }]
}

const has = (name: string) => colours.value.some((c) => c.name.trim().toLowerCase() === name.toLowerCase())
const quickPicks = computed(() => CATALOGUE_COLOURS.filter((c) => !has(c.name)))
function addNamed(c: { name: string; hex: string }) {
  if (!has(c.name)) colours.value = [...colours.value, { name: c.name, hex: c.hex }]
}

// Names the picker filled in itself, which a later swatch change may replace.
const autoNamed = new WeakSet<VariantColour>()
function onNameInput(c: VariantColour) {
  autoNamed.delete(c)
  const hex = hexFor(c.name)
  if (hex) c.hex = hex
}
function onSwatchInput(c: VariantColour) {
  if (c.name.trim() && !autoNamed.has(c)) return
  const name = nameFor(c.hex)
  if (name && (!has(name) || c.name.trim().toLowerCase() === name.toLowerCase())) {
    c.name = name
    autoNamed.add(c)
  }
}
function removeColour(i: number) {
  colours.value = colours.value.filter((_, j) => j !== i)
}

const rows = computed(() => sizes.value.length * Math.max(1, colours.value.filter((c) => c.name.trim()).length))
</script>

<template>
  <div class="flex flex-col gap-5">
    <div>
      <p class="field-label">Sizes</p>
      <ul v-if="sizes.length" class="mb-2 flex flex-wrap gap-1.5">
        <li
          v-for="s in sizes" :key="s"
          class="flex items-center gap-1 rounded-pill border border-border bg-bg-elevated py-1 pl-3 pr-1 text-ui text-fg-strong"
        >
          {{ s }}
          <button
            v-if="!disabled" type="button" class="toolbar-btn !size-6" :aria-label="`Remove size ${s}`"
            @click="removeSize(s)"
          >
            <PhX :size="12" />
          </button>
        </li>
      </ul>
      <p v-else class="mb-2 text-meta text-fg-faint">No sizes yet. Without one, the product can’t be stocked or sold.</p>
      <!-- Not a <form>: the editor sits inside the Add product form. -->
      <div v-if="!disabled" class="flex gap-2">
        <input
          v-model="sizeInput" class="field w-56" aria-label="Add sizes"
          placeholder="40, or a run: 36 to 45" @keydown.enter.prevent="addSizes"
        >
        <UiButton variant="secondary" size="sm" @click="addSizes"><PhPlus :size="14" />Add</UiButton>
      </div>
      <p v-if="sizeError" class="mt-1 text-meta text-danger" role="alert">{{ sizeError }}</p>
    </div>

    <div>
      <p class="field-label">Colours</p>
      <p class="mb-2 text-meta text-fg-faint">
        Leave empty for a product that comes in one colour. Each colour gets its own photos and
        swatch on the storefront.
      </p>
      <ul class="flex flex-col gap-2">
        <li v-for="(c, i) in colours" :key="i" class="flex items-center gap-2">
          <input
            v-model="c.hex" type="color" class="h-10 w-12 shrink-0 cursor-pointer rounded border border-border bg-transparent"
            :aria-label="`Swatch for ${c.name || `colour ${i + 1}`}`" :disabled="disabled"
            @input="onSwatchInput(c)"
          >
          <input
            v-model="c.name" class="field min-w-0 flex-1" maxlength="40" placeholder="e.g. Tan"
            list="variant-colour-names" :aria-label="`Colour ${i + 1} name`" :disabled="disabled"
            @input="onNameInput(c)"
          >
          <button
            v-if="!disabled" type="button" class="toolbar-btn shrink-0" :aria-label="`Remove ${c.name || `colour ${i + 1}`}`"
            @click="removeColour(i)"
          >
            <PhX :size="16" />
          </button>
        </li>
      </ul>
      <datalist id="variant-colour-names">
        <option v-for="n in NAMED_COLOURS" :key="n.name" :value="n.name" />
      </datalist>
      <template v-if="!disabled && colours.length < 12">
        <div v-if="quickPicks.length" class="mt-3 flex flex-wrap items-center gap-1.5">
          <span class="mr-1 text-meta text-fg-faint">Quick add:</span>
          <button
            v-for="q in quickPicks" :key="q.name" type="button"
            class="flex items-center gap-1.5 rounded-pill border border-border bg-bg-elevated py-1 pl-1.5 pr-2.5 text-meta text-fg hover:border-border-strong"
            @click="addNamed(q)"
          >
            <span class="size-3.5 rounded-full border border-border" :style="{ background: q.hex }" />
            {{ q.name }}
          </button>
        </div>
        <UiButton variant="secondary" size="sm" class="mt-2" @click="addColour">
          <PhPlus :size="14" />Another colour
        </UiButton>
      </template>
    </div>

    <p class="text-meta text-fg-muted">
      {{ rows }} stock {{ rows === 1 ? 'row' : 'rows' }}: every colour in every size.
    </p>
  </div>
</template>
