<script setup lang="ts">
import type { ApiOrder } from '~/utils/orders'
import { formatMoney } from '~/utils/formatters'

/**
 * A customer's orders, newest first: number, date, what was in it, total and
 * status. Each opens the order's page. Used by the account overview (the last
 * few) and the order history (all of them).
 */
defineProps<{ orders: ApiOrder[] }>()

function placedOn(iso: string): string {
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function summary(order: ApiOrder): string {
  const [first, ...rest] = order.items
  if (!first) return ''
  return rest.length ? `${first.name} and ${rest.length} more` : first.name
}
</script>

<template>
  <ul class="flex w-full flex-col border-t border-line">
    <li v-for="order in orders" :key="order.id" class="border-b border-line">
      <NuxtLink
        :to="`/order-confirmation/${order.reference}`"
        class="flex w-full flex-wrap items-start justify-between gap-x-6 gap-y-2 py-5 hover:bg-surface sm:flex-nowrap sm:items-center sm:px-2"
      >
        <span class="flex min-w-0 flex-col gap-1">
          <span class="text-label text-ink">{{ order.reference }}</span>
          <span class="text-caption text-muted">
            {{ placedOn(order.created_at) }} · {{ summary(order) }}
          </span>
        </span>
        <span class="flex shrink-0 items-center gap-4">
          <span class="text-label text-graphite">{{ formatMoney(order.total, order.currency) }}</span>
          <CommonStatusBadge :status="order.status" />
        </span>
      </NuxtLink>
    </li>
  </ul>
</template>
