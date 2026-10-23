<script setup lang="ts">
import { isValidEmail } from '~/utils/validators'

/**
 * Start a password reset (`POST /forgot-password`). The API emails a link to
 * /account/reset-password. It answers the same whether or not the address has
 * an account, so this page does too: saying "no such account" would tell a
 * stranger who shops here.
 */
const { forgotPassword } = useAuth()

const email = ref('')
const error = ref<string | undefined>()
const submitting = ref(false)
const sent = ref(false)
const notice = ref<string | null>(null)

async function onSubmit() {
  notice.value = null
  error.value = !email.value.trim()
    ? 'Enter your email address.'
    : !isValidEmail(email.value) ? 'That doesn’t look like a valid email address.' : undefined
  if (error.value) return

  submitting.value = true
  const result = await forgotPassword(email.value.trim())
  submitting.value = false

  if (result.ok) {
    sent.value = true
    return
  }
  error.value = result.errors.email
  notice.value = result.errors.email ? null : result.notice
}

useSeoMeta({
  title: 'Reset your password | Gold Coast Tokota',
  description: 'Get a link to reset your Gold Coast Tokota password.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <AccountAuthShell heading="Reset your password">
    <template #intro>
      Enter the email you signed up with and we’ll send you a link to choose a new password.
    </template>

    <CommonInlineNotice v-if="sent" variant="success" title="Check your email">
      If <strong class="font-normal">{{ email.trim() }}</strong> has an account, a reset link is on
      its way. It works for 60 minutes. Nothing there after a few minutes? Check your spam folder.
    </CommonInlineNotice>

    <CommonInlineNotice v-if="notice" variant="warning" title="We couldn’t send the link">
      {{ notice }}
    </CommonInlineNotice>

    <form v-if="!sent" class="flex w-full flex-col items-start gap-5" novalidate @submit.prevent="onSubmit">
      <FormsFormField
        v-model="email"
        label="Email" name="email" type="email" autocomplete="email" required :error="error"
      />
      <CommonBrandButton full type="submit" :disabled="submitting">
        {{ submitting ? 'Sending…' : 'Send reset link' }}
      </CommonBrandButton>
    </form>

    <NuxtLink to="/account/login" class="-my-2 py-2 text-caption text-graphite underline hover:no-underline">
      Back to sign in
    </NuxtLink>
  </AccountAuthShell>
</template>
