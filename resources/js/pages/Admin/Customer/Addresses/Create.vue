<script setup lang="ts">
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, computed, onMounted } from 'vue';

interface Props {
  customer: any;
}

const props = defineProps<Props>();
const page = usePage();

const form = ref({
  type: 'shipping',
  first_name: '',
  last_name: '',
  company: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  state: '',
  postal_code: '',
  phone: '',
  is_default_shipping: false,
  is_default_billing: false,
});

const errors = computed(() => page.props.errors || {});
const processing = ref(false);

// Pre-fill customer data
onMounted(() => {
  if (props.customer) {
    form.value.first_name = props.customer.first_name || '';
    form.value.last_name = props.customer.last_name || '';
    form.value.phone = props.customer.phone || '';
  }
});

function submit() {
  processing.value = true;
  router.post(`/admin/customers/${props.customer.id}/addresses`, form.value, {
    onFinish: () => {
      processing.value = false;
    },
  });
}

function cancel() {
  router.visit(`/admin/customers/${props.customer.id}`);
}
</script>

<template>
  <AdminLayout :title="$t('New Address')">
    <Head :title="$t('New Address')" />

    <div class="p-6">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $t('Add Address') }}</h1>
            <p class="mt-1 text-sm text-gray-600">
              {{ $t('Add a new address for') }} {{ props.customer.first_name }} {{ props.customer.last_name }}
            </p>
          </div>
          <Link
            :href="`/admin/customers/${props.customer.id}`"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ $t('Back to Customer') }}
          </Link>
        </div>
      </div>

      <form @submit.prevent="submit">
        <!-- Address Type -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Address Type') }}</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Type *') }}</label>
              <select
                v-model="form.type"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.type }"
              >
                <option value="shipping">{{ $t('Shipping Address') }}</option>
                <option value="billing">{{ $t('Billing Address') }}</option>
              </select>
              <p v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</p>
            </div>
          </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Contact Information') }}</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('First Name *') }}</label>
              <input
                v-model="form.first_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.first_name }"
                :placeholder="$t('Enter first name')"
              />
              <p v-if="errors.first_name" class="mt-1 text-sm text-red-600">{{ errors.first_name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Last Name *') }}</label>
              <input
                v-model="form.last_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.last_name }"
                :placeholder="$t('Enter last name')"
              />
              <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">{{ errors.last_name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Company') }}</label>
              <input
                v-model="form.company"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.company }"
                :placeholder="$t('Company name (optional)')"
              />
              <p v-if="errors.company" class="mt-1 text-sm text-red-600">{{ errors.company }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Phone *') }}</label>
              <input
                v-model="form.phone"
                type="tel"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.phone }"
                :placeholder="$t('Enter phone number')"
              />
              <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone }}</p>
            </div>
          </div>
        </div>

        <!-- Address Details -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Address Details') }}</h2>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Address Line 1 *') }}</label>
              <input
                v-model="form.address_line_1"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.address_line_1 }"
                :placeholder="$t('Street address, P.O. box, company name')"
              />
              <p v-if="errors.address_line_1" class="mt-1 text-sm text-red-600">{{ errors.address_line_1 }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Address Line 2') }}</label>
              <input
                v-model="form.address_line_2"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.address_line_2 }"
                :placeholder="$t('Apartment, suite, unit, building, floor, etc. (optional)')"
              />
              <p v-if="errors.address_line_2" class="mt-1 text-sm text-red-600">{{ errors.address_line_2 }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('City *') }}</label>
                <input
                  v-model="form.city"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.city }"
                  :placeholder="$t('Enter city')"
                />
                <p v-if="errors.city" class="mt-1 text-sm text-red-600">{{ errors.city }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('State / Province *') }}</label>
                <input
                  v-model="form.state"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.state }"
                  :placeholder="$t('Enter state or province')"
                />
                <p v-if="errors.state" class="mt-1 text-sm text-red-600">{{ errors.state }}</p>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Postal Code *') }}</label>
                <input
                  v-model="form.postal_code"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.postal_code }"
                  :placeholder="$t('Enter postal code')"
                />
                <p v-if="errors.postal_code" class="mt-1 text-sm text-red-600">{{ errors.postal_code }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Default Address Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Default Address Settings') }}</h2>
          <div class="space-y-3">
            <div class="flex items-start">
              <div class="flex items-center h-5">
                <input
                  v-model="form.is_default_shipping"
                  type="checkbox"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
              </div>
              <div class="ml-3">
                <label class="text-sm font-medium text-gray-700">{{ $t('Set as default shipping address') }}</label>
                <p class="text-sm text-gray-500">{{ $t('Use this address as the default for shipping orders') }}</p>
              </div>
            </div>
            <div class="flex items-start">
              <div class="flex items-center h-5">
                <input
                  v-model="form.is_default_billing"
                  type="checkbox"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
              </div>
              <div class="ml-3">
                <label class="text-sm font-medium text-gray-700">{{ $t('Set as default billing address') }}</label>
                <p class="text-sm text-gray-500">{{ $t('Use this address as the default for billing and invoices') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3">
          <button
            type="button"
            @click="cancel"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            :disabled="processing"
          >
            {{ $t('Cancel') }}
          </button>
          <button
            type="submit"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="processing"
          >
            <svg
              v-if="processing"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
            {{ $t(processing ? 'Saving...' : 'Save Address') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
