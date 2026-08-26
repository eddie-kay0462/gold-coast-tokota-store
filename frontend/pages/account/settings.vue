<script setup lang="ts">
import { AUTH_DISABLED_NOTICE, AUTH_ENABLED } from '~/composables/useAuth'
import { CURRENCIES } from '~/utils/constants'

/**
 * Account settings.
 *
 * The fields are real and mirror the `customers` table, but every control is
 * disabled: there is no session to load them from and no endpoint to save them
 * to. Rendering them disabled rather than hiding them means the design can be
 * reviewed now without faking a signed-in user.
 *
 * Gets `definePageMeta({ middleware: 'auth' })` when auth lands — see
 * `pages/account/orders.vue` for why it has none today.
 */

// Empty, and stays empty — there is nothing to hydrate these from.
const profile = reactive({ name: '', email: '', phone: '', currency: 'GHS' })
const address = reactive({ line1: '', city: '', region: '', country: '' })
const password = reactive({ current: '', next: '', confirm: '' })

const currencyOptions = CURRENCIES.map((value) => ({
  value,
  label: value === 'GHS' ? 'Ghana cedi (₵)' : 'US dollar ($)',
}))

useSeoMeta({
  title: 'Account settings — Gold Coast Tokota',
  description: 'Manage your Gold Coast Tokota profile, address and preferences.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <AccountShell heading="Settings" description="Your profile, delivery address and preferences.">
      <CommonInlineNotice v-if="!AUTH_ENABLED" variant="warning" title="Sign-in isn’t enabled yet">
        {{ AUTH_DISABLED_NOTICE }} These fields are shown for reference and can’t be saved.
      </CommonInlineNotice>

      <fieldset disabled class="flex w-full flex-col items-start gap-8">
        <section class="flex w-full max-w-[520px] flex-col items-start gap-5">
          <h2 class="w-full text-display-sm font-normal text-black">Profile</h2>
          <FormsFormField v-model="profile.name" label="Name" name="profile-name" disabled />
          <FormsFormField v-model="profile.email" label="Email" name="profile-email" type="email" disabled />
          <FormsFormField v-model="profile.phone" label="Phone" name="profile-phone" type="tel" disabled />
        </section>

        <section class="flex w-full max-w-[520px] flex-col items-start gap-5 border-t border-line pt-8">
          <h2 class="w-full text-display-sm font-normal text-black">Delivery address</h2>
          <FormsFormField v-model="address.line1" label="Street address" name="address-line1" disabled />
          <FormsFormField v-model="address.city" label="City" name="address-city" disabled />
          <FormsFormField v-model="address.region" label="Region" name="address-region" disabled />
          <FormsFormField v-model="address.country" label="Country" name="address-country" disabled />
        </section>

        <section class="flex w-full max-w-[520px] flex-col items-start gap-5 border-t border-line pt-8">
          <h2 class="w-full text-display-sm font-normal text-black">Preferences</h2>
          <FormsFormField
            v-model="profile.currency"
            label="Preferred currency"
            name="profile-currency"
            type="select"
            :options="currencyOptions"
            hint="Prices are always set in cedis; this changes what you see by default."
            disabled
          />
        </section>

        <section class="flex w-full max-w-[520px] flex-col items-start gap-5 border-t border-line pt-8">
          <h2 class="w-full text-display-sm font-normal text-black">Password</h2>
          <FormsFormField v-model="password.current" label="Current password" name="password-current" type="password" disabled />
          <FormsFormField v-model="password.next" label="New password" name="password-next" type="password" disabled />
          <FormsFormField v-model="password.confirm" label="Confirm new password" name="password-confirm" type="password" disabled />
        </section>

        <CommonBrandButton disabled>Save changes</CommonBrandButton>
      </fieldset>
    </AccountShell>
  </div>
</template>
