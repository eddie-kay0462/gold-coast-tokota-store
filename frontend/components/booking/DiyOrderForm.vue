<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'
import { whatsappMessage } from '~/utils/whatsapp'
import { nationalNumberError, toE164 } from '~/utils/phone'

const api = useApi()
const siteSettings = useSiteSettingsStore()

const form = reactive({
  size: '',
  footLength: '',
  fulfilment: 'pickup' as 'pickup' | 'delivery',
  // First and last name, and a phone in two parts, as checkout asks for them.
  firstName: '',
  lastName: '',
  email: '',
  phoneCountry: 'GH',
  phone: '',
})
const phoneError = ref<string | undefined>()
const fullName = computed(() => `${form.firstName} ${form.lastName}`.trim())

const submitted = ref(false)
const pendingSubmit = ref(false)
const error = ref('')

/**
 * The reference photo is uploaded as soon as it's chosen
 * (`POST /booking-uploads`), so a wrong type or an oversized photo is caught
 * straight away rather than on submit. The booking then carries the stored
 * `path` as `details.reference_image`, and the workshop sees the photo with
 * the order in the admin. The limits mirror StoreBookingUploadRequest.
 */
const MAX_PHOTO_BYTES = 5 * 1024 * 1024
const PHOTO_TYPES = /\.(jpe?g|png|webp|heic|heif)$/i

const photo = ref<{ path: string, url: string, filename: string, previewable: boolean } | null>(null)
const uploading = ref(false)
const photoError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

async function onFileChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  photoError.value = ''
  photo.value = null
  if (!file) return

  if (!PHOTO_TYPES.test(file.name)) {
    photoError.value = 'Upload a JPG, PNG, WebP or HEIC photo.'
    return
  }
  if (file.size > MAX_PHOTO_BYTES) {
    photoError.value = 'Photos must be 5MB or smaller.'
    return
  }

  uploading.value = true
  try {
    const body = new FormData()
    body.append('file', file)
    const res = await api<{ data: { path: string, url: string, filename: string } }>('/booking-uploads', { method: 'POST', body })
    // Browsers can't show HEIC, so those get the file name instead of a thumbnail.
    photo.value = { ...res.data, previewable: !/\.(heic|heif)$/i.test(file.name) }
  } catch (err) {
    const e = err as { statusCode?: number, data?: { errors?: { file?: string[] }, message?: string } }
    photoError.value = e.statusCode === 429
      ? 'Too many uploads for now. You can send the photo on WhatsApp instead.'
      : e.data?.errors?.file?.[0] ?? 'That photo didn’t upload. Try again, or send it on WhatsApp.'
  } finally {
    uploading.value = false
  }
}

function removePhoto() {
  photo.value = null
  photoError.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

/** The fallback when an upload can't be done. */
const referencePhotoMessage = computed(() => whatsappMessage.diyReference(fullName.value))

/** The way out when the submit fails: same request, different channel. */
const whatsappFallbackMessage = computed(() => whatsappMessage.diyOrder())

async function onSubmit() {
  if (pendingSubmit.value || uploading.value) return
  phoneError.value = nationalNumberError(form.phoneCountry, form.phone)
  if (phoneError.value) return
  pendingSubmit.value = true
  error.value = ''

  try {
    // Every field lives under `details`, which is what StoreBookingRequest
    // validates, including the contact fields, which used to be sent at the
    // top level where the request never looked.
    await api('/bookings', {
      method: 'POST',
      body: {
        type: 'diy_order',
        details: {
          size: form.size,
          foot_length: Number(form.footLength),
          fulfilment: form.fulfilment,
          first_name: form.firstName.trim(),
          last_name: form.lastName.trim(),
          // Also whole, for an API from before the two parts.
          name: fullName.value,
          email: form.email,
          phone: toE164(form.phoneCountry, form.phone),
          reference_image: photo.value?.path ?? null,
        },
      },
    })
    submitted.value = true
  }
  catch {
    error.value = 'We could not submit that order. Check your details and try again, or send it to us directly.'
  }
  finally {
    pendingSubmit.value = false
  }
}
</script>

<template>
  <div class="mt-8">
    <div v-if="submitted" class="flex flex-col items-center gap-2 border-t border-line px-6 py-14 text-center">
      <p class="caps-label text-muted">Request sent</p>
      <p class="text-display-sm text-ink">Order received</p>
      <p class="text-label text-subtle">We will confirm by email and SMS.</p>
    </div>

    <form
      v-else
      class="mx-auto flex w-full max-w-[640px] flex-col gap-4"
      @submit.prevent="onSubmit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <FormsFormField
          v-model="form.size"
          label="Sandal size (EU)"
          name="size"
          placeholder="e.g. 42"
          required
        />
        <FormsFormField
          v-model="form.footLength"
          label="Foot length (cm)"
          name="foot_length"
          type="number"
          placeholder="e.g. 27.5"
          required
        />
      </div>

      <!-- Reference image. The dashed well is the approved mockup's dropzone;
           the native input sits inside it so keyboard and screen-reader users
           get the real control. -->
      <div class="flex w-full min-w-0 flex-col gap-1.5">
        <label for="reference_image" class="text-caption font-normal text-graphite">
          Reference image
        </label>
        <div class="w-full border border-dashed border-line bg-white p-4">
          <div v-if="photo" class="flex items-center gap-3">
            <img
              v-if="photo.previewable"
              :src="photo.url"
              :alt="`Your reference photo, ${photo.filename}`"
              class="size-16 shrink-0 bg-surface object-cover"
            >
            <span class="min-w-0 flex-1 truncate text-caption text-graphite">{{ photo.filename }}</span>
            <button type="button" class="text-caption text-graphite underline hover:no-underline" @click="removePhoto">
              Remove
            </button>
          </div>
          <input
            v-show="!photo"
            id="reference_image"
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
            :disabled="uploading"
            :aria-describedby="photoError ? 'reference_image-error' : 'reference_image-hint'"
            class="w-full min-w-0 text-caption text-graphite file:mr-3 file:min-h-[36px] file:border-0 file:bg-surface file:px-3 file:text-caption file:text-graphite"
            @change="onFileChange"
          >
        </div>
        <p v-if="uploading" class="text-caption text-muted" aria-live="polite">Uploading your photo…</p>
        <p v-else-if="photoError" id="reference_image-error" class="text-caption text-sale" role="alert">
          {{ photoError }}
          <CommonWhatsAppLink source="diy-reference" variant="quiet" :message="referencePhotoMessage">
            Send it on WhatsApp
          </CommonWhatsAppLink>
        </p>
        <p v-else id="reference_image-hint" class="text-caption text-muted">
          Optional. A photo of the design you have in mind, up to 5MB (JPG, PNG, WebP or HEIC).
        </p>
      </div>

      <!-- `pickup_or_delivery` sat in the form state with no control and was
           submitted as a silent default. The mockup designs the choice. -->
      <fieldset class="flex w-full min-w-0 flex-col gap-1.5">
        <legend class="text-caption font-normal text-graphite">Fulfilment</legend>
        <div class="flex flex-col gap-2 sm:flex-row">
          <label
            v-for="option in [
              { value: 'pickup', label: 'Pickup in Accra' },
              { value: 'delivery', label: 'Delivery' },
            ]"
            :key="option.value"
            class="flex min-h-[44px] flex-1 cursor-pointer items-center gap-2.5 border px-4 text-label text-graphite"
            :class="form.fulfilment === option.value ? 'border-ink text-ink' : 'border-line bg-white hover:border-ink'"
          >
            <input
              v-model="form.fulfilment"
              type="radio"
              name="fulfilment"
              :value="option.value"
              class="accent-ink"
            >
            {{ option.label }}
          </label>
        </div>
      </fieldset>

      <div class="grid gap-4 sm:grid-cols-2">
        <FormsFormField v-model="form.firstName" label="First name" name="first-name" required autocomplete="given-name" />
        <FormsFormField v-model="form.lastName" label="Last name" name="last-name" required autocomplete="family-name" />
      </div>
      <FormsFormField v-model="form.email" label="Email" name="email" type="email" required autocomplete="email" />
      <FormsPhoneField
        v-model="form.phone"
        v-model:country="form.phoneCountry"
        label="Phone (WhatsApp)" name="phone" required
        :error="phoneError"
      />

      <div
        v-if="siteSettings.diyTurnaroundEstimate"
        class="flex items-center justify-between border-t border-line pt-4 text-caption text-muted"
      >
        <span>Current turnaround</span>
        <span class="font-normal text-graphite">{{ siteSettings.diyTurnaroundEstimate }}</span>
      </div>

      <CommonInlineNotice v-if="error" variant="warning">
        {{ error }}
        <template #action>
          <CommonWhatsAppLink source="booking-error" variant="quiet" :message="whatsappFallbackMessage">
            Send it on WhatsApp instead
          </CommonWhatsAppLink>
        </template>
      </CommonInlineNotice>

      <CommonBrandButton full type="submit" :disabled="pendingSubmit || uploading">
        {{ pendingSubmit ? 'Sending…' : 'Submit request' }}
      </CommonBrandButton>
    </form>
  </div>
</template>
