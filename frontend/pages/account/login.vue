<script setup lang="ts">
import { PhEye, PhEyeSlash } from '@phosphor-icons/vue'
import { isValidEmail } from '~/utils/validators'

/**
 * Customer sign-in — BUILT BUT DELIBERATELY INACTIVE.
 * See `composables/useAuth.ts` for what turning it on involves.
 */
const { signIn } = useAuth()

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
  const result = await signIn({ email: form.email, password: form.password })
  submitting.value = false
  notice.value = result.notice
}

useSeoMeta({
  title: 'Sign in — Gold Coast Tokota',
  description: 'Sign in to your Gold Coast Tokota account.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[calc(27.5rem+120px)] flex-col items-start gap-6">
      <header class="flex w-full flex-col items-start gap-2">
        <h1 class="w-full text-display-section font-normal text-black">Sign in</h1>
        <p class="w-full text-body text-graphite">
          Welcome back. Don’t have an account?
          <NuxtLink to="/account/register" class="underline hover:no-underline">Create one</NuxtLink>.
        </p>
      </header>

      <CommonInlineNotice v-if="notice" variant="warning" title="Sign-in isn’t enabled yet">
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
          <div class="flex w-full min-w-0 items-stretch">
            <input
              id="password"
              v-model="form.password"
              name="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              class="min-h-[44px] w-full min-w-0 flex-1 border border-r-0 bg-white px-3 py-2.5 text-body text-graphite placeholder:text-muted"
              :class="errors.password ? 'border-sale' : 'border-line'"
              :aria-invalid="!!errors.password"
              :aria-describedby="errors.password ? 'password-error' : undefined"
            >
            <!-- size-11 rather than a bare icon: the 44px tap floor applies to
                 the button, not the glyph inside it. -->
            <button
              type="button"
              class="flex size-11 shrink-0 items-center justify-center border text-subtle"
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

        <CommonBrandButton full type="submit" :disabled="submitting">
          {{ submitting ? 'Signing in…' : 'Sign in' }}
        </CommonBrandButton>
      </form>

      <p class="w-full text-caption text-muted">
        You don’t need an account to order —
        <NuxtLink to="/shop" class="underline hover:no-underline">shop as a guest</NuxtLink>
        and we’ll email your confirmation.
      </p>
    </section>
  </div>
</template>
