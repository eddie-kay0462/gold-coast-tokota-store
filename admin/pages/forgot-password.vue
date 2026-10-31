<script setup lang="ts">
import { PhArrowLeft } from '@phosphor-icons/vue'

/**
 * `POST /admin/forgot-password`. The answer is the same whether or not the
 * address has an account, so this page can't be used to find out who works
 * here. The link in the email opens /reset-password.
 */
definePageMeta({ layout: false })
useHead({ title: 'Forgot password' })

const route = useRoute()
const config = useRuntimeConfig()
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const sending = ref(false)
const sent = ref(false)
const error = ref<string | null>(null)

async function submit() {
  if (!email.value.trim()) return
  sending.value = true
  error.value = null
  try {
    await $fetch(`${apiOrigin(config.public.apiBase as string)}/sanctum/csrf-cookie`, { credentials: 'include', retry: 0 })
    await $fetch(`${config.public.apiBase}/admin/forgot-password`, {
      method: 'POST',
      body: { email: email.value.trim() },
      credentials: 'include',
      headers: { Accept: 'application/json', ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken()! } : {}) },
      retry: 0,
    })
    sent.value = true
  } catch (err) {
    const e = err as { statusCode?: number }
    error.value = e.statusCode === 429
      ? 'Too many tries. Wait a minute and try again.'
      : failureMessage(err, 'That didn’t go through. Try again in a moment.')
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-bg px-5 py-10">
    <div class="w-full max-w-sm">
      <h1 class="text-title font-medium text-fg-strong">Forgot password</h1>
      <template v-if="sent">
        <p class="mt-3 text-ui text-fg-muted">
          If {{ email }} has a dashboard account, a link to set a new password is on its way. It
          works for an hour.
        </p>
      </template>
      <form v-else class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="submit">
        <p class="text-ui text-fg-muted">Enter the email you sign in with and we’ll send a link to set a new password.</p>
        <UiField v-model="email" label="Email address" type="email" required autocomplete="username" />
        <p v-if="error" class="text-meta text-danger" role="alert">{{ error }}</p>
        <UiButton type="submit" block :loading="sending" :disabled="!email.trim()">Send the link</UiButton>
      </form>
      <NuxtLink to="/login" class="mt-6 inline-flex items-center gap-1.5 text-ui text-accent-text underline-offset-4 hover:underline">
        <PhArrowLeft :size="14" />Back to sign in
      </NuxtLink>
    </div>
  </div>
</template>
