<script setup lang="ts">
import { PhCrosshair, PhMapPin } from '@phosphor-icons/vue'

/**
 * Where exactly in Ghana the parcel is going, as a map pin.
 *
 * The pin decides the delivery zone: inside the Accra service area an order
 * goes out with an on-demand courier, outside it at the regional flat rate.
 * Ghana street addresses alone are too loose for a courier to find a door, so
 * the pin, not the typed address, is what the courier is sent to.
 *
 * With `NUXT_PUBLIC_GOOGLE_MAPS_KEY` set: Places search restricted to Ghana, a
 * map, and a pin the customer drags onto the right building. Without one (local
 * development, or a key that failed to load): just "Use my current location",
 * which still produces coordinates. Either way the pin is optional; an address
 * with none is priced at its regional rate.
 *
 * Google Maps loads by script tag on demand rather than as an npm package, and
 * only on this step, so nothing else on the storefront pays for it.
 */
export type Pin = { lat: number, lng: number, placeId: string | null }

const pin = defineModel<Pin | null>({ default: null })
const emit = defineEmits<{
  /** A searched place's address, for filling fields the customer left empty. */
  place: [{ line1: string | null, city: string | null }]
}>()

const config = useRuntimeConfig().public
const apiKey = config.googleMapsKey as string
const mapId = (config.googleMapsMapId as string) || 'DEMO_MAP_ID'

/** Accra, for an empty map. */
const ACCRA = { lat: 5.6037, lng: -0.187 }

const searchHost = ref<HTMLElement | null>(null)
const mapHost = ref<HTMLElement | null>(null)
const mapsReady = ref(false)
const mapsFailed = ref(false)
const locating = ref(false)
const notice = ref<string | null>(null)

/* eslint-disable @typescript-eslint/no-explicit-any -- the Maps JS API is loaded at runtime, untyped */
let map: any = null
let marker: any = null

function loadMaps(): Promise<any> {
  const w = window as any
  if (w.google?.maps?.importLibrary) return Promise.resolve(w.google.maps)

  w.__gctMapsLoading ??= new Promise((resolve, reject) => {
    w.__gctMapsReady = () => resolve(w.google.maps)
    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}`
      + '&v=weekly&loading=async&callback=__gctMapsReady'
    script.async = true
    script.onerror = () => reject(new Error('Google Maps failed to load.'))
    document.head.appendChild(script)
  })

  return w.__gctMapsLoading
}

function setPin(lat: number, lng: number, placeId: string | null = null) {
  pin.value = { lat: Number(lat.toFixed(6)), lng: Number(lng.toFixed(6)), placeId }
  if (marker) marker.position = { lat, lng }
  map?.panTo({ lat, lng })
}

async function initMap() {
  try {
    const maps = await loadMaps()
    const [{ Map }, { AdvancedMarkerElement }, { PlaceAutocompleteElement }] = await Promise.all([
      maps.importLibrary('maps'),
      maps.importLibrary('marker'),
      maps.importLibrary('places'),
    ])

    const start = pin.value ?? ACCRA
    map = new Map(mapHost.value, {
      center: start,
      zoom: pin.value ? 17 : 12,
      mapId,
      disableDefaultUI: true,
      zoomControl: true,
      gestureHandling: 'cooperative',
    })

    marker = new AdvancedMarkerElement({
      map,
      position: pin.value ?? null,
      gmpDraggable: true,
      title: 'Delivery location',
    })
    marker.addListener('dragend', () => {
      const position = marker.position
      const lat = typeof position.lat === 'function' ? position.lat() : position.lat
      const lng = typeof position.lng === 'function' ? position.lng() : position.lng
      setPin(lat, lng, null)
    })
    map.addListener('click', (event: any) => {
      setPin(event.latLng.lat(), event.latLng.lng(), null)
    })

    const search = new PlaceAutocompleteElement({ includedRegionCodes: ['gh'] })
    search.setAttribute('placeholder', 'Search for your street, estate or landmark')
    search.addEventListener('gmp-select', async (event: any) => {
      const place = event.placePrediction.toPlace()
      await place.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents', 'id'] })
      if (!place.location) return

      setPin(place.location.lat(), place.location.lng(), place.id ?? null)
      map.setZoom(17)

      const component = (type: string) =>
        place.addressComponents?.find((c: any) => c.types.includes(type))?.longText ?? null
      emit('place', {
        line1: place.formattedAddress?.split(',')[0]?.trim() ?? null,
        city: component('locality') ?? component('administrative_area_level_2'),
      })
    })
    searchHost.value?.appendChild(search)

    mapsReady.value = true
  }
  catch {
    // A bad key, a blocked script, no network: fall back to geolocation only.
    mapsFailed.value = true
  }
}
/* eslint-enable @typescript-eslint/no-explicit-any */

function useMyLocation() {
  notice.value = null
  if (!('geolocation' in navigator)) {
    notice.value = 'Your browser can’t share its location. Search for your address instead.'
    return
  }

  locating.value = true
  navigator.geolocation.getCurrentPosition(
    (position) => {
      locating.value = false
      setPin(position.coords.latitude, position.coords.longitude)
      map?.setZoom(17)
    },
    () => {
      locating.value = false
      notice.value = 'We couldn’t get your location. Check location permission, or search for your address.'
    },
    { enableHighAccuracy: true, timeout: 10_000 },
  )
}

function clearPin() {
  pin.value = null
  if (marker) marker.position = null
}

onMounted(() => {
  if (apiKey) initMap()
})

const showMap = computed(() => !!apiKey && !mapsFailed.value)
</script>

<template>
  <div class="flex w-full flex-col items-start gap-3">
    <div class="flex w-full flex-col gap-1">
      <p class="text-caption font-normal text-graphite">Delivery pin</p>
      <p class="text-caption text-muted">
        Optional. In Accra, a pin lets us send an on-demand courier straight to your door.
      </p>
    </div>

    <template v-if="showMap">
      <div ref="searchHost" class="w-full" />
      <div
        ref="mapHost"
        class="aspect-[4/3] w-full border border-line bg-surface sm:aspect-[16/9]"
        role="application"
        aria-label="Map. Drag the pin, or tap the map, to mark where your parcel should go."
      />
      <p v-if="!mapsReady" class="text-caption text-muted">Loading map…</p>
    </template>

    <div class="flex w-full flex-wrap items-center gap-x-5 gap-y-2">
      <button
        type="button"
        class="caps-label -my-2.5 flex min-h-[44px] items-center gap-1.5 py-2.5 text-ink hover:underline disabled:text-muted"
        :disabled="locating"
        @click="useMyLocation"
      >
        <PhCrosshair :size="14" aria-hidden="true" />
        {{ locating ? 'Finding you…' : 'Use my current location' }}
      </button>

      <span v-if="pin" class="flex items-center gap-1.5 text-caption text-subtle">
        <PhMapPin :size="14" aria-hidden="true" />
        Pinned
        <button type="button" class="underline underline-offset-4 hover:no-underline" @click="clearPin">
          Remove
        </button>
      </span>
    </div>

    <p v-if="notice" class="text-caption text-sale" role="alert">{{ notice }}</p>
  </div>
</template>
