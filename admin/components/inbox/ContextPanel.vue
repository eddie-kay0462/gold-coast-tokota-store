<script setup lang="ts">
import {
  PhUser, PhPackage, PhCalendarBlank, PhInfo, PhMagnifyingGlass, PhArrowBendDownLeft,
  PhArrowSquareOut, PhX, PhWarningCircle, PhShoppingCartSimple,
} from '@phosphor-icons/vue'
import type { ChatThread, EventsLookup, InboxContext, InfoLookup, OrderLineDraft, StockLookupProduct, StockVariant } from '~/types'
import { formatMoney } from '~/utils/currency'

/**
 * The inbox's third column: what the person answering needs without leaving
 * the chat. Who the customer is, what is in stock right now, what is coming
 * up, and the published rules.
 *
 * Every row can be inserted into the reply as a short, plain line, written
 * in the brand's customer-service voice (§7: warm, helpful, respectful,
 * prompt). Inserting never sends: the reply box is always the last word.
 *
 * Each tab shows only if the account could see that data anywhere else in
 * the admin; the API enforces the same.
 *
 * Stock is sellable stock (on the shelf minus held for orders awaiting
 * payment), refreshed every 30 s while open. It is a snapshot: building an
 * order takes a hold, and that is what decides.
 */
const props = defineProps<{ thread: ChatThread | null; live: boolean }>()
const emit = defineEmits<{
  (e: 'insert', text: string): void
  (e: 'close'): void
  (e: 'add-to-order', line: OrderLineDraft): void
  (e: 'resend-link' | 'cancel-order', reference: string): void
}>()

function addToOrder(p: StockLookupProduct, v: StockVariant) {
  emit('add-to-order', {
    inventoryItemId: v.inventoryItemId,
    quantity: 1,
    productName: p.name,
    variantLabel: [v.size, v.colour].filter(Boolean).join(' | ') || null,
    unitPrice: p.price,
    unitPriceUsd: p.priceUsd,
    sellable: v.sellable,
    problem: !p.isActive ? 'This product is no longer on sale.' : v.sellable === 0 ? 'Sold out.' : null,
  })
}

/** Exposed so the inbox can refresh the customer tab after an order changes. */
defineExpose({ reload: () => loadContext() })

const { adminFetch } = useAdminApi()
const { can } = useAuth()
const { formatDate, formatRelative, humanise } = useFormatters()

type Tab = 'customer' | 'stock' | 'events' | 'info'
const tabs = computed(() => ([
  { key: 'customer', label: 'Customer', icon: PhUser, show: can('customers.view') },
  { key: 'stock', label: 'Stock', icon: PhPackage, show: can('inventory.view') },
  { key: 'events', label: 'Events', icon: PhCalendarBlank, show: can('bookings.view') },
  { key: 'info', label: 'Info', icon: PhInfo, show: true },
] as const).filter((t) => t.show))

const tab = ref<Tab>('customer')
watchEffect(() => {
  if (!tabs.value.some((t) => t.key === tab.value)) tab.value = tabs.value[0]!.key
})

const unavailable = 'Available once the inbox is connected to the API.'

// --- customer ---------------------------------------------------------------

const context = ref<InboxContext | null>(null)
const contextError = ref(false)

async function loadContext() {
  if (!props.thread || !props.live || !can('customers.view')) return
  contextError.value = false
  try {
    context.value = (await adminFetch<InboxContext>(`/admin/inbox/threads/${props.thread.id}/context`)).data
  } catch {
    contextError.value = true
  }
}

watch(() => props.thread?.id, () => { context.value = null; loadContext() }, { immediate: true })
// The linked order can change from the thread header.
watch(() => props.thread?.linkedOrderReference, loadContext)

// --- stock --------------------------------------------------------------------

const query = ref('')
const products = ref<StockLookupProduct[]>([])
const stockLoading = ref(false)
const expanded = ref<number | null>(null)

async function loadStock() {
  if (!props.live || !can('inventory.view')) return
  stockLoading.value = true
  try {
    products.value = (await adminFetch<StockLookupProduct[]>('/admin/inbox/lookup/stock', { query: { q: query.value.trim() } })).data
    if (products.value.length === 1) expanded.value = products.value[0]!.id
  } finally {
    stockLoading.value = false
  }
}

let debounce: ReturnType<typeof setTimeout> | null = null
watch(query, () => {
  if (debounce) clearTimeout(debounce)
  debounce = setTimeout(loadStock, 300)
})

// --- events and info ------------------------------------------------------------

const events = ref<EventsLookup | null>(null)
const info = ref<InfoLookup | null>(null)

async function loadEvents() {
  if (!props.live || !can('bookings.view')) return
  events.value = (await adminFetch<EventsLookup>('/admin/inbox/lookup/events')).data
}

async function loadInfo() {
  if (!props.live || info.value) return
  info.value = (await adminFetch<InfoLookup>('/admin/inbox/lookup/info')).data
}

function loadTab(t: Tab) {
  const load = { customer: loadContext, stock: loadStock, events: loadEvents, info: loadInfo }[t]
  return load().catch(() => {})
}

watch(tab, loadTab)
watch(() => props.live, (live) => { if (live) loadTab(tab.value) }, { immediate: true })

// Stock and seats move while a chat is open; refresh the tab in view.
usePolling(() => (tab.value === 'stock' || tab.value === 'events') ? loadTab(tab.value) : undefined, 30_000)

// --- the lines inserted into a reply --------------------------------------------

const say = {
  variant(p: StockLookupProduct, v: StockVariant) {
    return v.sellable > 0
      ? `Good news, ${p.name} in ${v.label.toLowerCase()} is available. It's ${formatMoney(p.price)}.`
      : `${p.name} in ${v.label.toLowerCase()} is sold out at the moment.`
  },
  product(p: StockLookupProduct) {
    return `${p.name} is ${formatMoney(p.price)}${p.priceUsd ? ` (about ${formatMoney(p.priceUsd)})` : ''}. You can see it here: ${p.url}`
  },
  session(s: EventsLookup['sessions'][number]) {
    const when = `${formatDate(s.date, { weekday: 'long', day: 'numeric', month: 'long' })}, ${s.slot}`
    return s.remaining > 0
      ? `${s.experience} on ${when} has ${s.remaining} ${s.remaining === 1 ? 'place' : 'places'} left.`
      : `${s.experience} on ${when} is full, but I can add you to the waitlist.`
  },
  appointment(t: EventsLookup['byAppointment'][number]) {
    return `${t.name} runs by appointment${t.capacity ? `, for up to ${t.capacity} people` : ''}${t.duration ? ` (${t.duration})` : ''}. Which date would suit you?`
  },
  order(o: InboxContext['orders'][number]) {
    const tracking = o.trackingUrl ? ` You can track it here: ${o.trackingUrl}` : ''
    return `Your order ${o.reference} is ${humanise(o.status).toLowerCase()}.${tracking}`
  },
}

function stockTone(n: number) {
  return n === 0 ? 'danger' : n <= 2 ? 'warning' : 'success'
}
</script>

<template>
  <aside class="flex min-h-0 flex-col" aria-label="Customer and stock details">
    <div class="flex shrink-0 items-center gap-1 border-b border-border px-2 pt-2">
      <button
        v-for="t in tabs" :key="t.key" type="button"
        class="-mb-px flex items-center gap-1.5 border-b-2 px-2.5 pb-2 text-meta transition-colors"
        :class="tab === t.key ? 'border-accent font-medium text-fg-strong' : 'border-transparent text-fg-muted hover:text-fg-strong'"
        @click="tab = t.key"
      >
        <component :is="t.icon" :size="14" />
        {{ t.label }}
      </button>
      <button type="button" class="toolbar-btn ml-auto xl:hidden" aria-label="Close details" @click="emit('close')">
        <PhX :size="16" />
      </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto p-3">
      <p v-if="!live" class="rounded-lg bg-bg-sunken px-3 py-2.5 text-meta text-fg-muted">{{ unavailable }}</p>

      <!-- Customer -->
      <template v-else-if="tab === 'customer'">
        <p v-if="!thread" class="text-meta text-fg-faint">Pick a conversation.</p>
        <p v-else-if="contextError" class="text-meta text-danger">Couldn’t load this customer’s details.</p>
        <p v-else-if="!context" class="text-meta text-fg-faint">Loading…</p>
        <div v-else class="flex flex-col gap-4">
          <section>
            <p class="text-ui font-medium text-fg-strong">{{ context.contact.name }}</p>
            <p class="text-meta text-fg-faint">{{ context.contact.phone }} · first message {{ formatRelative(context.contact.firstContactAt) }}</p>
            <div v-if="context.customer" class="mt-2 rounded-lg bg-bg-sunken px-3 py-2 text-meta text-fg-muted">
              <NuxtLink :to="`/customers/${context.customer.id}`" class="font-medium text-fg-strong underline-offset-4 hover:underline">Customer account</NuxtLink>
              <p>{{ context.customer.email }}<template v-if="context.customer.preferredCurrency"> · prefers {{ context.customer.preferredCurrency }}</template></p>
            </div>
            <p v-else class="mt-2 text-meta text-fg-faint">No customer account with this number. Orders placed as a guest with it still show below.</p>
          </section>

          <section>
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">Orders</h3>
            <p v-if="!context.orders.length" class="text-meta text-fg-faint">No orders with this number.</p>
            <ul v-else class="flex flex-col gap-2">
              <li v-for="o in context.orders" :key="o.reference" class="rounded-lg border border-border px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                  <NuxtLink :to="`/orders/${o.reference}`" class="font-mono text-meta text-fg-strong underline-offset-4 hover:underline">{{ o.reference }}</NuxtLink>
                  <UiStatusBadge :status="o.status" />
                </div>
                <p class="mt-1 text-meta text-fg-muted">{{ o.items.join(', ') }}</p>
                <p class="mt-0.5 text-meta text-fg-faint">
                  {{ formatMoney(o.total) }} · {{ formatRelative(o.placedAt) }}
                  <template v-if="o.channel === 'whatsapp'"> · WhatsApp</template>
                  <template v-if="o.isLinked"> · linked to this chat</template>
                </p>
                <div class="mt-1.5 flex flex-wrap gap-3">
                  <button type="button" class="flex items-center gap-1 text-meta text-accent hover:underline" @click="emit('insert', say.order(o))">
                    <PhArrowBendDownLeft :size="13" /> Insert status
                  </button>
                  <template v-if="o.channel === 'whatsapp' && o.status === 'pending' && can('orders.create')">
                    <button v-if="can('inbox.reply')" type="button" class="text-meta text-fg-muted hover:underline" @click="emit('resend-link', o.reference)">Resend link</button>
                    <button type="button" class="text-meta text-danger hover:underline" @click="emit('cancel-order', o.reference)">Cancel</button>
                  </template>
                </div>
              </li>
            </ul>
          </section>

          <section>
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">Bookings</h3>
            <p v-if="!context.bookings.length" class="text-meta text-fg-faint">No bookings with this number.</p>
            <ul v-else class="flex flex-col gap-2">
              <li v-for="b in context.bookings" :key="b.id" class="flex items-start justify-between gap-2 rounded-lg border border-border px-3 py-2">
                <div class="min-w-0">
                  <p class="text-meta text-fg-strong">{{ b.experience ?? (b.type === 'diy_order' ? 'DIY / custom order' : 'Workshop') }}</p>
                  <p class="text-meta text-fg-faint">
                    <template v-if="b.date">{{ formatDate(b.date) }}<template v-if="b.slot">, {{ b.slot }}</template></template>
                    <template v-else>Submitted {{ formatRelative(b.createdAt) }}</template>
                    <template v-if="b.attendees"> · {{ b.attendees }} people</template>
                  </p>
                </div>
                <UiStatusBadge :status="b.status" />
              </li>
            </ul>
          </section>
        </div>
      </template>

      <!-- Stock -->
      <template v-else-if="tab === 'stock'">
        <div class="relative mb-3">
          <PhMagnifyingGlass :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-fg-faint" />
          <input v-model="query" type="search" placeholder="Search a product or SKU…" aria-label="Search stock" class="field min-h-[38px] pl-9">
        </div>
        <p v-if="stockLoading && !products.length" class="text-meta text-fg-faint">Searching…</p>
        <p v-else-if="!products.length" class="text-meta text-fg-faint">No products match “{{ query }}”.</p>
        <ul class="flex flex-col gap-2">
          <li v-for="p in products" :key="p.id" class="rounded-lg border border-border">
            <button type="button" class="flex w-full items-center gap-2.5 px-2.5 py-2 text-left" @click="expanded = expanded === p.id ? null : p.id">
              <img v-if="p.image" :src="p.image" alt="" class="size-10 shrink-0 rounded-md bg-bg-sunken object-cover">
              <span class="min-w-0 flex-1">
                <span class="block truncate text-meta font-medium text-fg-strong">{{ p.name }}</span>
                <span class="block text-meta text-fg-faint">
                  {{ formatMoney(p.price) }}<template v-if="p.priceUsd"> · {{ formatMoney(p.priceUsd) }}</template>
                  <template v-if="!p.isActive"> · not on sale</template>
                </span>
              </span>
              <UiBadge :tone="stockTone(p.sellableTotal)" size="sm">{{ p.sellableTotal }} left</UiBadge>
            </button>

            <div v-if="expanded === p.id" class="border-t border-border px-2.5 py-2">
              <p v-if="!p.isActive" class="mb-2 flex items-start gap-1.5 text-meta text-warning">
                <PhWarningCircle :size="13" class="mt-0.5 shrink-0" />
                This product is switched off on the storefront.
              </p>
              <ul class="flex flex-col">
                <li v-for="v in p.variants" :key="v.inventoryItemId" class="flex items-center justify-between gap-2 py-1">
                  <span class="min-w-0 text-meta text-fg">
                    {{ v.label }}
                    <span class="text-fg-faint">
                      · {{ v.sellable }} available<template v-if="v.held"> ({{ v.held }} held)</template>
                    </span>
                  </span>
                  <span class="flex shrink-0 items-center">
                    <button
                      v-if="can('orders.create') && p.isActive && v.sellable > 0"
                      type="button" class="toolbar-btn !size-7" :aria-label="`Add ${v.label} to an order`"
                      @click="addToOrder(p, v)"
                    >
                      <PhShoppingCartSimple :size="14" />
                    </button>
                    <button type="button" class="toolbar-btn !size-7" :aria-label="`Insert ${v.label} availability into the reply`" @click="emit('insert', say.variant(p, v))">
                      <PhArrowBendDownLeft :size="14" />
                    </button>
                  </span>
                </li>
              </ul>
              <div class="mt-2 flex flex-wrap gap-3">
                <button type="button" class="flex items-center gap-1 text-meta text-accent hover:underline" @click="emit('insert', say.product(p))">
                  <PhArrowBendDownLeft :size="13" /> Insert price and link
                </button>
                <a :href="p.url" target="_blank" rel="noopener" class="flex items-center gap-1 text-meta text-fg-muted hover:underline">
                  <PhArrowSquareOut :size="13" /> View in shop
                </a>
              </div>
            </div>
          </li>
        </ul>
      </template>

      <!-- Events -->
      <template v-else-if="tab === 'events'">
        <p v-if="!events" class="text-meta text-fg-faint">Loading…</p>
        <div v-else class="flex flex-col gap-4">
          <section>
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">Next 60 days</h3>
            <p v-if="!events.sessions.length" class="text-meta text-fg-faint">No sessions scheduled.</p>
            <ul v-else class="flex flex-col gap-2">
              <li v-for="s in events.sessions" :key="s.id" class="flex items-start justify-between gap-2 rounded-lg border border-border px-3 py-2">
                <div class="min-w-0">
                  <p class="text-meta font-medium text-fg-strong">{{ s.experience }}</p>
                  <p class="text-meta text-fg-faint">{{ formatDate(s.date, { weekday: 'short', day: 'numeric', month: 'short' }) }}, {{ s.slot }}</p>
                  <p class="mt-0.5 text-meta" :class="s.remaining ? 'text-fg-muted' : 'text-danger'">
                    {{ s.remaining ? `${s.remaining} of ${s.capacity} places left` : 'Full' }}<template v-if="s.waitlisted"> · {{ s.waitlisted }} waiting</template>
                  </p>
                </div>
                <button type="button" class="toolbar-btn !size-7 shrink-0" aria-label="Insert into the reply" @click="emit('insert', say.session(s))">
                  <PhArrowBendDownLeft :size="14" />
                </button>
              </li>
            </ul>
          </section>

          <section v-if="events.byAppointment.length">
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">By appointment</h3>
            <ul class="flex flex-col gap-2">
              <li v-for="t in events.byAppointment" :key="t.name" class="flex items-start justify-between gap-2 rounded-lg border border-border px-3 py-2">
                <div class="min-w-0">
                  <p class="text-meta font-medium text-fg-strong">{{ t.name }}</p>
                  <p class="text-meta text-fg-faint">{{ [t.duration, t.capacity ? `up to ${t.capacity}` : null].filter(Boolean).join(' · ') }}</p>
                </div>
                <button type="button" class="toolbar-btn !size-7 shrink-0" aria-label="Insert into the reply" @click="emit('insert', say.appointment(t))">
                  <PhArrowBendDownLeft :size="14" />
                </button>
              </li>
            </ul>
          </section>

          <section v-if="events.posts.length">
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">News &amp; Events</h3>
            <ul class="flex flex-col gap-1.5">
              <li v-for="p in events.posts" :key="p.url" class="flex items-start justify-between gap-2">
                <a :href="p.url" target="_blank" rel="noopener" class="min-w-0 text-meta text-fg underline-offset-4 hover:underline">{{ p.title }}</a>
                <button type="button" class="toolbar-btn !size-7 shrink-0" aria-label="Insert link into the reply" @click="emit('insert', `${p.title}: ${p.url}`)">
                  <PhArrowBendDownLeft :size="14" />
                </button>
              </li>
            </ul>
          </section>
        </div>
      </template>

      <!-- Info -->
      <template v-else>
        <p v-if="!info" class="text-meta text-fg-faint">Loading…</p>
        <div v-else class="flex flex-col gap-4">
          <p class="text-meta text-fg-faint">Quoted word for word from the brand guidelines, so a chat never promises something the website doesn’t.</p>

          <section v-if="info.businessHours">
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">Hours</h3>
            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-meta text-fg hover:bg-bg-sunken" @click="emit('insert', `Our team is available ${info.businessHours}.`)">
              {{ info.businessHours }}
            </button>
          </section>

          <section v-for="group in [
            { title: 'Delivery', lines: info.delivery },
            { title: 'Returns & exchanges', lines: info.returns },
          ]" :key="group.title">
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">{{ group.title }}</h3>
            <ul class="flex flex-col">
              <li v-for="l in group.lines" :key="l.label">
                <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left hover:bg-bg-sunken" @click="emit('insert', l.text)">
                  <span class="block text-meta font-medium text-fg-strong">{{ l.label }} <span class="font-normal text-fg-faint">{{ l.section }}</span></span>
                  <span class="block text-meta text-fg-muted">{{ l.text }}</span>
                </button>
              </li>
            </ul>
          </section>

          <section>
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">Delivery rates</h3>
            <ul class="flex flex-col text-meta">
              <li class="flex justify-between gap-2 px-2 py-1"><span class="text-fg">Accra</span><span class="text-fg-muted">{{ formatMoney(info.rates.accra) }}</span></li>
              <li v-for="r in info.rates.regions" :key="r.region" class="flex justify-between gap-2 px-2 py-1">
                <span class="text-fg">{{ r.region }}</span><span class="text-fg-muted">{{ formatMoney(r.rate) }}</span>
              </li>
            </ul>
            <p class="mt-1 px-2 text-meta text-fg-faint">{{ info.rates.internationalNote }}</p>
          </section>

          <section v-if="info.turnaround.length">
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">Production time</h3>
            <ul class="flex flex-col">
              <li v-for="t in info.turnaround" :key="t.label">
                <button type="button" class="flex w-full justify-between gap-2 rounded-lg px-2 py-1.5 text-left text-meta hover:bg-bg-sunken" @click="emit('insert', `${t.label}: ${t.estimate}.`)">
                  <span class="text-fg">{{ t.label }}</span><span class="shrink-0 text-fg-muted">{{ t.estimate }}</span>
                </button>
              </li>
            </ul>
          </section>

          <section>
            <h3 class="mb-1.5 text-micro uppercase tracking-wide text-fg-faint">Payment <span class="normal-case">§13</span></h3>
            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-meta text-fg-muted hover:bg-bg-sunken" @click="emit('insert', `You can pay by ${info.paymentMethods.join(', ')}, all through Paystack.`)">
              {{ info.paymentMethods.join(' · ') }}
            </button>
          </section>
        </div>
      </template>
    </div>
  </aside>
</template>
