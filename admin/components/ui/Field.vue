<script setup lang="ts">
/**
 * Labelled form control with error wiring. Mirrors the storefront's
 * `FormField` contract (label, error slot, aria-describedby) at admin density.
 */
const props = withDefaults(defineProps<{
  label?: string
  type?: string
  placeholder?: string
  error?: string
  hint?: string
  required?: boolean
  disabled?: boolean
  autocomplete?: string
  rows?: number
}>(), { type: 'text', rows: 4 })

const model = defineModel<string>({ default: '' })
const id = useId()
const describedBy = computed(() =>
  [props.error && `${id}-error`, props.hint && `${id}-hint`].filter(Boolean).join(' ') || undefined,
)
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="field-label">
      {{ label }}
      <span v-if="required" class="text-danger" aria-hidden="true">*</span>
    </label>

    <textarea
      v-if="type === 'textarea'"
      :id="id" v-model="model" :rows="rows" :placeholder="placeholder" :disabled="disabled"
      :aria-invalid="!!error" :aria-describedby="describedBy" class="field resize-y"
    />
    <div v-else class="relative">
      <input
        :id="id" v-model="model" :type="type" :placeholder="placeholder" :disabled="disabled"
        :autocomplete="autocomplete" :aria-invalid="!!error" :aria-describedby="describedBy"
        class="field min-h-[44px]" :class="$slots.suffix && 'pr-11'"
      >
      <span v-if="$slots.suffix" class="absolute inset-y-0 right-0 flex items-center pr-1">
        <slot name="suffix" />
      </span>
    </div>

    <span v-if="hint && !error" :id="`${id}-hint`" class="mt-1 block text-meta text-fg-faint">{{ hint }}</span>
    <span v-if="error" :id="`${id}-error`" class="field-error" role="alert">{{ error }}</span>
  </div>
</template>
