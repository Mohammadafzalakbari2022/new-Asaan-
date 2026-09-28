<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const { formatPrice } = useCurrency();

interface Service {
    id: number;
    name: string;
    slug: string;
    duration_display: string | null;
    service_area: string | null;
}

interface Worker {
    id: number;
    name: string;
    phone: string | null;
}

interface Booking {
    id: number;
    reference: string;
    status: string;
    scheduled_date: string;
    scheduled_slot: string;
    service_name: string;
    price_snapshot: string | number;
    payment_method: string;
    customer_name: string;
    address: string;
    city: string | null;
    service?: Service | null;
    worker?: Worker | null;
}

const props = defineProps<{
    booking: Booking;
    settings: {
        coverage_note: string | null;
        contact_phone: string | null;
        contact_whatsapp: string | null;
    };
}>();

const price = computed(() =>
    typeof props.booking.price_snapshot === 'string'
        ? parseFloat(props.booking.price_snapshot)
        : props.booking.price_snapshot,
);

const statusText: Record<string, string> = {
    booked: 'Booked',
    assigned: 'A worker has been assigned',
    in_progress: 'Work in progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
};
</script>

<template>
    <Head title="Booking confirmed" />

    <ThemeLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="rounded-xl border border-green-200 bg-green-50 p-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ 'Booking confirmed' }}</h1>
                <p class="mt-2 text-gray-700">
                    {{ 'Thank you. Keep this reference, you will need it to check on the job.' }}
                </p>

                <p class="mt-4 text-sm text-gray-600">{{ 'Your reference' }}</p>
                <p class="text-2xl font-bold tracking-wide text-gray-900">{{ booking.reference }}</p>
            </div>

            <div class="mt-6 rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900">{{ booking.service_name }}</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ 'Date' }}</dt>
                        <dd class="font-medium text-gray-900">{{ booking.scheduled_date }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ 'Time' }}</dt>
                        <dd class="font-medium text-gray-900">{{ booking.scheduled_slot }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ 'Address' }}</dt>
                        <dd class="text-right font-medium text-gray-900">
                            {{ booking.address }}<span v-if="booking.city">, {{ booking.city }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ 'Status' }}</dt>
                        <dd class="font-medium text-gray-900">
                            {{ statusText[booking.status] ?? booking.status }}
                        </dd>
                    </div>
                    <div v-if="booking.worker" class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ 'Your worker' }}</dt>
                        <dd class="font-medium text-gray-900">{{ booking.worker.name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-gray-200 pt-3">
                        <dt class="text-gray-600">{{ 'Price' }}</dt>
                        <dd class="text-lg font-semibold text-gray-900">{{ formatPrice(price) }}</dd>
                    </div>
                </dl>

                <p class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                    {{ 'You pay after the work is done. Nothing has been charged.' }}
                </p>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <Link
                    :href="`/services/track?reference=${booking.reference}`"
                    class="rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700"
                >
                    {{ 'Check on this job' }}
                </Link>
                <Link
                    :href="`/services/${booking.service?.slug ?? ''}`"
                    class="rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    {{ 'Back to services' }}
                </Link>
            </div>
        </div>
    </ThemeLayout>
</template>
