<script setup lang="ts">
import TiptapEditor from '@/components/Admin/TiptapEditor.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    FileText,
    Globe,
    Info,
    MapPin,
    Save,
    Share2,
    ShoppingBag,
    ShoppingCart,
    Smartphone,
    Store,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    settings?: {
        store_name: string;
        store_description: string;
        business_registration: string;
        vat_number: string;
        store_license: string;
        store_email: string;
        support_email: string;
        store_phone: string;
        store_phone_alt: string;
        store_whatsapp: string;
        store_address_1: string;
        store_address_2: string;
        store_city: string;
        store_state: string;
        store_postal_code: string;
        store_country: string;
        store_timezone: string;
        social_facebook: string;
        social_instagram: string;
        social_twitter: string;
        social_linkedin: string;
        social_youtube: string;
        social_tiktok: string;
        social_pinterest: string;
        play_store_url: string;
        app_store_url: string;
        policy_privacy: string;
        policy_terms: string;
        policy_return: string;
        policy_shipping: string;
        checkout_allow_guest: boolean;
        checkout_require_account: boolean;
    };
}

const props = withDefaults(defineProps<Props>(), {
    settings: () => ({
        store_name: '',
        store_description: '',
        business_registration: '',
        vat_number: '',
        store_license: '',
        store_email: '',
        support_email: '',
        store_phone: '',
        store_phone_alt: '',
        store_whatsapp: '',
        store_address_1: '',
        store_address_2: '',
        store_city: '',
        store_state: '',
        store_postal_code: '',
        store_timezone: 'UTC',
        social_facebook: '',
        social_instagram: '',
        social_twitter: '',
        social_linkedin: '',
        social_youtube: '',
        social_tiktok: '',
        social_pinterest: '',
        play_store_url: '',
        app_store_url: '',
        policy_privacy: '',
        policy_terms: '',
        policy_return: '',
        policy_shipping: '',
        checkout_allow_guest: true,
        checkout_require_account: false,
    }),
});

const activeTab = ref('details');

// Shown read-only: the country is decided by the store, not the admin form.
const storeCountry = ref(props.settings.store_country || '');

const form = useForm({
    store_name: props.settings.store_name || '',
    store_description: props.settings.store_description || '',
    business_registration: props.settings.business_registration || '',
    vat_number: props.settings.vat_number || '',
    store_license: props.settings.store_license || '',
    store_email: props.settings.store_email || '',
    support_email: props.settings.support_email || '',
    store_phone: props.settings.store_phone || '',
    store_phone_alt: props.settings.store_phone_alt || '',
    store_whatsapp: props.settings.store_whatsapp || '',
    store_address_1: props.settings.store_address_1 || '',
    store_address_2: props.settings.store_address_2 || '',
    store_city: props.settings.store_city || '',
    store_state: props.settings.store_state || '',
    store_postal_code: props.settings.store_postal_code || '',
    store_timezone: props.settings.store_timezone || 'UTC',
    social_facebook: props.settings.social_facebook || '',
    social_instagram: props.settings.social_instagram || '',
    social_twitter: props.settings.social_twitter || '',
    social_linkedin: props.settings.social_linkedin || '',
    social_youtube: props.settings.social_youtube || '',
    social_tiktok: props.settings.social_tiktok || '',
    social_pinterest: props.settings.social_pinterest || '',
    policy_privacy: props.settings.policy_privacy || '',
    policy_terms: props.settings.policy_terms || '',
    policy_return: props.settings.policy_return || '',
    policy_shipping: props.settings.policy_shipping || '',
    checkout_allow_guest: props.settings.checkout_allow_guest ?? true,
    checkout_require_account: props.settings.checkout_require_account ?? false,
});

const save = () => {
    if (activeTab.value === 'checkout') {
        router.post(
            '/admin/settings/store',
            {
                _section: 'checkout',
                checkout_allow_guest: form.checkout_allow_guest,
                checkout_require_account: form.checkout_require_account,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Toast shown automatically via flash message
                },
                onError: () => {
                    // Errors handled by Inertia form
                },
            },
        );
        return;
    }

    form.post('/admin/settings/store', {
        preserveScroll: true,
        onSuccess: () => {
            // Toast notification will be shown automatically via flash message
        },
        onError: () => {
            // Errors handled by Inertia form
        },
    });
};

watch(
    () => form.store_email,
    (newEmail) => {
        if (!form.support_email || form.support_email === '') {
            form.support_email = newEmail;
        }
    },
);
</script>

<template>
    <AdminLayout :title="$t('Store Configuration')">
        <Head :title="$t('Store Configuration')" />

        <div class="space-y-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ $t('Store Configuration') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{
                            $t(
                                'Manage store details, contact information, social media links, and policies',
                            )
                        }}
                    </p>
                </div>
                <div>
                    <button
                        @click="save"
                        :disabled="form.processing"
                        class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out hover:bg-blue-700 focus:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none active:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Save v-if="!form.processing" class="mr-2 h-4 w-4" />
                        <svg
                            v-else
                            class="mr-2 h-4 w-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{
                            form.processing
                                ? $t('Saving...')
                                : $t('Save Configuration')
                        }}
                    </button>
                </div>
            </div>

            <form @submit.prevent="save" novalidate>
                <div
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <!-- Tabs Navigation -->
                    <div
                        class="border-b border-gray-200 bg-gray-50/50 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <nav
                            class="flex space-x-8 overflow-x-auto px-6"
                            :aria-label="$t('Tabs')"
                        >
                            <button
                                type="button"
                                @click="activeTab = 'details'"
                                :class="[
                                    activeTab === 'details'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300',
                                    'group inline-flex items-center border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap transition-colors duration-200',
                                ]"
                            >
                                <Store
                                    class="mr-2 h-4 w-4"
                                    :class="
                                        activeTab === 'details'
                                            ? 'text-blue-500'
                                            : 'text-gray-400 group-hover:text-gray-500'
                                    "
                                />
                                {{ $t('Store Details') }}
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'contact'"
                                :class="[
                                    activeTab === 'contact'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300',
                                    'group inline-flex items-center border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap transition-colors duration-200',
                                ]"
                            >
                                <MapPin
                                    class="mr-2 h-4 w-4"
                                    :class="
                                        activeTab === 'contact'
                                            ? 'text-blue-500'
                                            : 'text-gray-400 group-hover:text-gray-500'
                                    "
                                />
                                {{ $t('Contact & Address') }}
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'social'"
                                :class="[
                                    activeTab === 'social'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300',
                                    'group inline-flex items-center border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap transition-colors duration-200',
                                ]"
                            >
                                <Share2
                                    class="mr-2 h-4 w-4"
                                    :class="
                                        activeTab === 'social'
                                            ? 'text-blue-500'
                                            : 'text-gray-400 group-hover:text-gray-500'
                                    "
                                />
                                {{ $t('Social Media') }}
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'checkout'"
                                :class="[
                                    activeTab === 'checkout'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300',
                                    'group inline-flex items-center border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap transition-colors duration-200',
                                ]"
                            >
                                <ShoppingBag
                                    class="mr-2 h-4 w-4"
                                    :class="
                                        activeTab === 'checkout'
                                            ? 'text-blue-500'
                                            : 'text-gray-400 group-hover:text-gray-500'
                                    "
                                />
                                {{ $t('Checkout') }}
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'policies'"
                                :class="[
                                    activeTab === 'policies'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300',
                                    'group inline-flex items-center border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap transition-colors duration-200',
                                ]"
                            >
                                <FileText
                                    class="mr-2 h-4 w-4"
                                    :class="
                                        activeTab === 'policies'
                                            ? 'text-blue-500'
                                            : 'text-gray-400 group-hover:text-gray-500'
                                    "
                                />
                                {{ $t('Policies') }}
                            </button>
                        </nav>
                    </div>

                    <!-- Store Details Tab -->
                    <div v-show="activeTab === 'details'" class="p-6 sm:p-8">
                        <div class="space-y-8">
                            <div>
                                <h3
                                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    <Store class="h-5 w-5 text-gray-400" />
                                    {{ $t('Business Information') }}
                                </h3>
                                <div
                                    class="grid grid-cols-1 gap-6 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            for="store_name"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Business Name') }}
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="store_name"
                                            v-model="form.store_name"
                                            type="text"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.store_name"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.store_name }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            for="business_registration"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{
                                                $t(
                                                    'Business Registration Number',
                                                )
                                            }}
                                        </label>
                                        <input
                                            id="business_registration"
                                            v-model="form.business_registration"
                                            type="text"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <p
                                            v-if="
                                                form.errors
                                                    .business_registration
                                            "
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .business_registration
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-6 border-t border-gray-100 pt-6 sm:grid-cols-2 dark:border-gray-700"
                            >
                                <div>
                                    <label
                                        for="vat_number"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('VAT Number') }}
                                    </label>
                                    <input
                                        id="vat_number"
                                        v-model="form.vat_number"
                                        type="text"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <p
                                        v-if="form.errors.vat_number"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.vat_number }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="store_license"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('License Number') }}
                                    </label>
                                    <input
                                        id="store_license"
                                        v-model="form.store_license"
                                        type="text"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <p
                                        v-if="form.errors.store_license"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.store_license }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="store_timezone"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Store Timezone') }}
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div
                                            class="padding-l-3 pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                        >
                                            <Globe
                                                class="h-4 w-4 text-gray-400"
                                            />
                                        </div>
                                        <select
                                            id="store_timezone"
                                            v-model="form.store_timezone"
                                            class="block w-full rounded-lg border border-gray-300 bg-white py-2.5 pr-10 pl-10 text-sm shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                            required
                                        >
                                            <option value="UTC">UTC</option>
                                            <option value="America/New_York">
                                                America/New York
                                            </option>
                                            <option value="America/Chicago">
                                                America/Chicago
                                            </option>
                                            <option value="America/Denver">
                                                America/Denver
                                            </option>
                                            <option value="America/Los_Angeles">
                                                America/Los Angeles
                                            </option>
                                            <option value="Europe/London">
                                                Europe/London
                                            </option>
                                            <option value="Europe/Paris">
                                                Europe/Paris
                                            </option>
                                            <option value="Asia/Dubai">
                                                Asia/Dubai
                                            </option>
                                            <option value="Asia/Kolkata">
                                                Asia/Kolkata
                                            </option>
                                            <option value="Asia/Singapore">
                                                Asia/Singapore
                                            </option>
                                            <option value="Asia/Tokyo">
                                                Asia/Tokyo
                                            </option>
                                            <option value="Australia/Sydney">
                                                Australia/Sydney
                                            </option>
                                        </select>
                                    </div>
                                    <p
                                        v-if="form.errors.store_timezone"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.store_timezone }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label
                                    for="store_description"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t('Business Description') }}
                                </label>
                                <textarea
                                    id="store_description"
                                    v-model="form.store_description"
                                    rows="4"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                    :placeholder="
                                        $t(
                                            'Brief description of your business for SEO purposes...',
                                        )
                                    "
                                ></textarea>
                                <p
                                    v-if="form.errors.store_description"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.store_description }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Address Tab -->
                    <div v-show="activeTab === 'contact'" class="p-6 sm:p-8">
                        <div class="space-y-8">
                            <div>
                                <h3
                                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    <MapPin class="h-5 w-5 text-gray-400" />
                                    {{ $t('Contact Information') }}
                                </h3>
                                <div
                                    class="grid grid-cols-1 gap-6 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            for="store_email"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Primary Email') }}
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="store_email"
                                            v-model="form.store_email"
                                            type="email"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.store_email"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.store_email }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            for="support_email"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Support Email') }}
                                        </label>
                                        <input
                                            id="support_email"
                                            v-model="form.support_email"
                                            type="email"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <p
                                            v-if="form.errors.support_email"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.support_email }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3"
                                >
                                    <div>
                                        <label
                                            for="store_phone"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Phone Number') }}
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="store_phone"
                                            v-model="form.store_phone"
                                            type="tel"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.store_phone"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.store_phone }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            for="store_phone_alt"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Alternate Phone') }}
                                        </label>
                                        <input
                                            id="store_phone_alt"
                                            v-model="form.store_phone_alt"
                                            type="tel"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <p
                                            v-if="form.errors.store_phone_alt"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.store_phone_alt }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            for="store_whatsapp"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('WhatsApp Number') }}
                                        </label>
                                        <input
                                            id="store_whatsapp"
                                            v-model="form.store_whatsapp"
                                            type="tel"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <p
                                            v-if="form.errors.store_whatsapp"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.store_whatsapp }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="border-t border-gray-100 pt-6 dark:border-gray-700"
                            >
                                <h3
                                    class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $t('Store Address') }}
                                </h3>

                                <div class="space-y-6">
                                    <div>
                                        <label
                                            for="store_address_1"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Street Address') }}
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="store_address_1"
                                            v-model="form.store_address_1"
                                            type="text"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.store_address_1"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.store_address_1 }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            for="store_address_2"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Address Line 2') }}
                                        </label>
                                        <input
                                            id="store_address_2"
                                            v-model="form.store_address_2"
                                            type="text"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <p
                                            v-if="form.errors.store_address_2"
                                            class="mt-1 text-sm text-red-600"
                                        >
                                            {{ form.errors.store_address_2 }}
                                        </p>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-6 sm:grid-cols-3"
                                    >
                                        <div>
                                            <label
                                                for="store_city"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                {{ $t('City') }}
                                                <span class="text-red-500"
                                                    >*</span
                                                >
                                            </label>
                                            <input
                                                id="store_city"
                                                v-model="form.store_city"
                                                type="text"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                                required
                                            />
                                            <p
                                                v-if="form.errors.store_city"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.store_city }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="store_state"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                {{ $t('State/Province') }}
                                                <span class="text-red-500"
                                                    >*</span
                                                >
                                            </label>
                                            <input
                                                id="store_state"
                                                v-model="form.store_state"
                                                type="text"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                                required
                                            />
                                            <p
                                                v-if="form.errors.store_state"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.store_state }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="store_postal_code"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                {{ $t('Postal Code') }}
                                                <span class="text-red-500"
                                                    >*</span
                                                >
                                            </label>
                                            <input
                                                id="store_postal_code"
                                                v-model="form.store_postal_code"
                                                type="text"
                                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                                required
                                            />
                                            <p
                                                v-if="
                                                    form.errors
                                                        .store_postal_code
                                                "
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{
                                                    form.errors
                                                        .store_postal_code
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            for="store_country"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Country') }}
                                        </label>
                                        <div
                                            id="store_country"
                                            class="block w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2.5 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                        >
                                            {{ storeCountry }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Tab -->
                    <div v-show="activeTab === 'social'" class="p-6 sm:p-8">
                        <div class="space-y-6">
                            <h3
                                class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                <Share2 class="h-5 w-5 text-gray-400" />
                                {{ $t('Social Media Profiles') }}
                            </h3>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="social_facebook"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Facebook') }}
                                    </label>
                                    <input
                                        id="social_facebook"
                                        v-model="form.social_facebook"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://facebook.com/yourstore"
                                    />
                                    <p
                                        v-if="form.errors.social_facebook"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.social_facebook }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="social_instagram"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Instagram') }}
                                    </label>
                                    <input
                                        id="social_instagram"
                                        v-model="form.social_instagram"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://instagram.com/yourstore"
                                    />
                                    <p
                                        v-if="form.errors.social_instagram"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.social_instagram }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="social_twitter"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Twitter/X') }}
                                    </label>
                                    <input
                                        id="social_twitter"
                                        v-model="form.social_twitter"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://twitter.com/yourstore"
                                    />
                                    <p
                                        v-if="form.errors.social_twitter"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.social_twitter }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="social_linkedin"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('LinkedIn') }}
                                    </label>
                                    <input
                                        id="social_linkedin"
                                        v-model="form.social_linkedin"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://linkedin.com/company/yourstore"
                                    />
                                    <p
                                        v-if="form.errors.social_linkedin"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.social_linkedin }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="social_youtube"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('YouTube') }}
                                    </label>
                                    <input
                                        id="social_youtube"
                                        v-model="form.social_youtube"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://youtube.com/@yourstore"
                                    />
                                    <p
                                        v-if="form.errors.social_youtube"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.social_youtube }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="social_tiktok"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('TikTok') }}
                                    </label>
                                    <input
                                        id="social_tiktok"
                                        v-model="form.social_tiktok"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://tiktok.com/@yourstore"
                                    />
                                    <p
                                        v-if="form.errors.social_tiktok"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.social_tiktok }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="social_pinterest"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Pinterest') }}
                                    </label>
                                    <input
                                        id="social_pinterest"
                                        v-model="form.social_pinterest"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://pinterest.com/yourstore"
                                    />
                                    <p
                                        v-if="form.errors.social_pinterest"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.social_pinterest }}
                                    </p>
                                </div>
                            </div>

                            <!-- Mobile App Links -->
                            <div class="mt-8 space-y-6">
                                <div>
                                    <h3
                                        class="mb-2 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                                    >
                                        <Smartphone
                                            class="h-5 w-5 text-gray-400"
                                        />
                                        {{ $t('Mobile App Links') }}
                                    </h3>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                'Where the Android and iPhone apps can be downloaded. Leave blank until the app is published — the website falls back to the direct Android download while the Play Store link is empty.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="play_store_url"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Google Play URL') }}
                                    </label>
                                    <input
                                        id="play_store_url"
                                        v-model="form.play_store_url"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://play.google.com/store/apps/details?id=com.cartxis.app"
                                    />
                                    <p
                                        v-if="form.errors.play_store_url"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.play_store_url }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="app_store_url"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('App Store URL') }}
                                    </label>
                                    <input
                                        id="app_store_url"
                                        v-model="form.app_store_url"
                                        type="url"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm placeholder-gray-400 shadow-sm transition-shadow duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                                        placeholder="https://apps.apple.com/app/id0000000000"
                                    />
                                    <p
                                        v-if="form.errors.app_store_url"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.app_store_url }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Tab -->
                    <div v-show="activeTab === 'checkout'" class="p-6 sm:p-8">
                        <div class="space-y-8">
                            <div>
                                <h3
                                    class="mb-2 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    <ShoppingCart
                                        class="h-5 w-5 text-gray-400"
                                    />
                                    {{ $t('Checkout Preferences') }}
                                </h3>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            'Configure checkout flow and account requirements used in your storefront',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="space-y-6 border-t border-gray-100 pt-6 dark:border-gray-700"
                            >
                                <!-- Allow Guest Checkout -->
                                <div class="flex items-start">
                                    <div class="flex h-5 items-center">
                                        <input
                                            id="checkout_allow_guest"
                                            v-model="form.checkout_allow_guest"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-blue-600 transition duration-150 ease-in-out focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label
                                            for="checkout_allow_guest"
                                            class="font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $t('Allow Guest Checkout') }}
                                        </label>
                                        <p
                                            class="mt-1 text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    'Allow customers to complete checkout without creating an account. They can optionally create one during checkout.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Require Account Creation -->
                                <div class="flex items-start">
                                    <div class="flex h-5 items-center">
                                        <input
                                            id="checkout_require_account"
                                            v-model="
                                                form.checkout_require_account
                                            "
                                            type="checkbox"
                                            :disabled="
                                                !form.checkout_allow_guest
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-blue-600 transition duration-150 ease-in-out focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label
                                            for="checkout_require_account"
                                            class="font-medium text-gray-700 dark:text-gray-300"
                                            :class="{
                                                'text-gray-400 dark:text-gray-600':
                                                    !form.checkout_allow_guest,
                                            }"
                                        >
                                            {{ $t('Require Account Creation') }}
                                        </label>
                                        <p
                                            class="mt-1 text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    'When enabled, guests must create an account during checkout (cannot proceed without registration).',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Box -->
                            <div
                                class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/50 dark:bg-blue-900/20"
                            >
                                <div class="flex">
                                    <Info
                                        class="mt-0.5 mr-3 h-5 w-5 flex-shrink-0 text-blue-400"
                                    />
                                    <div>
                                        <h3
                                            class="text-sm font-medium text-blue-800 dark:text-blue-200"
                                        >
                                            {{ $t('Configuration Options') }}
                                        </h3>
                                        <div
                                            class="mt-2 text-sm text-blue-700 dark:text-blue-300"
                                        >
                                            <ul
                                                class="list-inside list-disc space-y-1"
                                            >
                                                <li>
                                                    <strong>{{
                                                        $t('Both disabled:')
                                                    }}</strong>
                                                    {{
                                                        $t(
                                                            'Customers must log in or register before checkout (no guest option)',
                                                        )
                                                    }}
                                                </li>
                                                <li>
                                                    <strong>{{
                                                        $t(
                                                            'Guest allowed only:',
                                                        )
                                                    }}</strong>
                                                    {{
                                                        $t(
                                                            'Customers can checkout as guests or create an account (optional)',
                                                        )
                                                    }}
                                                </li>
                                                <li>
                                                    <strong>{{
                                                        $t('Both enabled:')
                                                    }}</strong>
                                                    {{
                                                        $t(
                                                            'Customers can start as guest but must create account during checkout',
                                                        )
                                                    }}
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Policies Tab -->
                    <div v-show="activeTab === 'policies'" class="p-6 sm:p-8">
                        <div class="space-y-8">
                            <div>
                                <h3
                                    class="mb-2 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    <FileText class="h-5 w-5 text-gray-400" />
                                    {{ $t('Store Policies') }}
                                </h3>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            'Define the legal policies for your store to ensure transparency with customers.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="space-y-8 border-t border-gray-100 pt-6 dark:border-gray-700"
                            >
                                <div>
                                    <label
                                        for="policy_privacy"
                                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Privacy Policy') }}
                                    </label>
                                    <TiptapEditor
                                        v-model="form.policy_privacy"
                                        :placeholder="
                                            $t(
                                                'Enter your privacy policy content...',
                                            )
                                        "
                                    />
                                    <p
                                        v-if="form.errors.policy_privacy"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.policy_privacy }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="policy_terms"
                                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Terms & Conditions') }}
                                    </label>
                                    <TiptapEditor
                                        v-model="form.policy_terms"
                                        :placeholder="
                                            $t(
                                                'Enter your terms and conditions...',
                                            )
                                        "
                                    />
                                    <p
                                        v-if="form.errors.policy_terms"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.policy_terms }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="policy_return"
                                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Return Policy') }}
                                    </label>
                                    <TiptapEditor
                                        v-model="form.policy_return"
                                        :placeholder="
                                            $t(
                                                'Enter your return and refund policy...',
                                            )
                                        "
                                    />
                                    <p
                                        v-if="form.errors.policy_return"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.policy_return }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="policy_shipping"
                                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t('Shipping Policy') }}
                                    </label>
                                    <TiptapEditor
                                        v-model="form.policy_shipping"
                                        :placeholder="
                                            $t(
                                                'Enter your shipping terms and conditions...',
                                            )
                                        "
                                    />
                                    <p
                                        v-if="form.errors.policy_shipping"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ form.errors.policy_shipping }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div
                        class="flex items-center justify-end border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out hover:bg-blue-700 focus:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none active:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Save
                                v-if="!form.processing"
                                class="mr-2 h-4 w-4"
                            />
                            <svg
                                v-else
                                class="mr-2 h-4 w-4 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            {{
                                form.processing
                                    ? $t('Saving...')
                                    : $t('Save Configuration')
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
