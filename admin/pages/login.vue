<script setup lang="ts">
import { PhEye, PhEyeSlash, PhInfo, PhWarningCircle } from '@phosphor-icons/vue'

/**
 * Admin sign-in.
 *
 * BUILT BUT DELIBERATELY INACTIVE, as briefed. The form is complete and
 * validates, but submitting does not authenticate: the Laravel side has no
 * login endpoint yet (no AuthController, no route — README Feature 9), so
 * calling one would just produce a confusing 404.
 *
 * No route middleware is registered anywhere in this app either, so every page
 * remains reachable without signing in. When the endpoint lands, the `submit`
 * body becomes a `GET /sanctum/csrf-cookie` followed by `POST /admin/login`,
 * an `auth` middleware goes on the pages, and nothing else here changes.
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
  // Deliberate: shows the loading state the real call will have, then explains
  // why nothing happened instead of failing with a raw network error.
  await new Promise((r) => setTimeout(r, 600))
  submitting.value = false
  notice.value =
    'Sign-in isn’t enabled yet. The admin login endpoint hasn’t been built on the API side ' +
    '(README Feature 9), so this form is inactive for now — the dashboard is open without it.'
}
</script>

<template>
  <div class="flex min-h-dvh bg-bg">
    <!-- Brand panel. Hidden below lg, where it would push the form below the fold. -->
    <div class="relative hidden w-[45%] flex-col justify-between bg-ink p-10 lg:flex xl:w-1/2">
      <img src="/brand/logo-white.png" alt="Gold Coast Tokota" class="h-8 w-auto self-start object-contain">

      <div class="max-w-md">
        <p class="text-[32px] font-light leading-tight text-white">
          Crafted with Purpose.<br>Inspired by Culture.
        </p>
        <p class="mt-5 text-ui leading-relaxed text-white/60">
          Handcrafted sustainable footwear from recycled materials, celebrating Ghanaian
          culture through immersive experiences and craftsmanship.
        </p>
      </div>

      <div class="flex items-center gap-2 text-meta text-white/40">
        <span class="h-px w-8 bg-accent" />
        Haatso, Accra · Ghana
      </div>
    </div>

    <!-- Form -->
    <div class="flex flex-1 flex-col justify-center px-5 py-10 sm:px-10 lg:px-14">
      <div class="mx-auto w-full max-w-sm">
        <img src="/brand/logo.png" alt="Gold Coast Tokota" class="mb-8 h-7 w-auto object-contain lg:hidden dark:hidden">
        <img src="/brand/logo-white.png" alt="Gold Coast Tokota" class="mb-8 hidden h-7 w-auto object-contain dark:block lg:dark:hidden">

        <h1 class="text-title font-medium text-fg-strong">Sign in</h1>
        <p class="mt-1.5 text-ui text-fg-muted">
          Admin dashboard for Gold Coast Tokota.
        </p>

        <div
          class="mt-6 flex items-start gap-2.5 rounded-lg border border-accent/30 bg-accent-soft
                 px-3.5 py-3 text-meta text-accent-text"
        >
          <PhInfo :size="16" class="mt-px shrink-0" />
          <p>
            Authentication isn’t wired up yet — this form is inactive and the dashboard is
            open without it. Seeded account:
            <code class="font-mono">admin@goldcoasttokota.store</code>
          </p>
        </div>

        <form class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="submit">
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
                type="button" class="toolbar-btn"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
              >
                <component :is="showPassword ? PhEyeSlash : PhEye" :size="18" />
              </button>
            </template>
          </UiField>

          <div class="flex items-center justify-between gap-3">
            <label class="flex cursor-pointer items-center gap-2 text-ui text-fg-muted">
              <input
                v-model="remember" type="checkbox"
                class="size-4 rounded-sm border-border-strong text-accent focus:ring-accent"
              >
              Keep me signed in
            </label>
            <button type="button" class="text-ui text-accent-text underline-offset-4 hover:underline">
              Forgot password?
            </button>
          </div>

          <UiButton type="submit" block :loading="submitting">
            {{ submitting ? 'Signing in…' : 'Sign in' }}
          </UiButton>

          <p
            v-if="notice"
            class="flex items-start gap-2.5 rounded-lg border border-warning/30 bg-warning-soft
                   px-3.5 py-3 text-meta text-warning"
            role="alert"
          >
            <PhWarningCircle :size="16" class="mt-px shrink-0" />
            <span>{{ notice }}</span>
          </p>
        </form>

        <p class="mt-8 text-center text-meta text-fg-faint">
          <NuxtLink to="/" class="text-accent-text underline-offset-4 hover:underline">
            Continue to the dashboard
          </NuxtLink>
          — no sign-in required while authentication is pending.
        </p>
      </div>
    </div>
  </div>
</template>
