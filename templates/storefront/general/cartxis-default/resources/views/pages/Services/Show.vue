<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';
import ServiceCard from '../../components/ServiceCard.vue';
import { useCurrency } from '@/composables/useCurrency';
import DatePicker from '@/components/Calendar/DatePicker.vue';
import { useI18nStore } from '@/Stores/i18n';

const { formatPrice } = useCurrency();
const { t } = useI18nStore();
const page = usePage();

interface TimeSlot {
    label: string;
    start: string;
    end: string;
}

interface Service {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    price: string | number;
    price_unit: string;
    price_display: string;
    duration_minutes: number | null;
    duration_label: string | null;
    duration_display: string | null;
    service_area: string | null;
    includes: string[] | null;
    excludes: string[] | null;
    image_url: string | null;
    category?: { id: number; name: string; slug: string } | null;
}

interface Props {
    service: Service;
    related: Service[];
    booking: {
        enabled: boolean;
        slots: TimeSlot[];
        earliest_date: string;
        latest_date: string;
    };
    settings: {
        coverage_note: string | null;
        contact_phone: string | null;
        contact_whatsapp: string | null;
        require_login_to_book: boolean;
    };
    errors?: Record<string, string>;
    flash?: { success?: string };
}

const props = defineProps<Props>();

/**
 * One key for this filled-in form.
 *
 * Sent with the booking so that a double tap, or a form sent again after the
 * connection dropped, is recognised as the same request rather than a second
 * job. A new one is made after every successful booking.
 */
const requestToken = ref(crypto.randomUUID());

const form = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    address: '',
    city: '',
    notes: '',
    scheduled_date: props.booking.earliest_date,
    scheduled_slot: props.booking.slots[0]?.label ?? '',
    request_token: requestToken.value,
});

const price = computed(() =>
    typeof props.service.price === 'string' ? parseFloat(props.service.price) : props.service.price,
);

const unit = computed(() => props.service.price_display.split(' ').slice(1).join(' '));

const included = computed(() => props.service.includes ?? []);
const excluded = computed(() => props.service.excludes ?? []);

/**
 * Error messages belong under the box they belong to, so the form points at
 * itself: check everything at once, then move to the first thing to fix.
 */
const fieldOrder = [
    'customer_name',
    'customer_phone',
    'customer_email',
    'address',
    'city',
    'scheduled_date',
    'scheduled_slot',
    'notes',
    'form',
] as const;

const fieldFor = (name: string) => `${name}-field`;

watch(
    () => page.props.errors,
    async (errors) => {
        if (!errors || Object.keys(errors).length === 0) {
            return;
        }

        await nextTick();

        const first = fieldOrder.find((name) => errors[name]);

        if (first) {
            document.getElementById(fieldFor(first))?.focus();
        }
    },
);

const submit = () => {
    form.post(`/services/${props.service.slug}/book`, {
        preserveScroll: true,
        onSuccess: () => {
            // A fresh form gets a fresh key, so the next booking is its own.
            form.reset();
            form.request_token = crypto.randomUUID();
        },
    });
};
</script>

<template>
    <Head :title="service.name" />

    <ThemeLayout>
        <div class="bg-gray-50 dark:bg-slate-900 border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <nav class="flex items-center space-x-2 text-sm text-gray-500 dark:text-slate-400">
                    <Link href="/" class="hover:text-gray-700">{{ $t('Home') }}</Link>
                    <span>/</span>
                    <Link href="/services" class="hover:text-gray-700">{{ $t('Services') }}</Link>
                    <span>/</span>
                    <span class="text-gray-700 dark:text-slate-300">{{ service.name }}</span>
                </nav>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2">
                <div>
                    <img
                        v-if="service.image_url"
                        :src="service.image_url"
                        :alt="service.name"
                        class="w-full rounded-xl object-cover"
                    />

                    <p
                        v-if="service.category"
                        class="mt-6 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-slate-400"
                    >
                        {{ service.category.name }}
                    </p>

                    <h1 class="mt-1 text-3xl font-bold text-gray-900 dark:text-slate-100">{{ service.name }}</h1>

                    <p v-if="service.short_description" class="mt-3 text-lg text-gray-700 dark:text-slate-300">
                        {{ service.short_description }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-slate-400">
                        <span class="text-2xl font-semibold text-gray-900 dark:text-slate-100">
                            {{ formatPrice(price) }}
                            <span class="text-base font-normal text-gray-500 dark:text-slate-400">{{ unit }}</span>
                        </span>
                        <span v-if="service.duration_display">
                            {{ $t('Takes about {duration}', { duration: service.duration_display }) }}
                        </span>
                        <span v-if="service.service_area">{{ service.service_area }}</span>
                    </div>

                    <div
                        v-if="service.description"
                        class="prose prose-sm mt-6 max-w-none text-gray-700 dark:text-slate-300"
                        v-html="service.description"
                    />

                    <div v-if="included.length" class="mt-8">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ $t('What is included') }}</h2>
                        <ul class="mt-3 space-y-2">
                            <li v-for="item in included" :key="item" class="flex gap-2 text-sm text-gray-700 dark:text-slate-300">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="excluded.length" class="mt-6">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ $t('Not included') }}</h2>
                        <ul class="mt-3 space-y-2">
                            <li v-for="item in excluded" :key="item" class="flex gap-2 text-sm text-gray-600 dark:text-slate-400">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div>
                    <div class="rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $t('Book this service') }}</h2>

                        <p v-if="!booking.enabled" class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                            {{ $t('Online booking is not available for this service right now.') }}
                            <a
                                v-if="settings.contact_phone"
                                :href="`tel:${settings.contact_phone}`"
                                class="font-medium underline"
                            >
                                {{ settings.contact_phone }}
                            </a>
                        </p>

                        <form v-else class="mt-5 space-y-5" novalidate @submit.prevent="submit">
                            <p
                                v-if="errors?.form"
                                class="rounded-md bg-red-50 p-3 text-sm text-red-700"
                            >
                                {{ errors.form }}
                            </p>

                            <div>
                                <label for="customer_name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ $t('Your name') }} <span class="text-red-600">*</span>
                                </label>
                                <input
                                    id="customer_name-field"
                                    v-model="form.customer_name"
                                    name="customer_name"
                                    type="text"
                                    autocomplete="name"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    :class="errors?.customer_name ? 'border-red-500' : ''"
                                />
                                <p v-if="errors?.customer_name" class="mt-1 text-sm text-red-600">
                                    {{ errors.customer_name }}
                                </p>
                            </div>

                            <div>
                                <label for="customer_phone" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ $t('Phone number') }} <span class="text-red-600">*</span>
                                </label>
                                <input
                                    id="customer_phone-field"
                                    v-model="form.customer_phone"
                                    name="customer_phone"
                                    type="tel"
                                    autocomplete="tel"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    :class="errors?.customer_phone ? 'border-red-500' : ''"
                                />
                                <p v-if="errors?.customer_phone" class="mt-1 text-sm text-red-600">
                                    {{ errors.customer_phone }}
                                </p>
                            </div>

                            <div>
                                <label for="customer_email" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ $t('Email') }}
                                </label>
                                <input
                                    id="customer_email-field"
                                    v-model="form.customer_email"
                                    name="customer_email"
                                    type="email"
                                    autocomplete="email"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    :class="errors?.customer_email ? 'border-red-500' : ''"
                                />
                                <p v-if="errors?.customer_email" class="mt-1 text-sm text-red-600">
                                    {{ errors.customer_email }}
                                </p>
                            </div>

                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ $t('Address') }} <span class="text-red-600">*</span>
                                </label>
                                <textarea
                                    id="address-field"
                                    v-model="form.address"
                                    name="address"
                                    rows="2"
                                    autocomplete="street-address"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    :class="errors?.address ? 'border-red-500' : ''"
                                />
                                <p v-if="errors?.address" class="mt-1 text-sm text-red-600">
                                    {{ errors.address }}
                                </p>
                            </div>

                            <div>
                                <label for="city" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ $t('City') }}
                                </label>
                                <input
                                    id="city-field"
                                    v-model="form.city"
                                    name="city"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    :class="errors?.city ? 'border-red-500' : ''"
                                />
                                <p v-if="errors?.city" class="mt-1 text-sm text-red-600">
                                    {{ errors.city }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="scheduled_date" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                        {{ $t('Date') }} <span class="text-red-600">*</span>
                                    </label>
                                    <DatePicker
                                        id="scheduled_date-field"
                                        v-model="form.scheduled_date"
                                        name="scheduled_date"
                                        :min="booking.earliest_date"
                                        :max="booking.latest_date"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                        :class="errors?.scheduled_date ? 'border-red-500' : ''"
                                    />
                                    <p v-if="errors?.scheduled_date" class="mt-1 text-sm text-red-600">
                                        {{ errors.scheduled_date }}
                                    </p>
                                </div>

                                <div>
                                    <label for="scheduled_slot" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                        {{ $t('Time') }} <span class="text-red-600">*</span>
                                    </label>
                                    <select
                                        id="scheduled_slot-field"
                                        v-model="form.scheduled_slot"
                                        name="scheduled_slot"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                        :class="errors?.scheduled_slot ? 'border-red-500' : ''"
                                    >
                                        <option v-for="slot in booking.slots" :key="slot.label" :value="slot.label">
                                            {{ slot.label }} ({{ slot.start }} - {{ slot.end }})
                                        </option>
                                    </select>
                                    <p v-if="errors?.scheduled_slot" class="mt-1 text-sm text-red-600">
                                        {{ errors.scheduled_slot }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                                    {{ $t('Notes for the worker') }}
                                </label>
                                <textarea
                                    id="notes-field"
                                    v-model="form.notes"
                                    name="notes"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                    :class="errors?.notes ? 'border-red-500' : ''"
                                />
                                <p v-if="errors?.notes" class="mt-1 text-sm text-red-600">
                                    {{ errors.notes }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="w-full rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? $t('Sending...') : $t('Book now') }}
                            </button>

                            <p class="text-center text-xs text-gray-500 dark:text-slate-400">
                                {{ $t('You pay after the work is done. No card needed.') }}
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <div v-if="related.length" class="mt-16">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $t('You may also need') }}</h2>
                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <ServiceCard v-for="item in related" :key="item.id" :service="item" />
                </div>
            </div>
        </div>
    </ThemeLayout>
</template>
