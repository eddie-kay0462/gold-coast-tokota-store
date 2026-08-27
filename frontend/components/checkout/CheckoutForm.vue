<script setup lang="ts">
import { PhCaretLeft } from '@phosphor-icons/vue'
import { isValidEmail, isValidGhanaPhone } from '~/utils/validators'

export type ShippingAddress = {
  email: string
  fullName: string
  line1: string
  city: string
  region: string
  postcode: string
  country: string
  phone: string
}

const model = defineModel<ShippingAddress>({ required: true })
const emit = defineEmits<{ submit: [], cart: [] }>()

const errors = reactive<Record<string, string | undefined>>({})

// Kept short deliberately: this is the delivery-routing decision (Ghana → Yango,
// everything else → DHL), not a full ISO list. Extend from the API when the
// delivery integration lands.
const countryOptions = [
  { value: 'GH', label: 'Ghana' },
  { value: 'NG', label: 'Nigeria' },
  { value: 'GB', label: 'United Kingdom' },
  { value: 'US', label: 'United States' },
  { value: 'CA', label: 'Canada' },
  { value: 'DE', label: 'Germany' },
  { value: 'FR', label: 'France' },
  { value: 'ZA', label: 'South Africa' },
  { value: 'OTHER', label: 'Somewhere else' },
]

const isGhana = computed(() => model.value.country === 'GH')

function validate(): boolean {
  errors.email = !model.value.email.trim()
    ? 'Enter your email address — we send your receipt there.'
    : !isValidEmail(model.value.email)
      ? 'That doesn’t look like a valid email address.'
      : undefined

  errors.fullName = !model.value.fullName.trim() ? 'Enter the recipient’s name.' : undefined
  errors.line1 = !model.value.line1.trim() ? 'Enter a street address.' : undefined
  errors.city = !model.value.city.trim() ? 'Enter a city or town.' : undefined
  errors.country = !model.value.country ? 'Choose a delivery country.' : undefined

  // The Ghana pattern applies only to a Ghana address. Running it on every
  // order would reject every international customer, which is the bug this
  // form had while it validated nothing at all.
  const phone = model.value.phone.trim()
  errors.phone = !phone
    ? 'Enter a phone number — the courier needs it.'
    : isGhana.value
      ? isValidGhanaPhone(phone)
        ? undefined
        : 'Enter a Ghana number as 0XXXXXXXXX or +233XXXXXXXXX.'
      : /^\+?[0-9\s-]{7,}$/.test(phone)
        ? undefined
        : 'Include your country code, e.g. +44 7700 900000.'

  return !Object.values(errors).some(Boolean)
}

function onSubmit() {
  if (validate()) emit('submit')
}

defineExpose({ validate })
</script>

<template>
  <!-- Split into Contact and Delivery, in Shopify's field order: country first
       (it is what routes the courier and changes the fields below it), then the
       recipient, then the address, then the phone. -->
  <form class="flex w-full flex-col items-start gap-8" novalidate @submit.prevent="onSubmit">
    <section class="flex w-full flex-col items-start gap-4">
      <h2 class="w-full text-body font-normal text-black">Contact</h2>
      <FormsFormField
        v-model="model.email"
        label="Email" name="email" type="email" autocomplete="email" required
        hint="Your order confirmation and receipt go here."
        :error="errors.email"
      />
    </section>

    <section class="flex w-full flex-col items-start gap-4">
      <h2 class="w-full text-body font-normal text-black">Delivery</h2>

      <FormsFormField
        v-model="model.country"
        label="Country/Region" name="country" type="select" :options="countryOptions" required
        :error="errors.country"
      />
      <FormsFormField
        v-model="model.fullName"
        label="Full name" name="fullName" autocomplete="name" required :error="errors.fullName"
      />
      <FormsFormField
        v-model="model.line1"
        label="Address" name="line1" autocomplete="address-line1" required :error="errors.line1"
      />
      <div class="flex w-full min-w-0 flex-col gap-4 sm:flex-row">
        <FormsFormField
          v-model="model.city"
          label="City" name="city" autocomplete="address-level2" required :error="errors.city"
        />
        <FormsFormField
          v-model="model.region"
          :label="isGhana ? 'Region' : 'State or province'"
          name="region" autocomplete="address-level1"
        />
        <FormsFormField
          v-if="!isGhana"
          v-model="model.postcode"
          label="Postal code" name="postcode" autocomplete="postal-code"
        />
      </div>
      <FormsFormField
        v-model="model.phone"
        label="Phone" name="phone" type="tel" autocomplete="tel" required
        :hint="isGhana ? 'For delivery updates from the courier.' : 'Include your country code.'"
        :error="errors.phone"
      />
    </section>

    <!-- Shopify's action row: the way back on the left as a quiet link, the way
         forward on the right as the only button. -->
    <div class="flex w-full flex-col-reverse items-stretch gap-4 sm:flex-row sm:items-center sm:justify-between">
      <button
        type="button"
        class="-my-2.5 flex min-h-[44px] items-center gap-1 py-2.5 text-caption text-graphite hover:underline sm:justify-start"
        @click="emit('cart')"
      >
        <PhCaretLeft :size="12" aria-hidden="true" />
        Return to cart
      </button>
      <CommonBrandButton type="submit">Continue to shipping</CommonBrandButton>
    </div>
  </form>
</template>
