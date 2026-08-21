<script setup lang="ts">
/**
 * The shared input primitive for the checkout and booking forms.
 *
 * It previously used raw Tailwind (`border` with no colour, `text-sm`,
 * `font-medium`, `rounded`) rather than the design tokens, and stood 40px tall
 * — under the 44px tap floor. It now sits on `border-line` and the `label` /
 * `caption` type scale like the rest of the storefront.
 *
 * `type="textarea"` renders a `<textarea>`: `DiyOrderForm` collects sandal
 * measurements, which do not belong in a single-line input. The 16px font size
 * is deliberate — anything smaller makes iOS Safari zoom on focus.
 */
defineProps<{
  label: string
  name: string
  /** Any native input type, or `textarea` for multi-line. */
  type?: string
  required?: boolean
  error?: string
  placeholder?: string
  /** Rows for the textarea variant. */
  rows?: number
}>()

const model = defineModel<string>()
</script>

<template>
  <div class="flex w-full min-w-0 flex-col gap-1.5">
    <label :for="name" class="text-caption font-normal text-graphite">
      {{ label }}
      <span v-if="required" aria-hidden="true">*</span>
    </label>

    <textarea
      v-if="type === 'textarea'"
      :id="name"
      v-model="model"
      :name="name"
      :required="required"
      :placeholder="placeholder"
      :rows="rows || 4"
      class="w-full min-w-0 resize-y border bg-white px-3 py-3 text-body text-graphite placeholder:text-muted"
      :class="error ? 'border-sale' : 'border-line'"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${name}-error` : undefined"
    />

    <input
      v-else
      :id="name"
      v-model="model"
      :name="name"
      :type="type || 'text'"
      :required="required"
      :placeholder="placeholder"
      class="min-h-[44px] w-full min-w-0 border bg-white px-3 py-2.5 text-body text-graphite placeholder:text-muted"
      :class="error ? 'border-sale' : 'border-line'"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${name}-error` : undefined"
    >

    <p v-if="error" :id="`${name}-error`" class="text-caption text-sale">{{ error }}</p>
  </div>
</template>
