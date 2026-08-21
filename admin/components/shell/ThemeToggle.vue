<script setup lang="ts">
import { PhSun, PhMoon, PhCircleHalf } from '@phosphor-icons/vue'

/**
 * The sun icon present in every Figma frame's header — here it cycles three
 * states rather than two, because "follow the system" is the default and the
 * user needs a way back to it.
 */
const { preference, resolved, cycle } = useTheme()

const meta = computed(() => ({
  light: { icon: PhSun, label: 'Light theme' },
  dark: { icon: PhMoon, label: 'Dark theme' },
  system: { icon: PhCircleHalf, label: `System theme (currently ${resolved.value})` },
}[preference.value]))
</script>

<template>
  <button
    type="button"
    class="toolbar-btn"
    :aria-label="`${meta.label}. Click to change.`"
    :title="meta.label"
    @click="cycle()"
  >
    <component :is="meta.icon" :size="20" />
  </button>
</template>
