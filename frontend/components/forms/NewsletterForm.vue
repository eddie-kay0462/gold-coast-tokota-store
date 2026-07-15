<script setup lang="ts">
const email = ref('')
const submitted = ref(false)

async function onSubmit() {
  const config = useRuntimeConfig()
  await $fetch(`${config.public.apiBase}/newsletter`, {
    method: 'POST',
    body: { email: email.value, source: 'footer' },
  })
  submitted.value = true
}
</script>

<template>
  <form v-if="!submitted" class="flex gap-2" @submit.prevent="onSubmit">
    <FormsFormField v-model="email" label="Email" name="newsletter-email" type="email" required />
    <button type="submit" class="rounded bg-black px-4 py-2 text-white">Subscribe</button>
  </form>
  <p v-else>Thanks for subscribing!</p>
</template>
