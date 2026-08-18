<script setup lang="ts">
// The 240px pill-less block button used across the landing page. Figma shows
// three fills: #262626 (default), #000000 (on light sections) and white
// (reversed, on photography).
const props = withDefaults(
  defineProps<{
    to?: string
    variant?: 'graphite' | 'ink' | 'white'
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
    class="flex w-[240px] max-w-full items-center justify-center py-3 text-label uppercase transition-opacity hover:opacity-80"
    :class="variantClass"
  >
    <slot />
  </component>
</template>
