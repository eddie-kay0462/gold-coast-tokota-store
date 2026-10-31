<script setup lang="ts">
import { PhArrowLeft, PhChatsTeardrop } from '@phosphor-icons/vue'
import type { Customer, Order } from '~/types'
import { formatMoney } from '~/utils/currency'

const route = useRoute()
const { useAdminItem, useAdminList } = useAdminApi()
const { formatDate, formatRelative } = useFormatters()

const { item: customer } = useAdminItem<Customer>(`customer-${route.params.id}`, `/admin/customers/${route.params.id}`)
// Asked for by customer, not the latest page of every order filtered here: an
// older customer's orders were off that page.
const { items: orders } = useAdminList<Order>(
  `customer-orders-${route.params.id}`, '/admin/orders', { customer_id: route.params.id, per_page: 500 }, { allPages: true },
)

useHead({ title: computed(() => customer.value?.name ?? 'Customer') })

const theirOrders = computed(() =>
  orders.value
    .filter((o) => o.customerId === customer.value?.id || o.customerEmail === customer.value?.email)
    .sort((a, b) => new Date(b.placedAt).getTime() - new Date(a.placedAt).getTime()),
)
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader :title="customer?.name ?? 'Customer'" :description="customer?.email">
      <template #actions>
        <UiButton variant="ghost" size="sm" to="/customers">
          <PhArrowLeft :size="16" />
          All customers
        </UiButton>
      </template>
    </UiPageHeader>

    <div v-if="!customer" class="card">
      <UiEmptyState title="Customer not found" />
    </div>

    <div v-else class="grid gap-4 lg:grid-cols-[320px,minmax(0,1fr)]">
      <aside class="flex flex-col gap-4">
        <div class="card card-pad">
          <div class="flex items-center gap-3">
            <UiAvatar :name="customer.name" :size="56" />
            <div class="min-w-0">
              <p class="truncate text-section text-fg-strong">{{ customer.name }}</p>
              <p class="truncate text-meta text-fg-muted">{{ customer.email }}</p>
            </div>
          </div>

          <dl class="mt-4 space-y-2.5 border-t border-border pt-4 text-ui">
            <div class="flex justify-between gap-3">
              <dt class="text-fg-muted">First name</dt>
              <dd class="truncate text-fg-strong">{{ customer.firstName }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-fg-muted">Last name</dt>
              <dd class="truncate text-fg-strong">{{ customer.lastName ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-fg-muted">Phone</dt>
              <dd class="text-fg-strong">{{ customer.phone ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-fg-muted">Location</dt>
              <dd class="text-fg-strong">{{ customer.country ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-fg-muted">Prefers</dt>
              <dd class="text-fg-strong">{{ customer.preferredCurrency }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-fg-muted">Account</dt>
              <dd class="text-fg-strong">{{ customer.hasAccount ? 'Registered' : 'Guest checkout' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-fg-muted">Customer since</dt>
              <dd class="text-fg-strong">{{ formatDate(customer.createdAt) }}</dd>
            </div>
          </dl>

          <UiButton variant="secondary" size="sm" to="/inbox" class="mt-4 w-full">
            <PhChatsTeardrop :size="16" />
            Message on WhatsApp
          </UiButton>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
          <UiMetricCard label="Orders" :value="String(customer.orderCount)" />
          <UiMetricCard
            label="Lifetime value"
            :value="customer.lifetimeValueUsd ? `${formatMoney(customer.lifetimeValue)} + ${formatMoney(customer.lifetimeValueUsd)}` : formatMoney(customer.lifetimeValue)"
          />
        </div>
      </aside>

      <section class="card">
        <div class="border-b border-border p-4 md:p-5">
          <h2 class="card-title">Order history</h2>
        </div>
        <UiEmptyState
          v-if="!theirOrders.length"
          title="No orders yet"
          description="This customer is on the list but hasn't ordered."
        />
        <ul v-else class="divide-y divide-border">
          <li v-for="o in theirOrders" :key="o.id">
            <NuxtLink :to="`/orders/${o.id}`" class="flex flex-wrap items-center gap-3 px-4 py-3 transition-colors hover:bg-bg-sunken md:px-5">
              <span class="min-w-0 flex-1">
                <span class="block font-mono text-ui text-fg-strong">{{ o.reference }}</span>
                <span class="block truncate text-meta text-fg-faint">
                  {{ o.items.map((i) => i.productName).join(', ') }}
                </span>
              </span>
              <span class="shrink-0 text-ui text-fg-strong">{{ formatMoney(o.total) }}</span>
              <UiStatusBadge :status="o.status" />
              <span class="shrink-0 text-meta text-fg-faint">{{ formatRelative(o.placedAt) }}</span>
            </NuxtLink>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
