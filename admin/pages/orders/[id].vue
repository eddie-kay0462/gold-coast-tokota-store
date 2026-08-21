<script setup lang="ts">
import { PhArrowLeft } from '@phosphor-icons/vue'
import type { Order } from '~/types'

/**
 * Order detail as a page, for deep links and for anyone who wants a URL they
 * can send to a colleague. The list uses the drawer; this renders the same
 * information without the surrounding list.
 */
const route = useRoute()
const { useAdminItem } = useAdminApi()
const { item: order } = useAdminItem<Order>(`order-${route.params.id}`, `/admin/orders/${route.params.id}`)

useHead({ title: computed(() => order.value?.reference ?? 'Order') })

const open = ref(true)
</script>

<template>
  <div class="admin-stack">
    <UiPageHeader :title="order?.reference ?? 'Order'">
      <template #actions>
        <UiButton variant="ghost" size="sm" to="/orders">
          <PhArrowLeft :size="16" />
          All orders
        </UiButton>
      </template>
    </UiPageHeader>

    <div v-if="!order" class="card">
      <UiEmptyState title="Order not found" description="It may have been removed, or the reference is wrong." />
    </div>

    <template v-else>
      <UiButton variant="secondary" size="sm" @click="open = true">Open details</UiButton>
      <OrdersOrderDrawer v-model:open="open" :order="order" />
    </template>
  </div>
</template>
