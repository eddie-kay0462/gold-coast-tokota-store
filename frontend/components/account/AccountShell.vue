<script setup lang="ts">
import { accountNav } from '~/utils/navigation'

/**
 * Chrome for the signed-in area.
 *
 * A component rather than `layouts/account.vue`: the storefront has exactly one
 * layout, and a second would either duplicate the header/footer/cart-drawer
 * shell or need `<NuxtLayout>` nesting to avoid it. A wrapper component costs
 * neither, and `AboutSectionNav` already sets the precedent for a section nav
 * that lives inside the page.
 *
 * `/account/login` and `/account/register` deliberately do not use this — they
 * are pre-session pages with no nav to show.
 */
defineProps<{
  heading: string
  description?: string
}>()

const route = useRoute()
</script>

<template>
  <div class="page-gutter section-y mx-auto flex w-full max-w-[1190px] flex-col items-start gap-8">
    <header class="flex w-full flex-col items-start gap-2">
      <h1 class="w-full text-display-section font-normal text-black">{{ heading }}</h1>
      <p v-if="description" class="w-full max-w-[720px] text-body text-graphite">
        {{ description }}
      </p>
    </header>

    <!-- Stacks on a phone, becomes a rail from `md`. The ladder matters: base →
         `md` → (nothing further needed), never base → `lg`. -->
    <div class="flex w-full flex-col items-start gap-8 md:flex-row md:gap-12">
      <nav class="w-full shrink-0 md:w-[220px]" aria-label="Account sections">
        <ul
          class="flex w-full items-center gap-4 overflow-x-auto border-b border-line md:flex-col md:items-start md:gap-1 md:overflow-visible md:border-b-0 md:border-l"
        >
          <li v-for="item in accountNav" :key="item.to" class="md:w-full">
            <NuxtLink
              :to="item.to"
              class="flex min-h-[44px] items-center whitespace-nowrap py-3 text-label hover:text-graphite md:w-full md:border-l-2 md:px-4"
              :class="
                route.path === item.to
                  ? 'text-graphite md:border-graphite'
                  : 'text-muted md:border-transparent'
              "
              :aria-current="route.path === item.to ? 'page' : undefined"
            >{{ item.label }}</NuxtLink>
          </li>
        </ul>
      </nav>

      <!-- `w-full min-w-0`: the parent is a `flex-col items-start` below `md`,
           which collapses children to their content width without it. -->
      <div class="flex w-full min-w-0 flex-1 flex-col items-start gap-6">
        <slot />
      </div>
    </div>
  </div>
</template>
