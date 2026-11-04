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
  <AuthShell>
    <h1 class="text-2xl font-semibold text-fg-strong">Forgot password</h1>
    <template v-if="sent">
      <p class="mt-2 text-sm text-fg-muted">
        If {{ email }} has a dashboard account, a link to set a new password is on its way. It
        works for an hour.
      </p>
    </template>
    <template v-else>
      <p class="mt-2 text-sm text-fg-muted">Enter the email you sign in with and we’ll send a link to set a new password.</p>
      <form class="mt-8 flex flex-col gap-5" novalidate @submit.prevent="submit">
        <UiField v-model="email" label="Email address" type="email" required autocomplete="username" placeholder="you@goldcoasttokota.store" />
        <p v-if="error" class="text-meta text-danger" role="alert">{{ error }}</p>
        <UiButton type="submit" block :loading="sending" :disabled="!email.trim()">Send the link</UiButton>
      </form>
    </template>
    <NuxtLink to="/login" class="mt-6 inline-flex items-center gap-1.5 text-sm text-fg-muted underline-offset-4 hover:text-fg-strong hover:underline">
      <PhArrowLeft :size="14" />Back to sign in
    </NuxtLink>
  </AuthShell>
</template>
