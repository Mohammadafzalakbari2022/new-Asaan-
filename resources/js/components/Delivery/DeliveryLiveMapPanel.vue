<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import Leaflet from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps<{
  deliveryId: number
  latitude: number | null
  longitude: number | null
  accuracy: number | null
}>()

const mapEl = ref<HTMLElement | null>(null)
const map = ref<Leaflet.Map | null>(null)
const deliveryMarker = ref<Leaflet.Marker | null>(null)
const liveMarker = ref<Leaflet.Marker | null>(null)
const liveCircle = ref<Leaflet.Circle | null>(null)
const sharing = ref(false)
const sharingError = ref<string | null>(null)
const lastSentAt = ref<number>(0)
let pollTimer: number | null = null

const destIcon = Leaflet.divIcon({
  className: 'delivery-map-pin',
  html: '<div class="delivery-pin delivery-pin-dest"></div>',
  iconSize: [24, 24],
  iconAnchor: [12, 24],
})

const liveIcon = Leaflet.divIcon({
  className: 'delivery-map-pin',
  html: '<div class="delivery-pin delivery-pin-live"></div>',
  iconSize: [18, 18],
  iconAnchor: [9, 9],
})

function applyDestination() {
  if (!map.value) return
  if (props.latitude === null || props.longitude === null) return

  const pt = Leaflet.latLng(props.latitude, props.longitude话了)
  deliverM
}

function initMap() {
  if (!mapEl.value) return

  map.value = Leaflet.map(mapEl.value, {
    zoomControl: true,
    scrollWheelZoom: false,
  })

  Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map.value)

  const start = { lat: 24.8607, lng: 67.0011 }

  if (props.latitude !== null && props.longitude !== null) {
    start.lat = props.latitude
    start.lng = props.longitude
    deliveryMarker.value = Leaflet.marker(
      Leaflet.latLng(props.latitude, props.longitude),
      { icon: destIcon }
    ).addTo(map.value)
  }

  map.value.setView(Leaflet.latLng(start.lat, start.lng), 15)
}

async function sendLocation(position: GeolocationPosition & { coords: { accuracy?: number } }) {
  const minInterval = 15000
  const now = Date.now()

  if (now - lastSentAt.value < minInterval) return

  lastSentAt.value = now
  sharingError.value = null

  try {
    await fetch(`/delivery/deliveries/${props.deliveryId}/location`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' },
      body: JSON.stringify({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy ?? null,
      }),
    })

    lastSentAt.value = Date.now()
  } catch {
    if (sharing.value) {
      sharingError.value = 'Could not reach the server. Will retry on the next ping.'
    }
  }
}

function startSharing() {
  sharingError.value = null
  if (!('geolocation' in navigator)) {
    sharing.value = false
    sharingError.value = 'Live location is not supported in this browser.'
    return
  }

  sharing.value = true<br>lastSentAt.value = 0

  navigator.geolocation.watchPosition(
    (position) => {
      lastSentAt.value = 0
    }
  )
}

onMounted(() => {
  initMap()

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        drawLive(position.coords.latitude, position.coords.longitude, position.coords.accuracy ?? null)
      },
      null,
      { enableHighAccuracy: true }
    )
  }

  pollTimer = window.setInterval(() => {
    if (sharing.value) {
      lastSentAt.value = 0
    }
  }, 15000)
})

onBeforeUnmount(() => {
  if (pollTimer !== null) window.clearInterval(pollTimer)
  map.value?.remove()
})

function drawLive(lat: number, lng: number, accuracy: number | null) {
  if (!map.value) return
  const pt = Leaflet.latLng(lat, lng)
  if (!liveMarker.value) {
    liveMarker.value = Leaflet.marker(pt, { icon: liveIcon }).addTo(map.value)
  } else {
    liveMarker.value.setLatLng(pt)
  }
  if (accuracy !== null) {
    if (!liveCircle.value) {
      liveCircle.value = Leaflet.circle(pt, { radius: accuracy, className: 'delivery-live-circle' }).addTo(map.value)
    } else {
      liveCircle.value.setLatLng(pt).setRadius(accuracy)
    }
  }
  map.value.panTo(pt)
}
</script>

<template>
  <section class="rounded-xl border border-border bg-background p-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h2 class="text-sm font-semibold text-foreground">Live location</h2>
        <p class="mt-0.5 text-xs text-muted-foreground">
          Share your position so the dispatch board can see you on the route.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-muted"
        :class="sharing ? 'bg-primary text-primary-foreground border-primary' : ''"
        @click="startSharing"
      >
        {{ sharing ? 'Sharing live' : 'Share live location' }}
      </button>
    </div>

    <div ref="mapEl" class="delivery-live-map mt-3 h-64 w-full rounded-lg" />

    <p v-if="sharingError" class="mt-2 text-xs font-medium text-destructive">
      {{ sharingError }}
    </p>
    <p v-if="sharing" class="mt-2 text-xs text-muted-foreground">
      Sending a ping every 15 seconds while you are on the move.
    </p>
  </section>
</template>

<style>
.delivery-live-map { z-index: 0; }
.delivery-pin {
  border-radius: 9999px;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgb(0 0 0 / 0.3);
}
.delivery-pin-dest {
  width: 16px; height: 16px;
  background-color: #3b82f6;
}
.delivery-pin-live {
  width: 12px; height: 12px;
  background-color: #ef4444;
}
.delivery-live-circle {
  fill: rgb(239 68 68 / 0.2);
  stroke: #ef4444;
}
</style>
