<script setup lang="ts">
// The 240px block button used across the site. Every variant now wears the
// same `.btn-outline` look (white, thin black outline, black only on hover or
// focus, see `assets/css/main.css`); the client asked for that in place of
// Figma's solid #262626 / #000000 / white fills. `graphite` and `ink` are kept
// as names so the call sites didn't churn; `white` marks a button on a dark
// ground or photography, which keeps a white edge when filled. Two shapes: the square-cornered uppercase `block` and
// the soft-cornered mixed-case `soft` used on the Sustainability page
// (10:963), where the label carries a handle that uppercasing would mangle.
const props = withDefaults(
  defineProps<{
    to?: string
    variant?: 'graphite' | 'ink' | 'white'
    shape?: 'block' | 'soft'
    /** Stretch to the container instead of the design's fixed 240px block. */
    full?: boolean
    disabled?: boolean
    /** Native button type. Ignored when `to` is set, since that renders a link. */
    type?: 'button' | 'submit' | 'reset'
  }>(),
  { variant: 'graphite', shape: 'block', type: 'button' },
)

// resolveComponent() must run in setup; it is not in scope inside template
// expressions, where it silently renders a literal <NuxtLink> element instead.
const linkComponent = resolveComponent('NuxtLink')

const variantClass = computed(
  () =>
    ({
      graphite: 'btn-outline',
      ink: 'btn-outline',
      white: 'btn-outline-on-dark',
    })[props.variant],
)

const shapeClass = computed(
  () =>
    ({
      block: 'min-h-[44px] py-3 text-label uppercase',
      soft: 'min-h-[44px] rounded-lg py-5 text-action',
    })[props.shape],
)
</script>

<template>
  <component
    :is="to ? linkComponent : 'button'"
    :to="to"
    :type="to ? undefined : type"
    :disabled="to ? undefined : disabled"
    class="flex max-w-full items-center justify-center text-center disabled:cursor-not-allowed disabled:opacity-40"
    :class="[variantClass, shapeClass, full ? 'w-full' : 'w-[240px]']"
  >
    <slot />
  </component>
</template>
