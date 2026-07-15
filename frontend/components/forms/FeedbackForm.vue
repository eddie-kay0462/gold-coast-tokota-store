<script setup lang="ts">
const form = reactive({ name: '', email: '', message: '' })
const submitted = ref(false)

async function onSubmit() {
  const config = useRuntimeConfig()
  await $fetch(`${config.public.apiBase}/feedback`, { method: 'POST', body: form })
  submitted.value = true
}
</script>

<template>
  <form v-if="!submitted" class="space-y-4" @submit.prevent="onSubmit">
    <FormsFormField v-model="form.name" label="Name" name="name" required />
    <FormsFormField v-model="form.email" label="Email" name="email" type="email" required />
    <FormsFormField v-model="form.message" label="Message" name="message" required />
    <button type="submit" class="rounded bg-black px-4 py-2 text-white">Send Feedback</button>
  </form>
  <p v-else>Thanks for your feedback!</p>
</template>
