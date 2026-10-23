<script setup lang="ts">
import type { ApiOrder } from '~/utils/orders'
import { whatsappMessage } from '~/utils/whatsapp'

/**
 * Order history: the signed-in customer's orders (`GET /orders`, whose
 * customer is always the session's). Guest orders aren't here; they're found
 * with order tracking on /account.
 */
definePageMeta({ middleware: 'auth' })

const api = useApi()
const orders = ref<ApiOrder[]>([])
const loading = ref(true)
const failed = ref(false)

onMounted(async () => {
  try {
    orders.value = (await api<{ data: ApiOrder[] }>('/orders')).data
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
})

useSeoMeta({
  title: 'Your orders | Gold Coast Tokota',
  description: 'Your Gold Coast Tokota order history.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <AccountShell heading="Your orders" description="Everything you’ve ordered from us while signed in.">
      <div v-if="loading" class="flex w-full flex-col gap-3">
        <CommonSkeletonLoader height="4rem" width="100%" />
        <CommonSkeletonLoader height="4rem" width="100%" />
      </div>

      <CommonInlineNotice v-else-if="failed" variant="warning" title="We couldn’t load your orders">
        Try again in a moment, or message us and we’ll look them up for you.
      </CommonInlineNotice>

      <AccountOrderList v-else-if="orders.length" :orders="orders" />

      <div v-else class="flex w-full flex-col items-center gap-3 border-t border-line px-2 py-14 text-center">
        <p class="caps-title">No orders yet</p>
        <p class="w-full max-w-[560px] text-label text-subtle">
          Orders you place while signed in will appear here. If you ordered as a guest, use
          <NuxtLink to="/account" class="underline hover:no-underline">order tracking</NuxtLink>
          instead. You’ll need your order number and the email you used.
        </p>
        <div class="flex flex-col items-stretch gap-3 pt-3 sm:flex-row sm:items-start">
          <CommonBrandButton to="/shop">Shop sandals</CommonBrandButton>
          <CommonWhatsAppLink source="order-help" :message="whatsappMessage.orderHelp()">
            Ask about an order
          </CommonWhatsAppLink>
        </div>
      </div>
    </AccountShell>
  </div>
</template>
