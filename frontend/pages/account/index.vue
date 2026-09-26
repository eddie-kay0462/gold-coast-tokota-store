<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
import { isValidEmail } from '~/utils/validators'

/**
 * The account hub — the destination of the header's person icon.
 *
 * Nobody can be signed in yet (see `composables/useAuth.ts`), so this renders
 * the signed-out state permanently rather than a dashboard of invented data.
 * The genuinely useful thing here today is the guest order lookup: guest
 * checkout is the spec's default (README Feature 4, "no forced account
 * creation"), so most people arriving here have an order and no account.
 */
const { isAuthenticated, user } = useAuth()

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
  // Inert for the same reason sign-in is: `GET /api/v1/orders/{id}` has not
  // been built yet (README Feature 4).
  await new Promise((resolve) => setTimeout(resolve, 600))
  looking.value = false
  lookupNotice.value =
    'Order lookup isn’t available yet — the orders endpoint hasn’t been built on the API side. ' +
    'Message us with your order number and we’ll check it for you.'
}

useSeoMeta({
  title: 'Your account — Gold Coast Tokota',
  description: 'Sign in, create an account, or track an order you placed as a guest.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <!-- Signed-in branch. Unreachable today, but three lines keeps the page
         honest about its own store rather than asserting nobody can ever be
         signed in. -->
    <AccountShell
      v-if="isAuthenticated"
      heading="Your account"
      :description="`Signed in as ${user?.email}`"
    >
      <CommonBrandButton to="/account/orders">View orders</CommonBrandButton>
    </AccountShell>

    <section
      v-else
      class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10"
    >
      <header class="flex w-full flex-col items-start gap-2">
        <h1 class="w-full text-display-section font-normal text-black">Your account</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Sign in to see your orders, or track an order you placed as a guest.
        </p>
      </header>

      <div class="grid w-full grid-cols-1 gap-5 md:grid-cols-2">
        <div class="flex min-w-0 flex-col items-start gap-3 border border-line p-6">
          <h2 class="w-full text-display-sm font-normal text-black">Sign in</h2>
          <p class="w-full flex-1 text-caption text-muted">
            See past orders, save your delivery details and check out faster next time.
          </p>
          <CommonBrandButton to="/account/login" full>Sign in</CommonBrandButton>
        </div>

        <div class="flex min-w-0 flex-col items-start gap-3 border border-line p-6">
          <h2 class="w-full text-display-sm font-normal text-black">Create an account</h2>
          <p class="w-full flex-1 text-caption text-muted">
            It takes a minute, and you never have to use it — ordering as a guest works exactly the same.
          </p>
          <CommonBrandButton to="/account/register" variant="white" full>Create account</CommonBrandButton>
        </div>
      </div>

      <div class="flex w-full flex-col items-start gap-4 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Track an order</h2>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Ordered as a guest? Enter your order number and the email you used.
        </p>

        <CommonInlineNotice v-if="lookupNotice" variant="warning" title="Order lookup isn’t available yet">
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
        <h2 class="w-full text-display-sm font-normal text-black">Need something else?</h2>
        <ul class="flex flex-wrap items-center gap-x-6">
          <li><NuxtLink to="/help" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-muted underline hover:text-graphite">Help Centre</NuxtLink></li>
          <li><NuxtLink to="/help/returns" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-muted underline hover:text-graphite">Returns</NuxtLink></li>
          <li><NuxtLink to="/help/shipping" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-muted underline hover:text-graphite">Shipping</NuxtLink></li>
          <li><NuxtLink to="/contact" class="-my-3 flex min-h-[44px] items-center py-3 text-label text-muted underline hover:text-graphite">Contact us</NuxtLink></li>
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
