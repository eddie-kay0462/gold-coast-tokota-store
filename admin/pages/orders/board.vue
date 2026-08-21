<script setup lang="ts">
import { PhList, PhTruck } from '@phosphor-icons/vue'
import type { Order, OrderStatus } from '~/types'
import { ORDER_BOARD_COLUMNS } from '~/types'
import { formatMoney } from '~/utils/currency'
import { humanise } from '~/utils/formatters'

/**
 * Fulfilment board — Figma node 19:945 (the "Deals" frame): a column per
 * pipeline stage, each headed by its own total and count, with cards beneath.
 *
 * The kit's pipeline is a sales funnel; ours is the order lifecycle from
 * README's Order.status, minus the terminal states (cancelled, refunded,
 * inventory_conflict) which are exceptions to handle, not stages to move
 * through. Those stay on the Orders table.
 *
 * Columns scroll horizontally below xl rather than reflowing — a kanban that
 * stacks vertically stops being a kanban.
 */
useHead({ title: 'Fulfilment' })

const { useAdminList } = useAdminApi()
const { formatRelative } = useFormatters()
const { items: orders, pending } = useAdminList<Order>('admin-orders-board', '/admin/orders')

const columns = computed(() =>
  ORDER_BOARD_COLUMNS.map((status: OrderStatus) => {
    const rows = orders.value
      .filter((o) => o.status === status)
      .sort((a, b) => new Date(b.placedAt).getTime() - new Date(a.placedAt).getTime())
    // Totals only sum within a currency; adding cedis to dollars would be
    // a number that means nothing.
    const ghs = rows.filter((o) => o.currency === 'GHS').reduce((s, o) => s + o.total.amount, 0)
    const usd = rows.filter((o) => o.currency === 'USD').reduce((s, o) => s + o.total.amount, 0)
    return { status, rows, ghs, usd }
  }),
)

const active = ref<Order | null>(null)
const drawerOpen = ref(false)
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader
      title="Fulfilment"
      description="Orders by stage. Terminal states — cancelled, refunded, stock conflicts — stay on the Orders list."
    >
      <template #actions>
        <UiButton variant="secondary" size="sm" to="/orders">
          <PhList :size="16" />
          List view
        </UiButton>
      </template>
    </UiPageHeader>

    <div v-if="pending" class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
      <div v-for="i in 5" :key="i" class="h-64 animate-pulse rounded-lg bg-bg-sunken" />
    </div>

    <div v-else class="-mx-4 overflow-x-auto px-4 pb-2 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
      <div class="flex min-w-max gap-4">
        <section v-for="col in columns" :key="col.status" class="flex w-[280px] shrink-0 flex-col gap-3">
          <!-- Column head, per the frame: label, total, count -->
          <header class="card card-pad">
            <div class="flex items-center gap-2">
              <UiStatusBadge :status="col.status" />
              <span class="ml-auto text-meta text-fg-faint">
                {{ col.rows.length }} order{{ col.rows.length === 1 ? '' : 's' }}
              </span>
            </div>
            <p class="mt-2 text-metric font-light tracking-tight text-fg-strong">
              {{ formatMoney({ amount: col.ghs, currency: 'GHS' }) }}
            </p>
            <p v-if="col.usd" class="text-meta text-fg-faint">
              + {{ formatMoney({ amount: col.usd, currency: 'USD' }) }}
            </p>
          </header>

          <div v-if="!col.rows.length" class="rounded-lg border border-dashed border-border px-3 py-8 text-center">
            <p class="text-meta text-fg-faint">Nothing {{ humanise(col.status).toLowerCase() }}</p>
          </div>

          <button
            v-for="o in col.rows" :key="o.id"
            type="button"
            class="card card-pad w-full text-left transition-colors hover:border-border-strong"
            @click="active = o; drawerOpen = true"
          >
            <div class="flex items-start justify-between gap-2">
              <span class="font-mono text-meta text-fg-faint">{{ o.reference }}</span>
              <span class="shrink-0 text-ui font-medium text-fg-strong">{{ formatMoney(o.total) }}</span>
            </div>
            <p class="mt-1.5 truncate text-ui text-fg-strong">{{ o.customerName }}</p>
            <p class="mt-0.5 truncate text-meta text-fg-muted">
              {{ o.items.map((i) => i.productName).join(', ') }}
            </p>
            <div class="mt-2.5 flex items-center gap-2 text-meta text-fg-faint">
              <PhTruck :size="14" />
              <span class="uppercase">{{ o.deliveryProvider }}</span>
              <span class="truncate">· {{ o.shippingAddress.country }}</span>
              <span class="ml-auto shrink-0">{{ formatRelative(o.placedAt) }}</span>
            </div>
          </button>
        </section>
      </div>
    </div>

    <OrdersOrderDrawer v-model:open="drawerOpen" :order="active" />
  </div>
</template>
