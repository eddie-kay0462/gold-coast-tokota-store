<script setup lang="ts">
/**
 * Account settings: the signed-in customer's details, from the session
 * (`GET /me`).
 *
 * Read-only for now. The API has no endpoint for editing a profile yet, so
 * the fields show what's on file and say where to go to change it. Delivery
 * addresses aren't stored on the account (`customers` has no address); each
 * order takes its own at checkout. A password is changed through the reset
 * flow, which already works end to end.
 */
definePageMeta({ middleware: 'auth' })

const { user } = useAuth()

const currencyLabel = computed(() =>
  user.value?.preferred_currency === 'USD' ? 'US dollar ($)' : 'Ghana cedi (₵)',
)

useSeoMeta({
  title: 'Account settings | Gold Coast Tokota',
  description: 'Your Gold Coast Tokota profile and preferences.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <AccountShell heading="Settings" description="Your profile and preferences.">
      <CommonInlineNotice title="Editing your details isn’t available yet">
        To change your name, email or phone number, message us and we’ll update it for you.
      </CommonInlineNotice>

      <section class="flex w-full max-w-[520px] flex-col items-start gap-4">
        <h2 class="caps-title w-full">Profile</h2>
        <dl class="flex w-full flex-col gap-3 text-label">
          <div class="flex w-full flex-col gap-0.5">
            <dt class="text-caption text-muted">Name</dt>
            <dd class="text-ink">{{ user?.name }}</dd>
          </div>
          <div class="flex w-full flex-col gap-0.5">
            <dt class="text-caption text-muted">Email</dt>
            <dd class="break-all text-ink">{{ user?.email }}</dd>
          </div>
          <div class="flex w-full flex-col gap-0.5">
            <dt class="text-caption text-muted">Phone</dt>
            <dd class="text-ink">{{ user?.phone || 'Not given' }}</dd>
          </div>
        </dl>
      </section>

      <section class="flex w-full max-w-[520px] flex-col items-start gap-3 border-t border-line pt-8">
        <h2 class="caps-title w-full">Preferences</h2>
        <p class="text-label text-ink">Preferred currency: {{ currencyLabel }}</p>
        <p class="text-caption text-muted">
          Prices are always set in cedis. You can switch the currency you see at any time from the
          menu at the top of the page.
        </p>
      </section>

      <section class="flex w-full max-w-[520px] flex-col items-start gap-3 border-t border-line pt-8">
        <h2 class="caps-title w-full">Delivery address</h2>
        <p class="text-label text-subtle">
          You enter the delivery address for each order at checkout, so a gift can go somewhere
          other than home.
        </p>
      </section>

      <section class="flex w-full max-w-[520px] flex-col items-start gap-3 border-t border-line pt-8">
        <h2 class="caps-title w-full">Password</h2>
        <p class="text-label text-subtle">We’ll email you a link to choose a new one.</p>
        <CommonBrandButton to="/account/forgot-password" variant="white">Change password</CommonBrandButton>
      </section>
    </AccountShell>
  </div>
</template>
