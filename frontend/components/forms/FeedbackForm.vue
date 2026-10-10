<script setup lang="ts">
const form = reactive({ name: '', email: '', message: '' })
const submitted = ref(false)
const api = useApi()

async function onSubmit() {
  await api('/feedback', { method: 'POST', body: form })
  submitted.value = true
}
</script>

<template>
  <form v-if="!submitted" class="space-y-4" @submit.prevent="onSubmit">
    <FormsFormField v-model="form.name" label="Name" name="name" required />
    <FormsFormField v-model="form.email" label="Email" name="email" type="email" required />
    <FormsFormField v-model="form.message" label="Message" name="message" required />
    <CommonBrandButton full type="submit">Send Feedback</CommonBrandButton>
  </form>
  <p v-else>Thanks for your feedback!</p>
</template>
