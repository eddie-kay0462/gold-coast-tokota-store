<script setup lang="ts">
import { isValidEmail } from '~/utils/validators'
import { DEFAULT_PHONE_COUNTRY, nationalNumberError, toE164 } from '~/utils/phone'
import { CURRENCIES } from '~/utils/constants'

/**
 * Customer sign-up (`POST /register`). The fields mirror the `customers` table
 * (name, email, phone, preferred_currency, password). The API signs the new
 * customer straight in, so they land on their account, or back where they
 * came from (`?redirect=`).
 */
const { register } = useAuth()
const route = useRoute()
const redirectTo = computed(() => {
  const r = String(route.query.redirect ?? '')
  return r.startsWith('/') && !r.startsWith('//') ? r : '/account'
})

const form = reactive({
  firstName: '',
  lastName: '',
  email: '',
  phoneCountry: DEFAULT_PHONE_COUNTRY,
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
  errors.firstName = !form.firstName.trim() ? 'Enter your first name.' : undefined
  errors.lastName = !form.lastName.trim() ? 'Enter your last name.' : undefined

  errors.email = !form.email.trim()
    ? 'Enter your email address.'
    : !isValidEmail(form.email)
      ? 'That doesn’t look like a valid email address.'
      : undefined

  // Phone is optional here (`customers.phone` is nullable) but checked when
  // given, against the chosen country: exactly 9 digits after +233 for
  // Ghana, a plausible length elsewhere (utils/phone.ts).
  errors.phone = form.phone.trim() ? nationalNumberError(form.phoneCountry, form.phone) : undefined

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
  const result = await register({
    first_name: form.firstName.trim(),
    last_name: form.lastName.trim(),
    email: form.email.trim(),
    // Saved as E.164, e.g. +233241234567.
    phone: toE164(form.phoneCountry, form.phone),
    preferred_currency: form.currency as 'GHS' | 'USD',
    password: form.password,
    password_confirmation: form.confirm,
  })
  submitting.value = false

  if (result.ok) {
    await navigateTo(redirectTo.value)
    return
  }

  // The API's field names, mapped onto this form's.
  errors.firstName = result.errors.first_name
  errors.lastName = result.errors.last_name
  errors.email = result.errors.email
  errors.phone = result.errors.phone
  errors.password = result.errors.password
  notice.value = Object.keys(result.errors).length ? null : result.notice
}

useSeoMeta({
  title: 'Create an account | Gold Coast Tokota',
  description: 'Create a Gold Coast Tokota account to track orders and check out faster.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <AccountAuthShell heading="Create an account">
    <template #intro>
      Save your delivery details and keep every order in one place.
    </template>

    <CommonInlineNotice v-if="notice" variant="warning" title="We couldn’t create your account">
      {{ notice }}
    </CommonInlineNotice>

    <form class="flex w-full flex-col items-start gap-5" novalidate @submit.prevent="onSubmit">
      <div class="grid w-full grid-cols-1 gap-5 sm:grid-cols-2">
        <FormsFormField
          v-model="form.firstName"
          label="First name" name="first_name" autocomplete="given-name" required :error="errors.firstName"
        />
        <FormsFormField
          v-model="form.lastName"
          label="Last name" name="last_name" autocomplete="family-name" required :error="errors.lastName"
        />
      </div>
      <FormsFormField
        v-model="form.email"
        label="Email" name="email" type="email" autocomplete="email" required :error="errors.email"
      />
      <FormsPhoneField
        v-model="form.phone"
        v-model:country="form.phoneCountry"
        label="Phone" name="phone"
        hint="Optional. We use it for delivery updates only."
        :error="errors.phone"
      />
      <FormsFormField
        v-model="form.currency"
        label="Preferred currency" name="currency" type="select" :options="currencyOptions"
      />
      <FormsPasswordField
        v-model="form.password"
        label="Password" name="password" autocomplete="new-password"
        hint="At least 8 characters." required :error="errors.password"
      />
      <FormsPasswordField
        v-model="form.confirm"
        label="Confirm password" name="confirm" autocomplete="new-password"
        required :error="errors.confirm"
      />

      <CommonBrandButton full type="submit" :disabled="submitting">
        {{ submitting ? 'Creating account…' : 'Create account' }}
      </CommonBrandButton>
    </form>

    <p class="w-full text-caption text-muted">
      By creating an account you agree to our
      <NuxtLink to="/legal/terms" class="text-ink underline underline-offset-4 hover:no-underline">Terms of Service</NuxtLink>
      and
      <NuxtLink to="/legal/privacy" class="text-ink underline underline-offset-4 hover:no-underline">Privacy Policy</NuxtLink>.
    </p>
  </AccountAuthShell>
</template>
