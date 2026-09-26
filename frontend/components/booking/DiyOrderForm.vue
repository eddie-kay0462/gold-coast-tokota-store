<script setup lang="ts">
import { useSiteSettingsStore } from '~/stores/siteSettings'
import { whatsappMessage } from '~/utils/whatsapp'

const config = useRuntimeConfig()
const siteSettings = useSiteSettingsStore()

const form = reactive({
  size: '',
  footLength: '',
  fulfilment: 'pickup' as 'pickup' | 'delivery',
  name: '',
  email: '',
  phone: '',
})

const referenceImage = ref<File | null>(null)
const submitted = ref(false)
const pendingSubmit = ref(false)
const error = ref('')

function onFileChange(event: Event) {
  referenceImage.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

/**
 * Reference photos travel over WhatsApp, not through this form.
 * `StoreBookingRequest` types `details.reference_image` as a string, and there
 * is no upload endpoint behind it — so the filename is recorded here to tie the
 * two together, and the customer is told plainly where to send the photo.
 */
const referencePhotoMessage = computed(() => whatsappMessage.diyReference(form.name))

/** The way out when the submit fails — same request, different channel. */
const whatsappFallbackMessage = computed(() => whatsappMessage.diyOrder())

async function onSubmit() {
  if (pendingSubmit.value) return
  pendingSubmit.value = true
  error.value = ''

  try {
    // Every field lives under `details`, which is what StoreBookingRequest
    // validates — including the contact fields, which used to be sent at the
    // top level where the request never looked.
    await $fetch(`${config.public.apiBase}/bookings`, {
      method: 'POST',
      body: {
        type: 'diy_order',
        details: {
          size: form.size,
          foot_length: Number(form.footLength),
          fulfilment: form.fulfilment,
          name: form.name,
          email: form.email,
          phone: form.phone,
          reference_image: referenceImage.value?.name ?? null,
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
    <div v-if="submitted" class="border border-line bg-surface px-6 py-14 text-center">
      <p class="text-display-sm font-light text-black">Order received</p>
      <p class="mt-2 text-label text-muted">We will confirm by email and SMS.</p>
    </div>

    <form
      v-else
      class="mx-auto flex w-full max-w-[640px] flex-col gap-4 border border-line p-6"
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
          <input
            id="reference_image"
            type="file"
            accept="image/*"
            class="w-full min-w-0 text-caption text-graphite file:mr-3 file:min-h-[36px] file:border-0 file:bg-surface file:px-3 file:text-caption file:text-graphite"
            @change="onFileChange"
          >
        </div>
        <p class="text-caption text-muted">
          We record the file name with your order and collect the photo itself over
          WhatsApp — there is no image upload on this form yet.
          <CommonWhatsAppLink
            source="diy-reference"
            variant="quiet"
            :message="referencePhotoMessage"
          >Send it now</CommonWhatsAppLink>
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
            :class="form.fulfilment === option.value ? 'border-graphite bg-surface' : 'border-line bg-white'"
          >
            <input
              v-model="form.fulfilment"
              type="radio"
              name="fulfilment"
              :value="option.value"
              class="accent-graphite"
            >
            {{ option.label }}
          </label>
        </div>
      </fieldset>

      <FormsFormField v-model="form.name" label="Full name" name="name" required autocomplete="name" />
      <div class="grid gap-4 sm:grid-cols-2">
        <FormsFormField v-model="form.email" label="Email" name="email" type="email" required autocomplete="email" />
        <FormsFormField v-model="form.phone" label="Phone (WhatsApp)" name="phone" type="tel" required autocomplete="tel" />
      </div>

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

      <CommonBrandButton full type="submit" :disabled="pendingSubmit">
        {{ pendingSubmit ? 'Sending…' : 'Submit request' }}
      </CommonBrandButton>
    </form>
  </div>
</template>
