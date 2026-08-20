<script setup lang="ts">
import { PhArrowRight as ArrowRight } from '@phosphor-icons/vue'

const props = withDefaults(defineProps<{ source?: string }>(), { source: 'footer' })

const email = ref('')
const submitted = ref(false)
const error = ref('')
const pending = ref(false)

async function onSubmit() {
  const config = useRuntimeConfig()
  pending.value = true
  error.value = ''
  try {
    await $fetch(`${config.public.apiBase}/newsletter`, {
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
    <form v-if="!submitted" class="flex" @submit.prevent="onSubmit">
      <label class="sr-only" for="newsletter-email">Email address</label>
      <input
        id="newsletter-email"
        v-model="email"
        type="email"
        required
        placeholder="Sign up for our Newsletter here!"
        class="w-full max-w-[388px] border border-line bg-white px-[15px] py-[18px] text-label text-graphite placeholder:text-muted"
      >
      <button
        type="submit"
        :disabled="pending"
        class="flex shrink-0 items-start border border-graphite bg-graphite px-[14px] py-[14.5px] text-white disabled:opacity-60"
        aria-label="Sign up for the newsletter"
      >
        <ArrowRight :size="24" />
      </button>
    </form>
    <p v-else class="text-label text-graphite">You're on the list. Watch your inbox for launches.</p>
    <p v-if="error" class="mt-2 text-caption text-sale">{{ error }}</p>
  </div>
</template>
