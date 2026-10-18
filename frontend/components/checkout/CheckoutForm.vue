<script setup lang="ts">
import { PhCaretLeft } from '@phosphor-icons/vue'
import type { Pin } from '~/components/checkout/AddressPinPicker.vue'
import { countryOptions as allCountries, GHANA_REGIONS } from '~/utils/countries'
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
  /** The delivery pin, Ghana only. Decides whether Accra's courier zone applies. */
  lat: number | null
  lng: number | null
  placeId: string | null
}

const model = defineModel<ShippingAddress>({ required: true })
const emit = defineEmits<{ submit: [], cart: [] }>()

const errors = reactive<Record<string, string | undefined>>({})

// Every country, Ghana first. The country decides the delivery zone, so it has
// to be a real ISO code; the old "Somewhere else" option sent one the API
// refused.
const countryOptions = allCountries()

const regionOptions = [
  { value: '', label: 'Choose a region' },
  ...GHANA_REGIONS.map((region) => ({ value: region, label: region })),
]

const isGhana = computed(() => model.value.country === 'GH')

const pin = computed<Pin | null>({
  get: () => model.value.lat != null && model.value.lng != null
    ? { lat: model.value.lat, lng: model.value.lng, placeId: model.value.placeId }
    : null,
  set: (value) => {
    model.value.lat = value?.lat ?? null
    model.value.lng = value?.lng ?? null
    model.value.placeId = value?.placeId ?? null
  },
})

/** A searched place fills the address only where the customer left it blank. */
function onPlace(place: { line1: string | null, city: string | null }) {
  if (!model.value.line1.trim() && place.line1) model.value.line1 = place.line1
  if (!model.value.city.trim() && place.city) model.value.city = place.city
}

// A pin only means anything in Ghana, and a region typed for one country is
// rarely valid in the next.
watch(() => model.value.country, (country, previous) => {
  if (country === previous) return
  if (country !== 'GH') pin.value = null
  if (country === 'GH' || previous === 'GH') model.value.region = ''
})

function validate(): boolean {
  errors.email = !model.value.email.trim()
    ? 'Enter your email address so we can send your receipt.'
    : !isValidEmail(model.value.email)
      ? 'That doesn’t look like a valid email address.'
      : undefined

  errors.fullName = !model.value.fullName.trim() ? 'Enter the recipient’s name.' : undefined
  errors.line1 = !model.value.line1.trim() ? 'Enter a street address.' : undefined
  errors.city = !model.value.city.trim() ? 'Enter a city or town.' : undefined
  errors.country = !model.value.country ? 'Choose a delivery country.' : undefined
  // Outside Accra, the region is what prices delivery.
  errors.region = isGhana.value && !model.value.region ? 'Choose your region.' : undefined

  // The Ghana pattern applies only to a Ghana address. Running it on every
  // order would reject every international customer, which is the bug this
  // form had while it validated nothing at all.
  const phone = model.value.phone.trim()
  errors.phone = !phone
    ? 'Enter a phone number so the courier can reach you.'
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
      <h2 class="caps-title w-full">Contact</h2>
      <FormsFormField
        v-model="model.email"
        label="Email" name="email" type="email" autocomplete="email" required
        hint="Your order confirmation and receipt go here."
        :error="errors.email"
      />
    </section>

    <section class="flex w-full flex-col items-start gap-4">
      <h2 class="caps-title w-full">Delivery</h2>

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
          v-if="isGhana"
          v-model="model.region"
          label="Region" name="region" type="select" :options="regionOptions" required
          autocomplete="address-level1" :error="errors.region"
        />
        <FormsFormField
          v-else
          v-model="model.region"
          label="State or province" name="region" autocomplete="address-level1"
        />
        <FormsFormField
          v-if="!isGhana"
          v-model="model.postcode"
          label="Postal code" name="postcode" autocomplete="postal-code"
        />
      </div>
      <ClientOnly v-if="isGhana">
        <CheckoutAddressPinPicker v-model="pin" @place="onPlace" />
      </ClientOnly>
      <FormsFormField
        v-model="model.phone"
        label="Phone (WhatsApp)" name="phone" type="tel" autocomplete="tel" required
        :hint="isGhana ? 'For delivery updates from the courier.' : 'Include your country code.'"
        :error="errors.phone"
      />
    </section>

    <!-- Shopify's action row: the way back on the left as a quiet link, the way
         forward on the right as the only button. -->
    <div class="flex w-full flex-col-reverse items-stretch gap-4 sm:flex-row sm:items-center sm:justify-between">
      <button
        type="button"
        class="caps-label -my-2.5 flex min-h-[44px] items-center gap-1 py-2.5 text-ink hover:underline sm:justify-start"
        @click="emit('cart')"
      >
        <PhCaretLeft :size="12" aria-hidden="true" />
        Return to cart
      </button>
      <CommonBrandButton type="submit">Continue to shipping</CommonBrandButton>
    </div>
  </form>
</template>
