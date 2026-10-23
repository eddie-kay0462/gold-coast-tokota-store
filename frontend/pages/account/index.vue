<script setup lang="ts">
import type { ApiOrder } from '~/utils/orders'
import { whatsappMessage } from '~/utils/whatsapp'
import { isValidEmail } from '~/utils/validators'

/**
 * The account hub: the destination of the header's person icon.
 *
 * Signed in: a short overview with the latest orders. Signed out: ways in, and
 * the guest order lookup. Guest checkout is the spec's default (README
 * Feature 4, "no forced account creation"), so many people arriving here have
 * an order and no account.
 */
const { isAuthenticated, user, restored } = useAuth()
const api = useApi()

const recent = ref<ApiOrder[]>([])
const recentLoading = ref(false)

watch(isAuthenticated, async (signedIn) => {
  if (!signedIn) return
  recentLoading.value = true
  try {
    recent.value = (await api<{ data: ApiOrder[] }>('/orders')).data.slice(0, 3)
  } catch {
    recent.value = []
  } finally {
    recentLoading.value = false
  }
}, { immediate: true })

const firstName = computed(() => user.value?.first_name ?? '')

const lookup = reactive({ reference: '', email: '' })
const lookupErrors = reactive<{ reference?: string, email?: string }>({})
const looking = ref(false)
const lookupNotice = ref<string | null>(null)

function validateLookup(): boolean {
  lookupErrors.reference = !lookup.reference.trim() ? 'Enter your order number.' : undefined
  lookupErrors.email = !lookup.email.trim()
    ? 'Enter the email you ordered with.'
    : !isValidEmail(lookup.email)
      ? 'That doesn’t look like a valid email address.'
      : undefined
  return !lookupErrors.reference && !lookupErrors.email
}

async function onLookup() {
  lookupNotice.value = null
  if (!validateLookup()) return

  looking.value = true
  const reference = lookup.reference.trim().toUpperCase()
  try {
    // The email has to match the order's: the number alone isn't treated as
    // proof the order is yours. A wrong number and a wrong email read the
    // same, so this can't be used to test which emails have orders.
    const res = await api<{ data: ApiOrder }>(`/orders/${encodeURIComponent(reference)}`)
    const onOrder = (res.data.shipping_address?.email ?? '').trim().toLowerCase()
    if (onOrder && onOrder === lookup.email.trim().toLowerCase()) {
      await navigateTo(`/order-confirmation/${reference}`)
      return
    }
    lookupNotice.value = 'We couldn’t find an order with that number and email. Check both and try again.'
  } catch (err) {
    lookupNotice.value = (err as { statusCode?: number }).statusCode === 404
      ? 'We couldn’t find an order with that number and email. Check both and try again.'
      : 'We couldn’t check just now. Try again in a moment, or message us with your order number.'
  } finally {
    looking.value = false
  }
}

const helpLinks = [
  { label: 'Help Centre', to: '/help' },
  { label: 'Returns', to: '/help/returns' },
  { label: 'Shipping', to: '/help/shipping' },
  { label: 'Contact us', to: '/contact' },
]

useSeoMeta({
  title: 'Your account | Gold Coast Tokota',
  description: 'Sign in, create an account, or track an order you placed as a guest.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <!-- Until the session is known, show nothing rather than flash the
         signed-out page at someone who is signed in. -->
    <div v-if="!restored" class="page-gutter section-y mx-auto flex w-full max-w-[1190px] flex-col gap-4">
      <CommonSkeletonLoader height="2.5rem" width="40%" />
      <CommonSkeletonLoader height="8rem" width="100%" />
    </div>

    <AccountShell
      v-else-if="isAuthenticated"
      :heading="firstName ? `Hello, ${firstName}` : 'Your account'"
      :description="`Signed in as ${user?.email}`"
    >
      <div class="flex w-full flex-col items-start gap-4">
        <h2 class="w-full text-display-sm text-ink">Recent orders</h2>
        <div v-if="recentLoading" class="flex w-full flex-col gap-3">
          <CommonSkeletonLoader height="4rem" width="100%" />
        </div>
        <AccountOrderList v-else-if="recent.length" :orders="recent" />
        <p v-else class="w-full text-label text-subtle">
          No orders yet. Orders you place while signed in will appear here.
        </p>
        <div class="flex flex-col items-stretch gap-3 pt-2 sm:flex-row sm:items-start">
          <CommonBrandButton to="/account/orders">All orders</CommonBrandButton>
          <CommonBrandButton to="/shop" variant="white">Shop sandals</CommonBrandButton>
        </div>
      </div>
    </AccountShell>

    <section
      v-else
      class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-12"
    >
      <header class="flex w-full flex-col items-start gap-2">
        <h1 class="w-full text-display-section font-normal text-ink">Your account</h1>
        <p class="w-full max-w-[720px] text-label text-subtle">
          Sign in to see your orders, or track an order you placed as a guest.
        </p>
      </header>

      <!-- Two columns split by a hairline rather than two boxed cards, the home
           page's white-and-rules look. -->
      <div class="grid w-full grid-cols-1 border-t border-line md:grid-cols-2">
        <div class="flex min-w-0 flex-col items-start gap-3 py-8 md:border-r md:border-line md:pr-10">
          <h2 class="caps-title w-full">Sign in</h2>
          <p class="w-full flex-1 text-label text-subtle">
            See past orders, save your delivery details and check out faster next time.
          </p>
          <CommonBrandButton to="/account/login" class="mt-2">Sign in</CommonBrandButton>
        </div>

        <div class="flex min-w-0 flex-col items-start gap-3 border-t border-line py-8 md:border-t-0 md:pl-10">
          <h2 class="caps-title w-full">Create an account</h2>
          <p class="w-full flex-1 text-label text-subtle">
            It takes a minute, and you never have to use it. Ordering as a guest works exactly the same.
          </p>
          <CommonBrandButton to="/account/register" class="mt-2">Create account</CommonBrandButton>
        </div>
      </div>

      <div class="flex w-full flex-col items-start gap-4 border-t border-line pt-10">
        <h2 class="w-full text-display-sm text-ink">Track an order</h2>
        <p class="w-full max-w-[720px] text-label text-subtle">
          Ordered as a guest? Enter your order number and the email you used.
        </p>

        <CommonInlineNotice v-if="lookupNotice" variant="warning" title="Order not found">
          {{ lookupNotice }}
        </CommonInlineNotice>

        <form class="flex w-full max-w-[440px] flex-col items-start gap-5" novalidate @submit.prevent="onLookup">
          <FormsFormField
            v-model="lookup.reference"
            label="Order number" name="order-reference" required :error="lookupErrors.reference"
          />
          <FormsFormField
            v-model="lookup.email"
            label="Email" name="order-email" type="email" autocomplete="email"
            required :error="lookupErrors.email"
          />
          <CommonBrandButton full type="submit" :disabled="looking">
            {{ looking ? 'Looking…' : 'Track order' }}
          </CommonBrandButton>
        </form>
      </div>

      <div class="flex w-full flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm text-ink">Need something else?</h2>
        <!-- The home page's "Shop all" link style: tracked capitals, underlined. -->
        <ul class="flex flex-wrap items-center gap-x-6">
          <li v-for="link in helpLinks" :key="link.to">
            <NuxtLink
              :to="link.to"
              class="caps-label -my-3 flex min-h-[44px] items-center py-3 text-ink underline underline-offset-4 hover:no-underline"
            >{{ link.label }}</NuxtLink>
          </li>
          <li>
            <CommonWhatsAppLink source="account" variant="quiet" :message="whatsappMessage.general()">
              WhatsApp
            </CommonWhatsAppLink>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>
