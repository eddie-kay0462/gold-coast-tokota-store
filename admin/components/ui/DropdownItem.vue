<script setup lang="ts">
defineProps<{ to?: string; danger?: boolean; disabled?: boolean }>()
</script>

<template>
  <component
    :is="to ? resolveComponent('NuxtLink') : 'button'"
    :to="to" :type="to ? undefined : 'button'" :disabled="disabled"
    role="menuitem"
    class="flex w-full items-center gap-2.5 rounded px-2.5 py-2 text-left text-ui transition-colors
           disabled:cursor-not-allowed disabled:opacity-50"
    :class="danger ? 'text-danger hover:bg-danger-soft' : 'text-fg hover:bg-bg-sunken'"
  >
    <slot />
  </component>
</template>
