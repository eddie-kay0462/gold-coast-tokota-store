<script setup lang="ts">
import { PhArrowRight as ArrowRight } from '@phosphor-icons/vue'

const props = withDefaults(
  defineProps<{
    source?: string
    /**
     * `light` is the original white-ground form. `dark` is the inline
     * bordered field with a gold "Join" from the approved Template B mockup,
     * Used by nothing since the footer went white; kept for dark grounds.
     */
    tone?: 'light' | 'dark'
    /** Overrides the tone's default; the light one is too long for a narrow
     *  column like the footer's. */
    placeholder?: string
  }>(),
  { source: 'footer', tone: 'light' },
)

const email = ref('')
const submitted = ref(false)
const error = ref('')
const pending = ref(false)

const isDark = computed(() => props.tone === 'dark')

const api = useApi()

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    await api('/newsletter', {
      method: 'POST',
      body: { email: email.value, source: props.source },
    })
    submitted.value = true
  }
  catch {
    error.value = 'That sign-up did not go through. Check the address and try again.'
  }
  finally {
    pending.value = false
  }
}
</script>

<template>
  <div>
    <!-- `min-w-0` on the input matters: an <input> has an intrinsic min-content
         width (~180px) that `w-full` cannot shrink past while it is a flex item,
         which left the row 8px clear of a 320px viewport. -->
    <form v-if="!submitted" class="flex w-full min-w-0" @submit.prevent="onSubmit">
      <label class="sr-only" for="newsletter-email">Email address</label>
      <input
        id="newsletter-email"
        v-model="email"
        type="email"
        required
        :placeholder="placeholder ?? (isDark ? 'Email address' : 'Sign up for our Newsletter here!')"
        class="w-full min-w-0 max-w-[388px] flex-1 border text-label"
        :class="isDark
          ? 'border-white/25 bg-transparent px-[15px] py-3 text-white placeholder:text-white/50'
          : 'border-line bg-white px-[15px] py-[18px] text-graphite placeholder:text-muted'"
      >
      <button
        v-if="isDark"
        type="submit"
        :disabled="pending"
        class="flex min-h-[44px] shrink-0 items-center justify-center border border-gold bg-gold px-4 text-caption uppercase tracking-[1px] text-chrome disabled:opacity-60"
      >
        Join
      </button>
      <button
        v-else
        type="submit"
        :disabled="pending"
        class="btn-outline flex shrink-0 items-start border-l-0 px-[14px] py-[14.5px] disabled:opacity-60"
        aria-label="Sign up for the newsletter"
      >
        <ArrowRight :size="24" />
      </button>
    </form>
    <p v-else class="text-label" :class="isDark ? 'text-white/70' : 'text-graphite'">
      You're on the list. Watch your inbox for launches.
    </p>
    <p v-if="error" class="mt-2 text-caption" :class="isDark ? 'text-sale-on-dark' : 'text-sale'">
      {{ error }}
    </p>
  </div>
</template>
