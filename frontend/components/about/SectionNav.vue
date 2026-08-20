<script setup lang="ts">
import { aboutSectionNav } from '~/utils/navigation'

const route = useRoute()

/** A tab is current when both its path and its anchor match the location. */
function isCurrent(to: string) {
  const [path, hash] = to.split('#')
  if (route.path !== path) return false
  return hash ? route.hash === `#${hash}` : !route.hash
}
</script>

<template>
  <!-- Figma 6:554. Scrolls horizontally below lg rather than wrapping, so the
       tab row keeps its single-line rhythm on narrow screens. -->
  <nav
    class="w-full overflow-x-auto bg-white"
    aria-label="About sections"
  >
    <ul class="flex items-center justify-start px-5 lg:justify-center lg:px-0">
      <li v-for="item in aboutSectionNav" :key="item.label">
        <NuxtLink
          :to="item.to"
          class="flex flex-col items-start px-3 py-5 hover:underline"
          :aria-current="isCurrent(item.to) ? 'page' : undefined"
        >
          <span class="whitespace-nowrap text-center text-caption text-graphite">
            {{ item.label }}
          </span>
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
