<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    Crosshair,
    MapPin,
    Navigation,
    Phone,
    RefreshCw,
    Truck,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface StatusBadge {
    label: string;
    class: string;
}

interface BoardDelivery {
    id: number;
    status: string;
    status_badge: StatusBadge;
    priority: string;
    shipment_number: string | null;
    order_number: string | null;
    customer_phone: string | null;
    scheduled_date: string | null;
    cod_amount: number | null;
    destination: {
        lat: number | null;
        lng: number | null;
        label: string;
    } | null;
    driver: { id: number; name: string; phone: string | null } | null;
    driver_location: {
        lat: number;
        lng: number;
        live_at: string | null;
        live: boolean;
    } | null;
}

const props = defineProps<{
    activeDeliveries: BoardDelivery[];
}>();

const mapEl = ref<HTMLElement | null>(null);
const map = ref<Leaflet.Map | null>(null);
const deliveryMarkers = ref<Map<number, Leaflet.Marker>>(new Map());
const driverMarkers = ref<Map<string, Leaflet.Marker>>(new Map());
const selectedId = ref<number | null>(null);
const updatedAt = ref<Date | null>(null);
const lastUpdateText = ref<string>('—');
let pollTimer: ReturnType<typeof setInterval> | null = null;
let fitDone = false;

const sorted = computed(() => {
    return [...props.activeDeliveries].sort((a, b) => {
        const rank: Record<string, number> = { high: 0, normal: 1 };
        return (rank[a.priority] ?? 1) - (rank[b.priority] ?? 1);
    });
});

const liveCount = computed(() => {
    return props.activeDeliveries.filter((d) => d.driver_location?.live).length;
});

const destinationIcon = Leaflet.divIcon({
    className: 'board-marker',
    html: '<div class="board-pin board-pin-dest"></div>',
    iconSize: [22, 22],
    iconAnchor: [11, 22],
});

function driverIcon(live: boolean): Leaflet.DivIcon {
    return Leaflet.divIcon({
        className: 'board-marker',
        html: `<div class="board-pin board-pin-driver${live ? ' board-pin-live' : ''}"></div>`,
        iconSize: [20, 20],
        iconAnchor: [10, 10],
    });
}

function escapeHtml(value: unknown): string {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function popupHtml(item: BoardDelivery): string {
    const driver = item.driver?.name ?? 'Unassigned';
    const liveFlag = item.driver_location?.live
        ? '<span style="color:#16a34a;font-weight:600">Live</span>'
        : '<span style="color:#6b7280">Offline</span>';
    const reason = item.destination?.label
        ? escapeHtml(item.destination.label)
        : 'No map pin — call the customer';

    return `
    <div style="min-width:180px">
      <div style="font-weight:700;margin-bottom:2px">${escapeHtml(item.shipment_number ?? 'Shipment')}</div>
      <div style="font-size:12px;color:#6b7280;margin-bottom:6px">${escapeHtml(item.order_number ?? '')}</div>
      <div style="font-size:13px;margin-bottom:4px"><b>${escapeHtml(driver)}</b> &middot; ${liveFlag}</div>
      <div style="font-size:12px;color:#374151;margin-bottom:6px">${escapeHtml(reason)}</div>
      <div style="font-size:12px">
        <span style="color:#111827">${escapeHtml(item.customer_phone ?? 'No phone')}</span>
        &middot; COD ${item.cod_amount != null ? '$' + item.cod_amount : '—'}
      </div>
    </div>`;
}

function initMap(): void {
    if (!mapEl.value) return;

    map.value = Leaflet.map(mapEl.value, {
        zoomControl: true,
        scrollWheelZoom: false,
    });

    Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map.value);

    map.value.setView([34.5553, 69.2075], 12);

    renderMarkers();
}

function renderMarkers(): void {
    if (!map.value) return;

    // Drop-off pins
    const seenDeliveries = new Set<number>();

    for (const item of props.activeDeliveries) {
        seenDeliveries.add(item.id);

        const destinationAt =
            item.destination?.lat != null && item.destination?.lng != null
                ? Leaflet.latLng(item.destination.lat, item.destination.lng)
                : null;

        if (destinationAt) {
            const marker = deliveryMarkers.value.get(item.id);
            if (marker) {
                marker.setLatLng(destinationAt);
            } else {
                const created = Leaflet.marker(destinationAt, {
                    icon: destinationIcon,
                }).addTo(map.value);
                created.bindPopup(() => popupHtml(item));
                deliveryMarkers.value.set(item.id, created);
            }
        }

        const location = item.driver_location;
        if (location) {
            const key = String(item.driver?.id ?? item.id);
            const icon = driverIcon(location.live);
            const driverAt = Leaflet.latLng(location.lat, location.lng);
            const existing = driverMarkers.value.get(key);

            if (existing) {
                existing.setLatLng(driverAt);
                existing.setIcon(icon);
            } else {
                const created = Leaflet.marker(driverAt, { icon }).addTo(
                    map.value,
                );
                created.bindPopup(() => popupHtml(item));
                driverMarkers.value.set(key, created);
            }
        }
    }

    // Remove markers that are no longer present
    for (const [id, marker] of deliveryMarkers.value) {
        if (!seenDeliveries.has(id)) {
            map.value.removeLayer(marker);
            deliveryMarkers.value.delete(id);
        }
    }

    for (const [key, marker] of driverMarkers.value) {
        const stillThere = props.activeDeliveries.some(
            (d) => d.driver_location && String(d.driver?.id ?? d.id) === key,
        );
        if (!stillThere) {
            map.value.removeLayer(marker);
            driverMarkers.value.delete(key);
        }
    }

    if (!fitDone) {
        fitBounds();
        fitDone = true;
    }
}

function fitBounds(): void {
    if (!map.value) return;

    const points: Leaflet.LatLng[] = [];

    for (const item of props.activeDeliveries) {
        if (item.destination?.lat != null && item.destination?.lng != null) {
            points.push(
                Leaflet.latLng(item.destination.lat, item.destination.lng),
            );
        }
        if (item.driver_location) {
            points.push(
                Leaflet.latLng(
                    item.driver_location.lat,
                    item.driver_location.lng,
                ),
            );
        }
    }

    if (points.length > 0) {
        map.value.fitBounds(Leaflet.latLngBounds(points).pad(0.2));
    } else {
        map.value.setView([34.5553, 69.2075], 12);
    }
}

function goTo(item: BoardDelivery): void {
    selectedId.value = item.id;

    const target = item.driver_location?.live
        ? Leaflet.latLng(item.driver_location.lat, item.driver_location.lng)
        : item.destination?.lat != null && item.destination?.lng != null
          ? Leaflet.latLng(item.destination.lat, item.destination.lng)
          : null;

    if (target && map.value) {
        map.value.flyTo(target, Math.max(map.value.getZoom(), 14));
    }
}

watch(() => props.activeDeliveries, renderMarkers);

function poll(): void {
    router.reload({
        only: ['activeDeliveries'],
        preserveState: true,
        preserveScroll: true,
    });
}

function tick(): void {
    if (!updatedAt.value) return;
    const elapsed = Math.max(
        0,
        Math.floor((Date.now() - updatedAt.value.getTime()) / 1000),
    );
    lastUpdateText.value =
        elapsed < 60 ? `${elapsed}s ago` : `${Math.floor(elapsed / 60)}m ago`;
}

onMounted(() => {
    initMap();
    updatedAt.value = new Date();
    lastUpdateText.value = 'just now';

    pollTimer = window.setInterval(() => {
        poll();
        tick();
    }, 20000);
});

onBeforeUnmount(() => {
    if (pollTimer !== null) window.clearInterval(pollTimer);
    map.value?.remove();
});
</script>

<template>
    <Head title="Delivery Board" />

    <AdminLayout title="Delivery Board">
        <div class="flex flex-col gap-4">
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-background p-4"
            >
                <div>
                    <h2 class="text-sm font-semibold text-foreground">
                        Live deliveries
                    </h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ props.activeDeliveries.length }} on the road ·
                        {{ liveCount }} sharing live location
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <span>Updated {{ lastUpdateText }}</span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 font-semibold text-foreground transition-colors hover:bg-muted"
                        @click="poll"
                    >
                        <RefreshCw class="h-3.5 w-3.5" /> Refresh
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 font-semibold text-foreground transition-colors hover:bg-muted"
                        @click="fitBounds"
                    >
                        <Crosshair class="h-3.5 w-3.5" /> Zoom to all
                    </button>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-border bg-background"
            >
                <div class="flex flex-col xl:flex-row">
                    <div ref="mapEl" class="board-map xl:w-3/5" />

                    <aside
                        class="flex xl:max-h-[calc(100dvh-16rem)] xl:w-2/5 xl:overflow-y-auto"
                    >
                        <div class="w-full divide-y divide-gray-100">
                            <template v-if="sorted.length">
                                <button
                                    v-for="item in sorted"
                                    :key="item.id"
                                    type="button"
                                    class="flex w-full items-start gap-3 p-4 text-left transition-colors hover:bg-gray-50"
                                    :class="
                                        selectedId === item.id
                                            ? 'bg-blue-50/60'
                                            : 'bg-background'
                                    "
                                    @click="goTo(item)"
                                >
                                    <span
                                        class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg"
                                        :class="
                                            item.priority === 'high'
                                                ? 'bg-amber-100 text-amber-700'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        <Truck class="h-4 w-4" />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <span
                                                class="truncate text-sm font-semibold text-foreground"
                                            >
                                                {{ item.shipment_number }}
                                            </span>
                                            <span
                                                v-if="item.status_badge"
                                                :class="item.status_badge.class"
                                                class="inline-flex flex-none rounded-full px-2 py-0.5 text-xs font-medium"
                                            >
                                                {{ item.status_badge.label }}
                                            </span>
                                        </span>
                                        <span
                                            class="mt-0.5 block truncate text-xs text-muted-foreground"
                                        >
                                            {{
                                                item.driver?.name ??
                                                'Unassigned'
                                            }}
                                        </span>
                                        <span
                                            class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1"
                                            >
                                                <Navigation class="h-3 w-3" />
                                                {{
                                                    item.driver
                                                        ? 'Live'
                                                        : 'No location'
                                                }}
                                            </span>
                                            <span
                                                v-if="item.customer_phone"
                                                class="inline-flex items-center gap-1"
                                            >
                                                <Phone class="h-3 w-3" />
                                                {{ item.customer_phone }}
                                            </span>
                                            <span
                                                v-if="item.destination?.label"
                                                class="inline-flex items-center gap-1"
                                            >
                                                <MapPin class="h-3 w-3" />
                                                {{
                                                    item.destination.label
                                                        .length > 42
                                                        ? item.destination.label.slice(
                                                              0,
                                                              42,
                                                          ) + '…'
                                                        : item.destination.label
                                                }}
                                            </span>
                                        </span>
                                    </span>
                                </button>
                            </template>
                            <p
                                v-else
                                class="px-4 py-16 text-center text-sm text-muted-foreground"
                            >
                                No active deliveries. Assign a shipment to a
                                driver to see it here.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
.board-map {
    height: 480px;
    z-index: 0;
}

.board-marker {
    background: transparent;
    border: none;
}

.board-pin {
    border-radius: 9999px;
    border: 2px solid #fff;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.35);
}

.board-pin-dest {
    width: 16px;
    height: 16px;
    background-color: #3b82f6;
}

.board-pin-driver {
    width: 18px;
    height: 18px;
    background-color: #9ca3af;
    border-radius: 6px;
}

.board-pin-live {
    background-color: #16a34a;
    box-shadow:
        0 0 0 4px rgb(22 163 74 / 0.25),
        0 2px 6px rgb(0 0 0 / 0.35);
}
</style>
