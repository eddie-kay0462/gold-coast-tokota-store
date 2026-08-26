<script setup lang="ts">
import { isValidEmail, isValidGhanaPhone } from '~/utils/validators'
import { CURRENCIES } from '~/utils/constants'

/**
 * Customer sign-up — BUILT BUT DELIBERATELY INACTIVE.
 *
 * The fields deliberately mirror the `customers` table (name, email, phone,
 * preferred_currency, password) so that wiring this to a real
 * POST /register is a straight map rather than a redesign.
 */
const { register } = useAuth()

const form = reactive({
  name: '',
  email: '',
  phone: '',
  currency: 'GHS',
  password: '',
  confirm: '',
})
const errors = reactive<Record<string, string | undefined>>({})
const submitting = ref(false)
const notice = ref<string | null>(null)

const currencyOptions = CURRENCIES.map((value) => ({
  value,
  label: value === 'GHS' ? 'Ghana cedi (₵)' : 'US dollar ($)',
}))

function validate(): boolean {
  errors.name = !form.name.trim() ? 'Enter your name.' : undefined

  errors.email = !form.email.trim()
    ? 'Enter your email address.'
    : !isValidEmail(form.email)
      ? 'That doesn’t look like a valid email address.'
      : undefined

  // Phone is optional here — `customers.phone` is nullable — but validated
  // when given. The Ghana pattern only applies to a Ghana number; an
  // international customer must not be rejected by it.
  errors.phone = !form.phone.trim()
    ? undefined
    : /^0|^\+233/.test(form.phone.trim()) && !isValidGhanaPhone(form.phone.trim())
      ? 'Enter a Ghana number as 0XXXXXXXXX or +233XXXXXXXXX.'
      : !/^\+?[0-9\s-]{7,}$/.test(form.phone.trim())
        ? 'That doesn’t look like a valid phone number.'
        : undefined

  errors.password = !form.password
    ? 'Choose a password.'
    : form.password.length < 8
      ? 'Use at least 8 characters.'
      : undefined

  errors.confirm = form.confirm !== form.password ? 'Passwords don’t match.' : undefined

  return !Object.values(errors).some(Boolean)
}

async function onSubmit() {
  notice.value = null
  if (!validate()) return

  submitting.value = true
  const result = await register({ name: form.name, email: form.email, password: form.password })
  submitting.value = false
  notice.value = result.notice
}

useSeoMeta({
  title: 'Create an account — Gold Coast Tokota',
  description: 'Create a Gold Coast Tokota account to track orders and check out faster.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[calc(27.5rem+120px)] flex-col items-start gap-6">
      <header class="flex w-full flex-col items-start gap-2">
        <h1 class="w-full text-display-section font-normal text-black">Create an account</h1>
        <p class="w-full text-body text-graphite">
          Already have one?
          <NuxtLink to="/account/login" class="underline hover:no-underline">Sign in</NuxtLink>.
        </p>
      </header>

      <CommonInlineNotice v-if="notice" variant="warning" title="Accounts aren’t enabled yet">
        {{ notice }}
      </CommonInlineNotice>

      <form class="flex w-full flex-col items-start gap-5" novalidate @submit.prevent="onSubmit">
        <FormsFormField
          v-model="form.name"
          label="Name" name="name" autocomplete="name" required :error="errors.name"
        />
        <FormsFormField
          v-model="form.email"
          label="Email" name="email" type="email" autocomplete="email" required :error="errors.email"
        />
        <FormsFormField
          v-model="form.phone"
          label="Phone" name="phone" type="tel" autocomplete="tel"
          hint="Optional. We use it for delivery updates only."
          :error="errors.phone"
        />
        <FormsFormField
          v-model="form.currency"
          label="Preferred currency" name="currency" type="select" :options="currencyOptions"
        />
        <FormsFormField
          v-model="form.password"
          label="Password" name="password" type="password" autocomplete="new-password"
          hint="At least 8 characters." required :error="errors.password"
        />
        <FormsFormField
          v-model="form.confirm"
          label="Confirm password" name="confirm" type="password" autocomplete="new-password"
          required :error="errors.confirm"
        />

        <CommonBrandButton full type="submit" :disabled="submitting">
          {{ submitting ? 'Creating account…' : 'Create account' }}
        </CommonBrandButton>
      </form>

      <p class="w-full text-caption text-muted">
        By creating an account you agree to our
        <NuxtLink to="/legal/terms" class="underline hover:no-underline">Terms of Service</NuxtLink>
        and
        <NuxtLink to="/legal/privacy" class="underline hover:no-underline">Privacy Policy</NuxtLink>.
      </p>
    </section>
  </div>
</template>
