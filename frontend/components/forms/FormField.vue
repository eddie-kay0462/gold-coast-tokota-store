<script setup lang="ts">
/**
 * The shared input primitive for the checkout, booking and account forms.
 *
 * It previously used raw Tailwind (`border` with no colour, `text-sm`,
 * `font-medium`, `rounded`) rather than the design tokens, and stood 40px tall
 * — under the 44px tap floor. It now sits on `border-line` and the `label` /
 * `caption` type scale like the rest of the storefront.
 *
 * `type="textarea"` renders a `<textarea>`: `DiyOrderForm` collects sandal
 * measurements, which do not belong in a single-line input. `type="select"`
 * renders a `<select>` over `options` — the checkout country field needs one,
 * and README's component notes always described this field as
 * "text/select/date/file". The 16px font size is deliberate throughout:
 * anything smaller makes iOS Safari zoom on focus.
 */
defineProps<{
  label: string
  name: string
  /** Any native input type, or `textarea` / `select`. */
  type?: string
  required?: boolean
  error?: string
  placeholder?: string
  /** Rows for the textarea variant. */
  rows?: number
  /** Options for the select variant. */
  options?: { value: string, label: string }[]
  /** Hint shown under the control, before any error. */
  hint?: string
  disabled?: boolean
  autocomplete?: string
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
      :disabled="disabled"
      class="w-full min-w-0 resize-y border bg-white px-3 py-3 text-body text-graphite placeholder:text-muted disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted"
      :class="error ? 'border-sale' : 'border-line'"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${name}-error` : hint ? `${name}-hint` : undefined"
    />

    <!-- `appearance-none` would strip the disclosure arrow and leave no
         affordance, so the native control is kept as-is; only the box is
         restyled to match the text inputs. -->
    <select
      v-else-if="type === 'select'"
      :id="name"
      v-model="model"
      :name="name"
      :required="required"
      :disabled="disabled"
      class="min-h-[44px] w-full min-w-0 border bg-white px-3 py-2.5 text-body text-graphite disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted"
      :class="error ? 'border-sale' : 'border-line'"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${name}-error` : hint ? `${name}-hint` : undefined"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <input
      v-else
      :id="name"
      v-model="model"
      :name="name"
      :type="type || 'text'"
      :required="required"
      :placeholder="placeholder"
      :disabled="disabled"
      :autocomplete="autocomplete"
      class="min-h-[44px] w-full min-w-0 border bg-white px-3 py-2.5 text-body text-graphite placeholder:text-muted disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted"
      :class="error ? 'border-sale' : 'border-line'"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${name}-error` : hint ? `${name}-hint` : undefined"
    >

    <p v-if="error" :id="`${name}-error`" class="text-caption text-sale">{{ error }}</p>
    <p v-else-if="hint" :id="`${name}-hint`" class="text-caption text-muted">{{ hint }}</p>
  </div>
</template>
