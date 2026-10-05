<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import { useCurrency } from '@/composables/useCurrency';
import { useI18nStore } from '@/Stores/i18n';

const { formatPrice } = useCurrency();
const { t } = useI18nStore();

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
    <Head :title="$t('Booking confirmed')" />

    <ThemeLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="rounded-xl border border-green-200 bg-green-50 p-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $t('Booking confirmed') }}</h1>
                <p class="mt-2 text-gray-700 dark:text-slate-300">
                    {{ $t('Thank you. Keep this reference, you will need it to check on the job.') }}
                </p>

                <p class="mt-4 text-sm text-gray-600 dark:text-slate-400">{{ $t('Your reference') }}</p>
                <p class="text-2xl font-bold tracking-wide text-gray-900 dark:text-slate-100">{{ booking.reference }}</p>
            </div>

            <div class="mt-6 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ booking.service_name }}</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Date') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-slate-100"><DateDisplay :value="booking.scheduled_date" /></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Time') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-slate-100">{{ booking.scheduled_slot }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Address') }}</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-slate-100">
                            {{ booking.address }}<span v-if="booking.city">, {{ booking.city }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Status') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-slate-100">
                            {{ $t(statusText[booking.status] ?? booking.status) }}
                        </dd>
                    </div>
                    <div v-if="booking.worker" class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Your worker') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-slate-100">{{ booking.worker.name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-gray-200 dark:border-slate-700 pt-3">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Price') }}</dt>
                        <dd class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ formatPrice(price) }}</dd>
                    </div>
                </dl>

                <p class="mt-4 rounded-md bg-gray-50 dark:bg-slate-900 p-3 text-sm text-gray-700 dark:text-slate-300">
                    {{ $t('You pay after the work is done. Nothing has been charged.') }}
                </p>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <Link
                    :href="`/services/track?reference=${booking.reference}`"
                    class="rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700"
                >
                    {{ $t('Check on this job') }}
                </Link>
                <Link
                    :href="`/services/${booking.service?.slug ?? ''}`"
                    class="rounded-md border border-gray-300 dark:border-slate-600 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800"
                >
                    {{ $t('Back to services') }}
                </Link>
            </div>
        </div>
    </ThemeLayout>
</template>
