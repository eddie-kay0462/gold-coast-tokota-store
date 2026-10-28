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
  <!-- 440px wide with 20px between fields: the same measure as the sign-in,
       sign-up and order-tracking forms, so every form on the site lines up.
       It used to stretch the whole column, about 650px on a laptop. -->
  <form v-if="!submitted" class="flex w-full max-w-[440px] flex-col items-start gap-5" @submit.prevent="onSubmit">
    <FormsFormField v-model="form.name" label="Name" name="name" autocomplete="name" required />
    <FormsFormField v-model="form.email" label="Email" name="email" type="email" autocomplete="email" required />
    <FormsFormField v-model="form.message" label="Message" name="message" type="textarea" :rows="5" required />
    <CommonBrandButton full type="submit">Send message</CommonBrandButton>
  </form>
  <p v-else class="w-full max-w-[440px] text-label text-graphite">Thanks, your message is with us.</p>
</template>
