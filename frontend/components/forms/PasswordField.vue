<script setup lang="ts">
import { PhEye, PhEyeSlash } from '@phosphor-icons/vue'

/**
 * A password box with a show/hide button joined to its right (`field-group`
 * in main.css): one border, one focus ring. Used by sign-in, sign-up and
 * password reset. Each field toggles on its own, so showing the new password
 * doesn't also reveal the confirmation.
 */
const props = defineProps<{
  label: string
  name: string
  autocomplete?: string
  required?: boolean
  error?: string
  hint?: string
}>()

const model = defineModel<string>({ default: '' })
const visible = ref(false)

const describedBy = computed(() =>
  props.error ? `${props.name}-error` : props.hint ? `${props.name}-hint` : undefined,
)
</script>

<template>
  <div class="flex w-full min-w-0 flex-col gap-1.5">
    <label :for="name" class="text-caption font-normal text-graphite">
      {{ label }}
      <span v-if="required" aria-hidden="true">*</span>
    </label>
    <div class="field-group" :class="{ 'border-sale': error }">
      <input
        :id="name"
        v-model="model"
        :name="name"
        :type="visible ? 'text' : 'password'"
        :autocomplete="autocomplete"
        :required="required"
        class="min-h-[44px] w-full min-w-0 flex-1 bg-transparent px-3 py-2.5 text-body text-graphite placeholder:text-muted"
        :aria-invalid="!!error"
        :aria-describedby="describedBy"
      >
      <!-- w-11 and the group's full height: the 44px tap floor applies to
           the button, not the glyph inside it. -->
      <button
        type="button"
        class="flex w-11 shrink-0 items-center justify-center border-l text-subtle transition-colors hover:text-ink focus-visible:bg-surface focus-visible:text-ink"
        :class="error ? 'border-sale' : 'border-line'"
        :aria-label="visible ? `Hide ${label.toLowerCase()}` : `Show ${label.toLowerCase()}`"
        :aria-pressed="visible"
        @click="visible = !visible"
      >
        <component :is="visible ? PhEyeSlash : PhEye" :size="18" />
      </button>
    </div>
    <p v-if="error" :id="`${name}-error`" class="text-caption text-sale">{{ error }}</p>
    <p v-else-if="hint" :id="`${name}-hint`" class="text-caption text-muted">{{ hint }}</p>
  </div>
</template>
