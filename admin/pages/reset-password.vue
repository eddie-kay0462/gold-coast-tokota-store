<script setup lang="ts">
/**
 * Where the reset email lands: `?token=…&email=…`, then
 * `POST /admin/reset-password`. The link lasts an hour and works once.
 *
 * A new member's invitation lands here too, with `invite=1`: the same form,
 * worded as choosing a first password, and the link lasts three days.
 */
definePageMeta({ layout: false })
const isInvite = useRoute().query.invite === '1'
useHead({ title: isInvite ? 'Choose your password' : 'Set a new password' })

const route = useRoute()
const config = useRuntimeConfig()
const token = typeof route.query.token === 'string' ? route.query.token : ''
const email = typeof route.query.email === 'string' ? route.query.email : ''
const password = ref('')
const confirm = ref('')
const saving = ref(false)
const done = ref(false)
const error = ref<string | null>(token && email ? null : 'This link is incomplete. Open it again from the email.')

async function submit() {
  if (!password.value || password.value !== confirm.value) return
  saving.value = true
  error.value = null
  try {
    await $fetch(`${apiOrigin(config.public.apiBase as string)}/sanctum/csrf-cookie`, { credentials: 'include', retry: 0 })
    await $fetch(`${config.public.apiBase}/admin/reset-password`, {
      method: 'POST',
      body: { token, email, password: password.value, password_confirmation: confirm.value, invite: isInvite },
      credentials: 'include',
      headers: { Accept: 'application/json', ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken()! } : {}) },
      retry: 0,
    })
    done.value = true
  } catch (err) {
    error.value = failureMessage(err, 'That didn’t go through. Try again in a moment.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AuthShell>
    <h1 class="text-2xl font-semibold text-fg-strong">{{ isInvite ? 'Choose your password' : 'Set a new password' }}</h1>
    <template v-if="done">
      <p class="mt-2 text-sm text-fg-muted">
        {{ isInvite ? 'Your account is ready. Sign in with your email and this password.' : 'Your password has been changed.' }}
      </p>
      <UiButton to="/login" block class="auth-pill mt-8">Sign in</UiButton>
    </template>
    <template v-else>
      <p v-if="email" class="mt-2 text-sm text-fg-muted">
        {{ isInvite ? `Welcome to the Gold Coast Tokota dashboard. You’ll sign in as ${email}.` : `For ${email}.` }}
      </p>
      <form class="mt-8 flex flex-col gap-5" novalidate @submit.prevent="submit">
        <UiField v-model="password" label="New password" type="password" required autocomplete="new-password" hint="At least 8 characters." />
        <UiField v-model="confirm" label="Repeat it" type="password" required autocomplete="new-password" />
        <p v-if="confirm && password !== confirm" class="text-meta text-fg-muted">The two don’t match yet.</p>
        <p v-if="error" class="text-meta text-danger" role="alert">{{ error }}</p>
        <UiButton type="submit" block :loading="saving" :disabled="!token || !password || password !== confirm">
          Save new password
        </UiButton>
        <NuxtLink v-if="!isInvite" to="/forgot-password" class="text-sm text-fg-muted underline-offset-4 hover:text-fg-strong hover:underline">
          Ask for a new link
        </NuxtLink>
        <p v-else class="text-meta text-fg-faint">If the link has run out, ask whoever invited you to send it again.</p>
      </form>
    </template>
  </AuthShell>
</template>
