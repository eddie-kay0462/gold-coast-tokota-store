<script setup lang="ts">
/**
 * Finish a password reset (`POST /reset-password`). The emailed link lands
 * here as `/account/reset-password?token=…&email=…`; the token proves the
 * customer has the email, and the address says which account.
 */
const { resetPassword } = useAuth()
const route = useRoute()

const token = computed(() => String(route.query.token ?? ''))
const email = computed(() => String(route.query.email ?? ''))
const linkIsComplete = computed(() => Boolean(token.value && email.value))

const form = reactive({ password: '', confirm: '' })
const errors = reactive<{ password?: string, confirm?: string }>({})
const submitting = ref(false)
const done = ref(false)
const notice = ref<string | null>(null)

function validate(): boolean {
  errors.password = !form.password
    ? 'Choose a new password.'
    : form.password.length < 8 ? 'Use at least 8 characters.' : undefined
  errors.confirm = form.confirm !== form.password ? 'Passwords don’t match.' : undefined
  return !errors.password && !errors.confirm
}

async function onSubmit() {
  notice.value = null
  if (!validate()) return

  submitting.value = true
  const result = await resetPassword({
    token: token.value,
    email: email.value,
    password: form.password,
    password_confirmation: form.confirm,
  })
  submitting.value = false

  if (result.ok) {
    done.value = true
    return
  }
  errors.password = result.errors.password
  notice.value = result.errors.password ? null : result.notice
}

useSeoMeta({
  title: 'Choose a new password | Gold Coast Tokota',
  description: 'Choose a new password for your Gold Coast Tokota account.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <AccountAuthShell heading="Choose a new password">
    <template v-if="linkIsComplete && !done" #intro>
      For <strong class="font-normal">{{ email }}</strong>.
    </template>

    <template v-if="!linkIsComplete">
      <CommonInlineNotice variant="warning" title="This link isn’t complete">
        Open the link from your reset email again, or ask for a new one.
      </CommonInlineNotice>
      <CommonBrandButton to="/account/forgot-password">Get a new link</CommonBrandButton>
    </template>

    <template v-else-if="done">
      <CommonInlineNotice variant="success" title="Your password has been changed">
        You can sign in with your new password now.
      </CommonInlineNotice>
      <CommonBrandButton to="/account/login">Sign in</CommonBrandButton>
    </template>

    <template v-else>
      <CommonInlineNotice v-if="notice" variant="warning" title="We couldn’t change your password">
        {{ notice }}
        <NuxtLink to="/account/forgot-password" class="underline hover:no-underline">Get a new link</NuxtLink>.
      </CommonInlineNotice>

      <form class="flex w-full flex-col items-start gap-5" novalidate @submit.prevent="onSubmit">
        <FormsFormField
          v-model="form.password"
          label="New password" name="password" type="password" autocomplete="new-password"
          hint="At least 8 characters." required :error="errors.password"
        />
        <FormsFormField
          v-model="form.confirm"
          label="Confirm new password" name="confirm" type="password" autocomplete="new-password"
          required :error="errors.confirm"
        />
        <CommonBrandButton full type="submit" :disabled="submitting">
          {{ submitting ? 'Saving…' : 'Save new password' }}
        </CommonBrandButton>
      </form>
    </template>
  </AccountAuthShell>
</template>
