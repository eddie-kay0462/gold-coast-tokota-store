<script setup lang="ts">
const props = defineProps<{ title: string }>()

// Share targets need the absolute canonical URL, which differs between SSR and
// client navigation — useRequestURL resolves both.
const url = useRequestURL()
const shareUrl = computed(() => url.href)

const targets = computed(() => [
  {
    name: 'X',
    icon: '/design/icons/social-twitter.svg',
    href: `https://twitter.com/intent/tweet?text=${encodeURIComponent(props.title)}&url=${encodeURIComponent(shareUrl.value)}`,
  },
  {
    name: 'Facebook',
    icon: '/design/icons/social-facebook.svg',
    href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl.value)}`,
  },
  {
    name: 'LinkedIn',
    icon: '/design/icons/social-linkedin.svg',
    href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl.value)}`,
  },
])
</script>

<template>
  <ul class="flex shrink-0 items-start gap-1.5">
    <li v-for="target in targets" :key="target.name">
      <a
        :href="target.href"
        target="_blank"
        rel="noopener noreferrer"
        class="block transition-opacity hover:opacity-60"
      >
        <img :src="target.icon" alt="" class="size-7" aria-hidden="true">
        <span class="sr-only">Share “{{ title }}” on {{ target.name }}</span>
      </a>
    </li>
  </ul>
</template>
