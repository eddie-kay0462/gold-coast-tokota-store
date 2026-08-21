<script setup lang="ts">
import { PhCaretDown } from '@phosphor-icons/vue'

defineProps<{ label?: string; options: { value: string; label: string }[] }>()
const model = defineModel<string>({ default: '' })
const id = useId()
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="field-label">{{ label }}</label>
    <div class="relative">
      <select :id="id" v-model="model" class="field min-h-[40px] appearance-none pr-9">
        <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
      <PhCaretDown :size="14" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-fg-faint" />
    </div>
  </div>
</template>
