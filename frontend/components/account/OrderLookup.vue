<script setup lang="ts">
import type { ApiOrder } from '~/utils/orders'
import { isValidEmail } from '~/utils/validators'

/**
 * "Track an order": an order number and the email it was placed with, then
 * the order's status page. On /account for anyone signed out, and for a
 * signed-in customer when an order email's link names an order.
 *
 * Order emails link here as /account?order=GCT-…#track-order, so the number
 * is filled in and the customer only types their email. The email is never
 * in the link, so it stays out of logs and analytics.
 */
const api = useApi()
const route = useRoute()
const lookup = reactive({
  reference: typeof route.query.order === 'string' ? route.query.order.trim().toUpperCase().slice(0, 32) : '',
  email: '',
})
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

</script>

<template>
  <div class="flex w-full scroll-mt-48 flex-col items-start gap-4 border-t border-line pt-10">
    <h2 class="w-full text-display-sm text-ink">Track an order</h2>
    <p class="w-full max-w-[720px] text-label text-subtle">
      Enter your order number and the email you used, and we’ll show you where your order is.
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
</template>
