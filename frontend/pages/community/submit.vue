<script setup lang="ts">
import { isValidEmail } from '~/utils/validators'

/**
 * UGC submission — linked from the home page gallery's "Add Your Photo".
 *
 * BUILT BUT DELIBERATELY INACTIVE: there is no media-upload endpoint for
 * customer photos (the only upload path in the API is the DIY booking reference
 * image, which belongs to a booking). The form validates and explains itself
 * rather than posting into nothing.
 *
 * When a `POST /community/submissions` endpoint exists this becomes a FormData
 * post — see `components/booking/DiyOrderForm.vue` for the upload idiom.
 */
const form = reactive({ name: '', email: '', handle: '', note: '' })
const photo = ref<File | null>(null)
const errors = reactive<Record<string, string | undefined>>({})
const submitting = ref(false)
const notice = ref<string | null>(null)

const MAX_BYTES = 5 * 1024 * 1024

function onFileChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  photo.value = file
  errors.photo = !file
    ? undefined
    : !file.type.startsWith('image/')
      ? 'Choose an image file.'
      : file.size > MAX_BYTES
        ? 'That image is over 5MB — please choose a smaller one.'
        : undefined
}

function validate(): boolean {
  errors.name = !form.name.trim() ? 'Tell us your name.' : undefined
  errors.email = !form.email.trim()
    ? 'Enter your email so we can reach you.'
    : !isValidEmail(form.email)
      ? 'That doesn’t look like a valid email address.'
      : undefined
  if (!photo.value) errors.photo = 'Choose a photo to share.'
  return !Object.values(errors).some(Boolean)
}

async function onSubmit() {
  notice.value = null
  if (!validate()) return

  submitting.value = true
  await new Promise((resolve) => setTimeout(resolve, 600))
  submitting.value = false
  notice.value =
    'Photo submissions aren’t enabled yet — there’s no upload endpoint on the API side. ' +
    'Tag us with #GoldCoastTokota on Instagram instead and we’ll find it.'
}

useSeoMeta({
  title: 'Share your photo — Gold Coast Tokota',
  description: 'Send us a photo of your Gold Coast Tokota sandals for a chance to be featured.',
  ogTitle: 'Share your photo — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-8">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Share your photo</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          We love seeing where our sandals end up. Send us a photo and we may feature it on the
          site — we’ll always ask you first.
        </p>
      </header>

      <CommonInlineNotice v-if="notice" variant="warning" title="Submissions aren’t enabled yet">
        {{ notice }}
      </CommonInlineNotice>

      <form class="flex w-full max-w-[520px] flex-col items-start gap-5" novalidate @submit.prevent="onSubmit">
        <FormsFormField v-model="form.name" label="Name" name="name" required :error="errors.name" />
        <FormsFormField
          v-model="form.email" label="Email" name="email" type="email" autocomplete="email"
          required :error="errors.email"
        />
        <FormsFormField
          v-model="form.handle" label="Instagram handle" name="handle"
          hint="Optional — so we can credit you."
        />

        <div class="flex w-full min-w-0 flex-col gap-1.5">
          <label for="photo" class="text-caption font-normal text-graphite">
            Photo <span aria-hidden="true">*</span>
          </label>
          <input
            id="photo"
            name="photo"
            type="file"
            accept="image/*"
            class="w-full min-w-0 border bg-white px-3 py-2.5 text-caption text-graphite file:mr-3 file:border-0 file:bg-surface file:px-3 file:py-2 file:text-caption file:text-graphite"
            :class="errors.photo ? 'border-sale' : 'border-line'"
            :aria-invalid="!!errors.photo"
            :aria-describedby="errors.photo ? 'photo-error' : 'photo-hint'"
            @change="onFileChange"
          >
          <p v-if="errors.photo" id="photo-error" class="text-caption text-sale">{{ errors.photo }}</p>
          <p v-else id="photo-hint" class="text-caption text-muted">JPG or PNG, up to 5MB.</p>
        </div>

        <FormsFormField
          v-model="form.note" label="Anything to add?" name="note" type="textarea" :rows="3"
          hint="Where was it taken? Which pair?"
        />

        <CommonBrandButton full type="submit" :disabled="submitting">
          {{ submitting ? 'Sending…' : 'Send photo' }}
        </CommonBrandButton>
      </form>

      <p class="w-full max-w-[720px] text-caption text-muted">
        By sending a photo you confirm it’s yours to share. We’ll ask before we publish it, and
        you can tell us to remove it at any time.
      </p>
    </section>
  </div>
</template>
