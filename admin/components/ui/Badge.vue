<script setup lang="ts">
/**
 * Small count / label pill. Tones map to the semantic tokens, so dark mode
 * needs no variant here — see assets/css/main.css.
 */
type Tone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info' | 'outline'

const props = withDefaults(defineProps<{
  tone?: Tone
  size?: 'sm' | 'md'
  /** Renders a leading dot instead of an icon slot. */
  dot?: boolean
}>(), { tone: 'neutral', size: 'md', dot: false })

const tones: Record<Tone, string> = {
  neutral: 'bg-neutral-soft text-fg-subtle',
  accent: 'bg-accent-soft text-accent-text',
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-warning',
  danger: 'bg-danger-soft text-danger',
  info: 'bg-info-soft text-info',
  outline: 'border border-border text-fg-muted',
}

const dots: Record<Tone, string> = {
  neutral: 'bg-fg-faint',
  accent: 'bg-accent',
  success: 'bg-success',
  warning: 'bg-warning',
  danger: 'bg-danger',
  info: 'bg-info',
  outline: 'bg-fg-faint',
}
</script>

<template>
  <span
    class="chip"
    :class="[tones[props.tone], props.size === 'sm' && 'px-1.5 py-0.5 text-micro']"
  >
    <span v-if="dot" class="size-1.5 rounded-pill" :class="dots[props.tone]" />
    <slot />
  </span>
</template>
