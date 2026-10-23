<script setup lang="ts">
import { callingCode, callingCodeOptions, nationalPlaceholder, normaliseNational } from '~/utils/phone'

/**
 * A phone number in two boxes: a narrow country-code picker and the rest of
 * the number. The picker is a real `<select>` laid transparently over a box
 * that shows only "GH +233", so it stays small while the open list reads
 * "Ghana (+233)". Keyboard and screen readers get the native control.
 *
 * On blur the number is tidied (a pasted +233 or the leading 0 removed, per
 * `utils/phone.ts`), so what the customer sees is what gets saved.
 */
const props = defineProps<{
  label: string
  name: string
  required?: boolean
  error?: string
  hint?: string
}>()

const country = defineModel<string>('country', { required: true })
const number = defineModel<string>({ default: '' })

const options = callingCodeOptions()
const placeholder = computed(() => nationalPlaceholder(country.value))
const describedBy = computed(() =>
  props.error ? `${props.name}-error` : props.hint ? `${props.name}-hint` : undefined,
)

function tidy() {
  if (number.value.trim()) number.value = normaliseNational(country.value, number.value)
}
</script>

<template>
  <div class="flex w-full min-w-0 flex-col gap-1.5">
    <label :for="name" class="text-caption font-normal text-graphite">
      {{ label }}
      <span v-if="required" aria-hidden="true">*</span>
    </label>

    <div class="flex w-full min-w-0 gap-2">
      <div
        class="relative flex min-h-[44px] w-[108px] shrink-0 items-center justify-between gap-1 border bg-white px-3 text-body text-graphite focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ink"
        :class="error ? 'border-sale' : 'border-line'"
      >
        <span aria-hidden="true" class="whitespace-nowrap">
          <span class="text-caption text-muted">{{ country }}</span> +{{ callingCode(country) }}
        </span>
        <svg aria-hidden="true" viewBox="0 0 12 12" class="size-3 shrink-0 text-muted">
          <path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" />
        </svg>
        <select
          :id="`${name}-country`"
          v-model="country"
          :name="`${name}_country`"
          autocomplete="tel-country-code"
          :aria-label="`${label}: country code`"
          class="absolute inset-0 size-full cursor-pointer opacity-0"
          @change="tidy"
        >
          <option v-for="option in options" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>

      <input
        :id="name"
        v-model="number"
        :name="name"
        type="tel"
        inputmode="tel"
        autocomplete="tel-national"
        :required="required"
        :placeholder="placeholder"
        class="min-h-[44px] w-full min-w-0 border bg-white px-3 py-2.5 text-body text-graphite placeholder:text-muted"
        :class="error ? 'border-sale' : 'border-line'"
        :aria-invalid="!!error"
        :aria-describedby="describedBy"
        @blur="tidy"
      >
    </div>

    <p v-if="error" :id="`${name}-error`" class="text-caption text-sale">{{ error }}</p>
    <p v-else-if="hint" :id="`${name}-hint`" class="text-caption text-muted">{{ hint }}</p>
  </div>
</template>
