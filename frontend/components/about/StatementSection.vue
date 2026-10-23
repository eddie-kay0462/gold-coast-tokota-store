<script setup lang="ts">
/** The one opening line under the hero (Content › Pages › About, statement). */
defineProps<{ text: string }>()
</script>

<template>
  <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] items-center justify-center">
    <p class="min-w-0 flex-1 whitespace-pre-line text-center font-light text-black text-display-statement">{{ text }}</p>
  </section>
</template>
