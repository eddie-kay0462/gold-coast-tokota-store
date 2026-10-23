<script setup lang="ts">
import { PhEye, PhEyeSlash } from '@phosphor-icons/vue'
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
const showPassword = ref(false)
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

      <div class="flex w-full min-w-0 flex-col gap-1.5">
        <label for="password" class="text-caption font-normal text-graphite">
          Password <span aria-hidden="true">*</span>
        </label>
        <div class="field-group" :class="{ 'border-sale': errors.password }">
          <input
            id="password"
            v-model="form.password"
            name="password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            class="min-h-[44px] w-full min-w-0 flex-1 bg-transparent px-3 py-2.5 text-body text-graphite placeholder:text-muted"
            :aria-invalid="!!errors.password"
            :aria-describedby="errors.password ? 'password-error' : undefined"
          >
          <!-- w-11 and the group's full height: the 44px tap floor applies to
               the button, not the glyph inside it. -->
          <button
            type="button"
            class="flex w-11 shrink-0 items-center justify-center border-l text-subtle transition-colors hover:text-ink focus-visible:bg-surface focus-visible:text-ink"
            :class="errors.password ? 'border-sale' : 'border-line'"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            <component :is="showPassword ? PhEyeSlash : PhEye" :size="18" />
          </button>
        </div>
        <p v-if="errors.password" id="password-error" class="text-caption text-sale">
          {{ errors.password }}
        </p>
      </div>

      <NuxtLink to="/account/forgot-password" class="-my-2 py-2 text-caption text-graphite underline hover:no-underline">
        Forgot your password?
      </NuxtLink>

      <CommonBrandButton full type="submit" :disabled="submitting">
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </CommonBrandButton>
    </form>
  </AccountAuthShell>
</template>
