<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ConfirmDeleteModal from '@/components/Admin/ConfirmDeleteModal.vue';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import { useCurrency } from '@/composables/useCurrency';

interface CustomerGroup {
    id: number;
    name: string;
    color: string;
}

interface CustomerAddress {
    id: number;
    type: string;
    first_name: string;
    last_name: string;
    company: string | null;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    phone: string | null;
    is_default_shipping: boolean;
    is_default_billing: boolean;
    formatted_address: string;
}

interface CustomerNote {
    id: number;
    note: string;
    created_at: string;
    user: {
        name: string;
    };
}

interface Customer {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    customer_group_id: number;
    company_name: string | null;
    tax_id: string | null;
    is_active: boolean;
    is_verified: boolean;
    is_guest: boolean;
    newsletter_subscribed: boolean;
    total_orders: number;
    total_spent: number;
    notes: string | null;
    created_at: string;
    updated_at: string;
    customer_group: CustomerGroup;
    addresses: CustomerAddress[];
    customer_notes: CustomerNote[];
}

interface Props {
    customer: Customer;
}

const props = defineProps<Props>();

const activeTab = ref('info');
const showDeleteModal = ref(false);

const { formatPrice } = useCurrency();

const deleteCustomer = () => {
    router.delete(`/admin/customers/${props.customer.id}`, {
        onSuccess: () => {
            // Redirect handled by controller
        },
    });
};

const formatCurrency = (amount: number) => {
    return formatPrice(amount);
};
</script>

<template>
    <Head :title="$t('Customer: {name}', { name: customer.full_name })" />

    <AdminLayout :title="customer.full_name">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ customer.full_name }}</h1>
                            <span
                                :class="[
                                    'px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    customer.is_active ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300'
                                ]"
                            >
                                {{ customer.is_active ? $t('Active') : $t('Inactive') }}
                            </span>
                            <span
                                v-if="customer.is_guest"
                                class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300"
                            >
                                {{ $t('Guest') }}
                            </span>
                            <span
                                v-if="customer.is_verified"
                                class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300"
                            >
                                {{ $t('Verified') }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $t('Customer ID: #{id}', { id: customer.id }) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="router.visit('/admin/customers')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            {{ $t('Back to List') }}
                        </button>
                        <a
                            :href="`/admin/customers/${customer.id}/edit`"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                        >
                            {{ $t('Edit Customer') }}
                        </a>
                        <button
                            @click="showDeleteModal = true"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
                        >
                            {{ $t('Delete') }}
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $t('Total Orders') }}</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ customer.total_orders }}
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $t('Total Spent') }}</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ formatCurrency(customer.total_spent) }}
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $t('Customer Group') }}</div>
                        <div class="mt-2">
                            <span
                                v-if="customer.customer_group && customer.customer_group.id"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                :style="{ backgroundColor: customer.customer_group.color + '20', color: customer.customer_group.color }"
                            >
                                {{ customer.customer_group.name }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400"
                            >
                                {{ $t('No Group') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex gap-6 px-6" :aria-label="$t('Tabs')">
                        <button
                            @click="activeTab = 'info'"
                            :class="[
                                'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'info'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
                            ]"
                        >
                            {{ $t('Customer Information') }}
                        </button>
                        <button
                            @click="activeTab = 'addresses'"
                            :class="[
                                'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'addresses'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
                            ]"
                        >
                            {{ $t('Addresses ({count})', { count: customer.addresses?.length || 0 }) }}
                        </button>
                        <button
                            @click="activeTab = 'notes'"
                            :class="[
                                'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'notes'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
                            ]"
                        >
                            {{ $t('Notes ({count})', { count: customer.customer_notes?.length || 0 }) }}
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Customer Information Tab -->
                    <div v-if="activeTab === 'info'">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Contact Information -->
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Contact Information') }}</h3>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Email') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ customer.email }}</dd>
                                </div>
                                <div v-if="customer.phone">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Phone') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ customer.phone }}</dd>
                                </div>
                                <div v-if="customer.company_name">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Company') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ customer.company_name }}</dd>
                                </div>
                                <div v-if="customer.tax_id">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Tax ID') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ customer.tax_id }}</dd>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Personal Information') }}</h3>
                                <div v-if="customer.date_of_birth">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Date of Birth') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100"><DateDisplay :value="customer.date_of_birth" /></dd>
                                </div>
                                <div v-if="customer.gender">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Gender') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 capitalize">{{ customer.gender }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Newsletter') }}</dt>
                                    <dd class="mt-1 text-sm">
                                        <span
                                            :class="[
                                                'px-2 py-1 rounded text-xs font-medium',
                                                customer.newsletter_subscribed ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-gray-800/60 text-gray-800 dark:text-gray-200'
                                            ]"
                                        >
                                            {{ customer.newsletter_subscribed ? $t('Subscribed') : $t('Not Subscribed') }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Member Since') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100"><DateDisplay :value="customer.created_at" /></dd>
                                </div>
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div v-if="customer.notes" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Notes') }}</h3>
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ customer.notes }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Addresses Tab -->
                    <div v-if="activeTab === 'addresses'">
                        <div class="mb-4 flex justify-between items-center">
                            <Link
                                :href="`/admin/customers/${customer.id}/addresses`"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                                {{ $t('Manage All Addresses') }}
                            </Link>
                            <Link
                                :href="`/admin/customers/${customer.id}/addresses/create`"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ $t('Add Address') }}
                            </Link>
                        </div>
                        <div v-if="customer.addresses && customer.addresses.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                v-for="address in customer.addresses"
                                :key="address.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 capitalize">{{ address.type }}</span>
                                    <div class="flex gap-2">
                                        <span
                                            v-if="address.is_default_shipping"
                                            class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded"
                                        >
                                            {{ $t('Default Shipping') }}
                                        </span>
                                        <span
                                            v-if="address.is_default_billing"
                                            class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded"
                                        >
                                            {{ $t('Default Billing') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                    <p class="font-medium">{{ address.first_name }} {{ address.last_name }}</p>
                                    <p v-if="address.company">{{ address.company }}</p>
                                    <p>{{ address.address_line_1 }}</p>
                                    <p v-if="address.address_line_2">{{ address.address_line_2 }}</p>
                                    <p>{{ address.city }}, {{ address.state }} {{ address.postal_code }}</p>
                                    <p>{{ address.country }}</p>
                                    <p v-if="address.phone" class="pt-2">{{ address.phone }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $t('No addresses') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t("This customer hasn't added any addresses yet.") }}</p>
                        </div>
                    </div>

                    <!-- Notes Tab -->
                    <div v-if="activeTab === 'notes'">
                        <div v-if="customer.customer_notes && customer.customer_notes.length > 0" class="space-y-4">
                            <div
                                v-for="note in customer.customer_notes"
                                :key="note.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ note.user.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><DateDisplay :value="note.created_at" time /></div>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ note.note }}</p>
                            </div>
                        </div>
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $t('No notes') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('No notes have been added for this customer.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <ConfirmDeleteModal
            v-model:show="showDeleteModal"
            :title="customer.full_name"
            :message="$t('Are you sure you want to delete {name}? This action cannot be undone.', { name: customer.full_name })"
            @confirm="deleteCustomer"
        />
    </AdminLayout>
</template>
