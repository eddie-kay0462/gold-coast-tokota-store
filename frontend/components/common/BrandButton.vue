<script setup lang="ts">
// The 240px block button used across the landing page. Figma shows three
// fills: #262626 (default), #000000 (on light sections) and white (reversed,
// on photography), and two shapes — the square-cornered uppercase `block` and
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
  }>(),
  { variant: 'graphite', shape: 'block' },
)

// resolveComponent() must run in setup — it is not in scope inside template
// expressions, where it silently renders a literal <NuxtLink> element instead.
const linkComponent = resolveComponent('NuxtLink')

const variantClass = computed(
  () =>
    ({
      graphite: 'bg-graphite text-white',
      ink: 'bg-ink text-white',
      white: 'bg-white text-graphite',
    })[props.variant],
)

const shapeClass = computed(
  () =>
    ({
      block: 'py-3 text-label uppercase',
      soft: 'rounded-lg py-5 text-action',
    })[props.shape],
)
</script>

<template>
  <component
    :is="to ? linkComponent : 'button'"
    :to="to"
    :type="to ? undefined : 'button'"
    :disabled="to ? undefined : disabled"
    class="flex max-w-full items-center justify-center text-center transition-opacity hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-40"
    :class="[variantClass, shapeClass, full ? 'w-full' : 'w-[240px]']"
  >
    <slot />
  </component>
</template>
