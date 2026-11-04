<script setup lang="ts">
import { PhEye, PhEyeSlash, PhWarningCircle } from '@phosphor-icons/vue'

/**
 * Admin sign-in: Sanctum SPA cookie auth against the `admin` guard (see
 * `useAuth().login()`). `middleware/auth.global.ts` sends every signed-out
 * visit here and returns to `?redirect=` afterwards.
 *
 * In `fixtures` data mode there is no API to sign in to; the app runs on a
 * demo session and the middleware never lands here.
 */
definePageMeta({ layout: false })
useHead({ title: 'Sign in' })

const email = ref('')
const password = ref('')
const remember = ref(true)
const showPassword = ref(false)
const submitting = ref(false)
const notice = ref<string | null>(null)

const errors = reactive<{ email?: string; password?: string }>({})

const { login } = useAuth()
const route = useRoute()

function validate(): boolean {
  errors.email = !email.value.trim()
    ? 'Enter your email address.'
    : !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)
      ? 'That doesn’t look like a valid email address.'
      : undefined
  errors.password = !password.value ? 'Enter your password.' : undefined
  return !errors.email && !errors.password
}

async function submit() {
  notice.value = null
  if (!validate()) return

  submitting.value = true
  try {
    await login(email.value.trim(), password.value, remember.value)
    const target = route.query.redirect
    await navigateTo(typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/')
  } catch (err: unknown) {
    const e = err as { statusCode?: number; data?: { errors?: { email?: string[] }; message?: string } }
    if (e.statusCode === 422) {
      // Bad credentials, or the five-attempt lockout. Laravel words both.
      notice.value = e.data?.errors?.email?.[0] ?? e.data?.message ?? 'Those details didn’t match an account.'
    } else if (!e.statusCode) {
      notice.value = 'Couldn’t reach the server. Check your connection and try again.'
    } else {
      notice.value = `Sign-in failed (error ${e.statusCode}). Try again in a moment.`
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthShell>
    <h1 class="text-2xl font-semibold text-fg-strong">Welcome back</h1>
    <p class="mt-2 text-sm text-fg-muted">Sign in to the Gold Coast Tokota dashboard to continue.</p>

    <form class="mt-8 flex flex-col gap-5" novalidate @submit.prevent="submit">
      <UiField
        v-model="email" label="Email address" type="email" required
        autocomplete="username" placeholder="you@goldcoasttokota.store"
        :error="errors.email"
      />

      <UiField
        v-model="password" label="Password" :type="showPassword ? 'text' : 'password'"
        required autocomplete="current-password" placeholder="••••••••"
        :error="errors.password"
      >
        <template #suffix>
          <button
            type="button" class="flex h-full items-center px-2 text-fg-faint transition-colors hover:text-fg-muted"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            @click="showPassword = !showPassword"
          >
            <component :is="showPassword ? PhEyeSlash : PhEye" :size="18" />
          </button>
        </template>
      </UiField>

      <div class="flex items-center justify-between gap-3">
        <label class="flex cursor-pointer items-center gap-2 text-sm text-fg-muted">
          <input
            v-model="remember" type="checkbox"
            class="size-4 rounded-sm border-border-strong accent-fg-strong focus:ring-border"
          >
          Keep me signed in
        </label>
        <NuxtLink
          :to="{ path: '/forgot-password', query: email ? { email } : {} }"
          class="text-sm text-fg-muted underline-offset-4 hover:text-fg-strong hover:underline"
        >
          Forgot password?
        </NuxtLink>
      </div>

      <p
        v-if="notice"
        class="flex items-start gap-2.5 rounded-lg border border-warning/30 bg-warning-soft
               px-3.5 py-3 text-meta text-warning"
        role="alert"
      >
        <PhWarningCircle :size="16" class="mt-px shrink-0" />
        <span>{{ notice }}</span>
      </p>

      <UiButton type="submit" block :loading="submitting">
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </UiButton>
    </form>
  </AuthShell>
</template>
