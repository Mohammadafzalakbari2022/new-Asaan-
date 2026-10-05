<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import { useCurrency } from '@/composables/useCurrency';
import { useI18nStore } from '@/Stores/i18n';

const { formatPrice } = useCurrency();
const { t } = useI18nStore();
const page = usePage();

interface Service {
    id: number;
    name: string;
    slug: string;
    duration_display: string | null;
}

interface Worker {
    id: number;
    name: string;
    phone: string | null;
}

interface BookingEvent {
    id: number;
    from_status: string | null;
    to_status: string;
    note: string | null;
    created_at: string;
    actor?: { id: number; name: string } | null;
}

interface Booking {
    id: number;
    reference: string;
    status: string;
    scheduled_date: string;
    scheduled_slot: string;
    service_name: string;
    price_snapshot: string | number;
    amount_collected: string | number | null;
    payment_method: string;
    address: string;
    city: string | null;
    notes: string | null;
    service?: Service | null;
    worker?: Worker | null;
    events: BookingEvent[];
}

const props = defineProps<{
    booking?: Booking;
    settings?: {
        coverage_note: string | null;
        contact_phone: string | null;
        contact_whatsapp: string | null;
    };
    errors?: Record<string, string>;
}>();

const route = usePage().route;

const form = ref({
    reference: (route().query.reference as string) ?? '',
    phone: (route().query.phone as string) ?? '',
});

const submitted = ref(false);
const posting = ref(false);

const search = () => {
    submitted.value = true;
    posting.value = true;

    router.get(
        '/services/track',
        { reference: form.value.reference, phone: form.value.phone },
        {
            preserveScroll: true,
            onFinish: () => {
                posting.value = false;
                submitted.value = false;
            },
        },
    );
};

const price = computed(() => {
    if (!props.booking) {
        return 0;
    }

    return typeof props.booking.price_snapshot === 'string'
        ? parseFloat(props.booking.price_snapshot)
        : props.booking.price_snapshot;
});

const collected = computed(() => {
    const value = props.booking?.amount_collected;

    if (value === null || value === undefined) {
        return null;
    }

    return typeof value === 'string' ? parseFloat(value) : value;
});

const statusText: Record<string, string> = {
    booked: 'Booked',
    assigned: 'A worker has been assigned',
    in_progress: 'Work in progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

/**
 * The reference alone is not enough, so the phone number is asked for as well
 * and the first thing to fix is focused.
 */
watch(
    () => page.props.errors,
    async (errors) => {
        if (!errors || Object.keys(errors).length === 0) {
            return;
        }

        await nextTick();

        document.getElementById('track-reference')?.focus();
    },
);
</script>

<template>
    <Head :title="$t('Check on a job')" />

    <ThemeLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $t('Check on a job') }}</h1>
            <p class="mt-2 text-gray-600 dark:text-slate-400">
                {{ $t('Enter the reference from your confirmation and the phone number you booked with.') }}
            </p>

            <form class="mt-6 space-y-5 rounded-xl border border-gray-200 dark:border-slate-700 p-6" novalidate @submit.prevent="search">
                <p
                    v-if="errors?.error"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ errors.error }}
                </p>

                <div>
                    <label for="track-reference" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ $t('Reference') }} <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="track-reference"
                        v-model="form.reference"
                        name="reference"
                        type="text"
                        autocomplete="off"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                        :class="errors?.reference ? 'border-red-500' : ''"
                    />
                    <p v-if="errors?.reference" class="mt-1 text-sm text-red-600">{{ errors.reference }}</p>
                </div>

                <div>
                    <label for="track-phone" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                        {{ $t('Phone number') }} <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="track-phone"
                        v-model="form.phone"
                        name="phone"
                        type="tel"
                        autocomplete="tel"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                        :class="errors?.phone ? 'border-red-500' : ''"
                    />
                    <p v-if="errors?.phone" class="mt-1 text-sm text-red-600">{{ errors.phone }}</p>
                </div>

                <button
                    type="submit"
                    class="rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                    :disabled="posting"
                >
                    {{ posting ? $t('Looking...') : $t('Find my job') }}
                </button>
            </form>

            <div v-if="booking" class="mt-8 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ booking.service_name }}</h2>
                    <span class="rounded-full bg-gray-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-gray-700 dark:text-slate-300">
                        {{ $t(statusText[booking.status] ?? booking.status) }}
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ booking.reference }}</p>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Date') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-slate-100"><DateDisplay :value="booking.scheduled_date" /></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Time') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-slate-100">{{ booking.scheduled_slot }}</dd>
                    </div>
                    <div v-if="booking.worker" class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Your worker') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-slate-100">{{ booking.worker.name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Price') }}</dt>
                        <dd class="font-semibold text-gray-900 dark:text-slate-100">{{ formatPrice(price) }}</dd>
                    </div>
                    <div v-if="collected !== null" class="flex justify-between gap-4">
                        <dt class="text-gray-600 dark:text-slate-400">{{ $t('Paid') }}</dt>
                        <dd class="font-semibold text-gray-900 dark:text-slate-100">{{ formatPrice(collected) }}</dd>
                    </div>
                </dl>

                <div v-if="booking.events?.length" class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $t('History') }}</h3>
                    <ol class="mt-3 space-y-3">
                        <li v-for="event in booking.events" :key="event.id" class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-slate-100">
                                {{ $t(statusText[event.to_status] ?? event.to_status) }}
                            </span>
                            <span class="text-gray-500 dark:text-slate-400"> - <DateDisplay :value="event.created_at" time /></span>
                            <p v-if="event.note" class="text-gray-600 dark:text-slate-400">{{ event.note }}</p>
                        </li>
                    </ol>
                </div>

                <p class="mt-6 text-sm text-gray-500 dark:text-slate-400">
                    {{ $t('Need to change something?') }}
                    <a
                        v-if="settings?.contact_phone"
                        :href="`tel:${settings.contact_phone}`"
                        class="font-medium text-gray-900 dark:text-slate-100 underline"
                    >
                        {{ settings.contact_phone }}
                    </a>
                </p>
            </div>

            <p class="mt-8 text-sm text-gray-500 dark:text-slate-400">
                <Link href="/services" class="underline">{{ $t('Back to services') }}</Link>
            </p>
        </div>
    </ThemeLayout>
</template>
