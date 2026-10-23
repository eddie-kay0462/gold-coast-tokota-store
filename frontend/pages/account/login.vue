<script setup lang="ts">
import { isValidEmail } from '~/utils/validators'

/**
 * Customer sign-in (`POST /login`, the `web` guard). Afterwards the customer
 * goes back to the page that sent them here (`?redirect=`), or to /account.
 */
const { signIn } = useAuth()
const route = useRoute()

/** Only an in-site path, so the link can't send someone off to another site. */
const redirectTo = computed(() => {
  const r = String(route.query.redirect ?? '')
  return r.startsWith('/') && !r.startsWith('//') ? r : '/account'
})

const form = reactive({ email: '', password: '' })
const errors = reactive<{ email?: string, password?: string }>({})
const submitting = ref(false)
const notice = ref<string | null>(null)

function validate(): boolean {
  errors.email = !form.email.trim()
    ? 'Enter your email address.'
    : !isValidEmail(form.email)
      ? 'That doesn’t look like a valid email address.'
      : undefined
  errors.password = !form.password ? 'Enter your password.' : undefined
  return !errors.email && !errors.password
}

async function onSubmit() {
  notice.value = null
  if (!validate()) return

  submitting.value = true
  const result = await signIn({ email: form.email.trim(), password: form.password })
  submitting.value = false

  if (result.ok) {
    await navigateTo(redirectTo.value)
    return
  }

  errors.email = result.errors.email
  errors.password = result.errors.password
  // The field shows the API's message; the notice is for anything else.
  notice.value = result.errors.email || result.errors.password ? null : result.notice
}

useSeoMeta({
  title: 'Sign in | Gold Coast Tokota',
  description: 'Sign in to your Gold Coast Tokota account.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <AccountAuthShell heading="Welcome back">
    <template #intro>
      Sign in to see your orders and check out faster.
    </template>

    <CommonInlineNotice v-if="notice" variant="warning" title="We couldn’t sign you in">
      {{ notice }}
    </CommonInlineNotice>

    <form class="flex w-full flex-col items-start gap-5" novalidate @submit.prevent="onSubmit">
      <FormsFormField
        v-model="form.email"
        label="Email"
        name="email"
        type="email"
        autocomplete="email"
        required
        :error="errors.email"
      />

      <FormsPasswordField
        v-model="form.password"
        label="Password" name="password" autocomplete="current-password"
        required :error="errors.password"
      />

      <NuxtLink to="/account/forgot-password" class="-my-2 py-2 text-caption text-graphite underline hover:no-underline">
        Forgot your password?
      </NuxtLink>

      <CommonBrandButton full type="submit" :disabled="submitting">
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </CommonBrandButton>
    </form>
  </AccountAuthShell>
</template>
