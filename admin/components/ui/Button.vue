<script setup lang="ts">
/**
 * Button.
 *
 * Fills mirror the storefront's `CommonBrandButton` (frontend/components/
 * common/BrandButton.vue) so the two apps agree on what "primary" looks like,
 * with a denser `sm` size added for the admin's toolbars and table rows.
 */
type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'accent'
type Size = 'sm' | 'md' | 'lg'

const props = withDefaults(defineProps<{
  variant?: Variant
  size?: Size
  type?: 'button' | 'submit' | 'reset'
  to?: string
  disabled?: boolean
  loading?: boolean
  block?: boolean
}>(), { variant: 'primary', size: 'md', type: 'button', disabled: false, loading: false, block: false })

const variants: Record<Variant, string> = {
  primary: 'bg-primary text-primary-fg hover:bg-primary-hover',
  secondary: 'border border-border bg-bg-elevated text-fg-strong hover:bg-bg-sunken',
  ghost: 'text-fg-muted hover:bg-bg-sunken hover:text-fg-strong',
  danger: 'bg-danger text-white hover:opacity-90',
  accent: 'bg-accent text-accent-fg hover:opacity-90',
}

// 44px at `md` and `lg` — the tap-target floor the storefront works to.
const sizes: Record<Size, string> = {
  sm: 'h-9 px-3 text-meta gap-1.5',
  md: 'min-h-[44px] px-4 text-ui gap-2',
  lg: 'min-h-[48px] px-6 text-ui gap-2',
}

const classes = computed(() => [
  'inline-flex items-center justify-center rounded-lg font-medium transition-colors',
  'disabled:cursor-not-allowed disabled:opacity-50',
  variants[props.variant],
  sizes[props.size],
  props.block && 'w-full',
])
</script>

<template>
  <NuxtLink v-if="to && !disabled" :to="to" :class="classes">
    <slot />
  </NuxtLink>
  <button v-else :type="type" :disabled="disabled || loading" :class="classes">
    <span
      v-if="loading"
      class="size-4 shrink-0 animate-spin rounded-pill border-2 border-current border-t-transparent"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>
