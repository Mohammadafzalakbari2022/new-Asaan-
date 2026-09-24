<script setup lang="ts">
import Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { MapPin } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    deliveryId: number;
    latitude: number | null;
    longitude: number | null;
    directionsQuery?: string;
}>();

const mapEl = ref<HTMLElement | null>(null);
const map = ref<Leaflet.Map | null>(null);
const destinationMarker = ref<Leaflet.Marker | null>(null);
const liveMarker = ref<Leaflet.Marker | null>(null);
const liveCircle = ref<Leaflet.Circle | null>(null);
const sharing = ref(false);
const sharingError = ref<string | null>(null);
const lastSentAt = ref<number>(0);
let watchId: number | null = null;
let sendTimer: ReturnType<typeof setInterval> | null = null;

const defaultCenter: Leaflet.LatLngTuple = [34.5553, 69.2075];

const destinationIcon = Leaflet.divIcon({
    className: 'delivery-map-pin',
    html: '<div class="delivery-pin delivery-pin-dest"></div>',
    iconSize: [24, 24],
    iconAnchor: [12, 24],
});

const liveIcon = Leaflet.divIcon({
    className: 'delivery-map-pin',
    html: '<div class="delivery-pin delivery-pin-live"></div>',
    iconSize: [18, 18],
    iconAnchor: [9, 9],
});

const directionsUrl = computed(() => {
    if (props.latitude !== null && props.longitude !== null) {
        return `https://www.google.com/maps/dir/?api=1&destination=${props.latitude},${props.longitude}`;
    }
    if (props.directionsQuery) {
        return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(props.directionsQuery)}`;
    }
    return 'https://www.google.com/maps';
});

function initMap(): void {
    if (!mapEl.value) return;

    map.value = Leaflet.map(mapEl.value, {
        zoomControl: true,
        scrollWheelZoom: false,
    });

    Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map.value);

    let center: Leaflet.LatLngTuple = defaultCenter;
    let zoom = 13;

    if (props.latitude !== null && props.longitude !== null) {
        center = [props.latitude, props.longitude];
        zoom = 15;
        destinationMarker.value = Leaflet.marker(center, {
            icon: destinationIcon,
        }).addTo(map.value);
    }

    map.value.setView(center, zoom);
}

function drawLive(
    latitude: number,
    longitude: number,
    accuracy: number | null,
): void {
    if (!map.value) return;

    const point = Leaflet.latLng(latitude, longitude);

    if (!liveMarker.value) {
        liveMarker.value = Leaflet.marker(point, { icon: liveIcon }).addTo(
            map.value,
        );
    } else {
        liveMarker.value.setLatLng(point);
    }

    if (accuracy !== null) {
        if (!liveCircle.value) {
            liveCircle.value = Leaflet.circle(point, {
                radius: accuracy,
                className: 'delivery-live-circle',
            }).addTo(map.value);
        } else {
            liveCircle.value.setLatLng(point).setRadius(accuracy);
        }
    }
}

async function sendLocation(position: GeolocationPosition): Promise<void> {
    if (Date.now() - lastSentAt.value < 15000) return;

    lastSentAt.value = Date.now();
    sharingError.value = null;

    try {
        const csrf =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '';

        await fetch(`/delivery/deliveries/${props.deliveryId}/location`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy ?? null,
            }),
        });
    } catch {
        sharingError.value =
            'Could not reach the server. It will retry on the next ping.';
    }
}

function onPosition(position: GeolocationPosition): void {
    drawLive(
        position.coords.latitude,
        position.coords.longitude,
        position.coords.accuracy ?? null,
    );

    if (sharing.value) {
        void sendLocation(position);
    }
}

function onPositionError(): void {
    sharingError.value =
        'Could not read your location. Check that location permission is on.';
}

function startSharing(): void {
    sharingError.value = null;

    if (!('geolocation' in navigator)) {
        sharing.value = false;
        sharingError.value = 'Live location is not supported in this browser.';
        return;
    }

    if (sharing.value) {
        stopSharing();
        return;
    }

    sharing.value = true;
    lastSentAt.value = 0;

    watchId = navigator.geolocation.watchPosition(onPosition, onPositionError, {
        enableHighAccuracy: true,
        maximumAge: 10000,
        timeout: 20000,
    });

    sendTimer = window.setInterval(() => {
        if (sharing.value) {
            lastSentAt.value = 0;
        }
    }, 15000);
}

function stopSharing(): void {
    sharing.value = false;
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    if (sendTimer !== null) {
        window.clearInterval(sendTimer);
        sendTimer = null;
    }
}

onMounted(() => {
    initMap();

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) =>
                drawLive(
                    position.coords.latitude,
                    position.coords.longitude,
                    position.coords.accuracy ?? null,
                ),
            () => {},
            { enableHighAccuracy: true, timeout: 10000 },
        );
    }
});

onBeforeUnmount(() => {
    stopSharing();
    map.value?.remove();
});
</script>

<template>
    <section class="rounded-xl border border-border bg-background p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-foreground">Location</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Blue pin is the drop-off point. Share your position so
                    dispatch can see you live.
                </p>
            </div>
            <a
                :href="directionsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-muted"
            >
                <MapPin class="h-3.5 w-3.5" />
                Open in Google Maps
            </a>
        </div>

        <div
            ref="mapEl"
            class="delivery-live-map mt-3 h-64 w-full rounded-lg"
        />

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p v-if="sharingError" class="text-xs font-medium text-destructive">
                {{ sharingError }}
            </p>
            <p v-else-if="sharing" class="text-xs text-muted-foreground">
                Sending a ping every 15 seconds while you are on the move.
            </p>
            <p v-else class="text-xs text-muted-foreground">
                Turn sharing on while you are on the way to the customer.
            </p>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="
                    sharing
                        ? 'border-destructive/40 bg-destructive/10 text-destructive hover:bg-destructive/20'
                        : 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                "
                @click="startSharing"
            >
                {{ sharing ? 'Stop sharing' : 'Share live location' }}
            </button>
        </div>
    </section>
</template>

<style>
.delivery-live-map {
    z-index: 0;
}

.delivery-pin {
    border-radius: 9999px;
    border: 2px solid #fff;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.3);
}

.delivery-pin-dest {
    width: 16px;
    height: 16px;
    background-color: #3b82f6;
}

.delivery-pin-live {
    width: 12px;
    height: 12px;
    background-color: #ef4444;
}

.delivery-live-circle {
    fill: rgb(239 68 68 / 0.2);
    stroke: #ef4444;
}
</style>
