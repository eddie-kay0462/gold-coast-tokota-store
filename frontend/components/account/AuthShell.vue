<script setup lang="ts">
/**
 * The frame shared by sign-in and register, in the home page's look: a tall
 * product photo beside the form from `lg` (the 3:4 crop the product rail uses),
 * and above the form a pair of tracked-capital tabs that switch between the
 * two pages, styled like the header's category row.
 *
 * Below `lg` the photo is dropped. On a phone it would push the first field
 * below the fold.
 */
defineProps<{
  heading: string
}>()

const route = useRoute()

const tabs = [
  { label: 'Sign in', to: '/account/login' },
  { label: 'Create account', to: '/account/register' },
]
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto grid w-full max-w-[1190px] grid-cols-1 items-start gap-12 lg:grid-cols-2 lg:gap-16">
      <img
        src="/design/about-quality.png"
        alt="A pair of blue leather Gold Coast Tokota slides, seen from above"
        class="hidden aspect-[3/4] w-full bg-surface object-cover lg:block"
        width="1170"
        height="1560"
      >

      <div class="mx-auto flex w-full max-w-[440px] flex-col items-start gap-8 lg:mx-0 lg:pt-6">
        <nav class="flex w-full gap-6 border-b border-line" aria-label="Account access">
          <NuxtLink
            v-for="tab in tabs"
            :key="tab.to"
            :to="tab.to"
            class="caps-label -mb-px flex min-h-[44px] items-center border-b transition-colors"
            :class="route.path === tab.to ? 'border-ink text-ink' : 'border-transparent text-subtle hover:text-ink'"
            :aria-current="route.path === tab.to ? 'page' : undefined"
          >
            {{ tab.label }}
          </NuxtLink>
        </nav>

        <header class="flex w-full flex-col items-start gap-2">
          <h1 class="w-full text-display-md text-ink">{{ heading }}</h1>
          <p v-if="$slots.intro" class="w-full text-label text-subtle">
            <slot name="intro" />
          </p>
        </header>

        <slot />

        <!-- Guest checkout is the default (README Feature 4), so every way into
             an account also says plainly that none is needed. -->
        <div class="flex w-full flex-col items-start gap-2 border-t border-line pt-6">
          <p class="caps-label text-muted">No account needed</p>
          <p class="text-label text-subtle">
            You can order as a guest and we’ll email your confirmation.
          </p>
          <NuxtLink
            to="/shop"
            class="caps-label -my-3 flex min-h-[44px] items-center py-3 text-ink underline underline-offset-4 hover:no-underline"
          >
            Shop as a guest
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
