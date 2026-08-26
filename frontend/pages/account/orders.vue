<script setup lang="ts">
import { AUTH_DISABLED_NOTICE, AUTH_ENABLED } from '~/composables/useAuth'

/**
 * Order history.
 *
 * No session and no `GET /api/v1/orders` endpoint, so this is an empty state —
 * but one designed as though the list existed, so the real list drops into the
 * same panel without a redesign.
 *
 * When customer auth lands this page gets
 * `definePageMeta({ middleware: 'auth' })`. It deliberately has none today:
 * a guard would make the page unreachable while nothing can authenticate.
 */
const { href: whatsappHref } = useWhatsApp()

useSeoMeta({
  title: 'Your orders — Gold Coast Tokota',
  description: 'Your Gold Coast Tokota order history.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <AccountShell heading="Your orders" description="Everything you’ve ordered from us.">
      <CommonInlineNotice v-if="!AUTH_ENABLED" variant="warning" title="Sign-in isn’t enabled yet">
        {{ AUTH_DISABLED_NOTICE }}
      </CommonInlineNotice>

      <div class="flex w-full flex-col items-start gap-4 border border-line p-8">
        <p class="w-full text-body text-graphite">No orders to show yet.</p>
        <p class="w-full max-w-[560px] text-caption text-muted">
          Orders you place while signed in will appear here. If you ordered as a guest, use
          <NuxtLink to="/account" class="underline hover:no-underline">order tracking</NuxtLink>
          instead — you’ll need your order number and the email you used.
        </p>
        <div class="flex flex-col items-stretch gap-3 pt-2 sm:flex-row sm:items-start">
          <CommonBrandButton to="/shop">Shop sandals</CommonBrandButton>
          <CommonBrandButton v-if="whatsappHref" :to="whatsappHref" variant="white">
            Ask about an order
          </CommonBrandButton>
        </div>
      </div>
    </AccountShell>
  </div>
</template>
