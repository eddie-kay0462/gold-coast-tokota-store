<script setup lang="ts">
/**
 * NOTE: this renders in normal flow — it has no placement of its own, and there
 * is no shared toast region in `layouts/default.vue`. Nothing mounts it yet, so
 * where toasts appear (and how they stack, and whether they clear the fixed
 * WhatsApp button at `bottom-5 right-5`) is still an open decision; see
 * FOR_THE_TEAM.md. The width is bounded here so a long message cannot stretch
 * whatever container it eventually lands in.
 */
defineProps<{ message: string; variant?: 'success' | 'error' | 'info' }>()
</script>

<template>
  <Transition name="fade">
    <div
      class="max-w-full break-words rounded px-4 py-2 text-caption text-white sm:max-w-[420px]"
      :class="{
        'bg-green-600': variant === 'success',
        'bg-red-600': variant === 'error',
        'bg-gray-800': !variant || variant === 'info',
      }"
    >
      {{ message }}
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
