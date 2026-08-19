<script setup lang="ts">
// The 240px pill-less block button used across the landing page. Figma shows
// three fills: #262626 (default), #000000 (on light sections) and white
// (reversed, on photography).
const props = withDefaults(
  defineProps<{
    to?: string
    variant?: 'graphite' | 'ink' | 'white'
    /** Stretch to the container instead of the design's fixed 240px block. */
    full?: boolean
    disabled?: boolean
  }>(),
  { variant: 'graphite' },
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
</script>

<template>
  <component
    :is="to ? linkComponent : 'button'"
    :to="to"
    :type="to ? undefined : 'button'"
    :disabled="to ? undefined : disabled"
    class="flex max-w-full items-center justify-center py-3 text-label uppercase transition-opacity hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-40"
    :class="[variantClass, full ? 'w-full' : 'w-[240px]']"
  >
    <slot />
  </component>
</template>
