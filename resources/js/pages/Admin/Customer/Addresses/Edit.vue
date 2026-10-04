<script setup lang="ts">
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, computed } from 'vue';

interface Props {
  customer: any;
  address: any;
}

const props = defineProps<Props>();
const page = usePage();

const form = ref({
  type: props.address.type || 'shipping',
  first_name: props.address.first_name || '',
  last_name: props.address.last_name || '',
  company: props.address.company || '',
  address_line_1: props.address.address_line_1 || '',
  address_line_2: props.address.address_line_2 || '',
  city: props.address.city || '',
  state: props.address.state || '',
  postal_code: props.address.postal_code || '',
  phone: props.address.phone || '',
  is_default_shipping: props.address.is_default_shipping || false,
  is_default_billing: props.address.is_default_billing || false,
});

const processing = ref(false);

const errors = computed(() => page.props.errors || {});

function submit() {
  processing.value = true;
  router.put(`/admin/customers/${props.customer.id}/addresses/${props.address.id}`, form.value, {
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
  <AdminLayout :title="$t('Edit Address')">
    <Head :title="$t('Edit Address')" />

    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $t('Edit Address') }}</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Edit address for') }} {{ customer.first_name }} {{ customer.last_name }}
          </p>
        </div>
        <Link 
          :href="`/admin/customers/${customer.id}`"
          class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          {{ $t('Back to Customer') }}
        </Link>
      </div>

      <form @submit.prevent="submit">
        <!-- Section 1: Address Type -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ $t('Address Type') }}</h2>
          
          <div>
            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Type *') }}</label>
            <select 
              id="type"
              v-model="form.type"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
              :class="{ 'border-red-500': errors.type }"
            >
              <option value="shipping">{{ $t('Shipping Address') }}</option>
              <option value="billing">{{ $t('Billing Address') }}</option>
            </select>
            <p v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</p>
          </div>
        </div>

        <!-- Section 2: Contact Information -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ $t('Contact Information') }}</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('First Name *') }}</label>
              <input 
                id="first_name"
                v-model="form.first_name"
                type="text"
                :placeholder="$t('Enter first name')"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': errors.first_name }"
              />
              <p v-if="errors.first_name" class="mt-1 text-sm text-red-600">{{ errors.first_name }}</p>
            </div>

            <div>
              <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Last Name *') }}</label>
              <input 
                id="last_name"
                v-model="form.last_name"
                type="text"
                :placeholder="$t('Enter last name')"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': errors.last_name }"
              />
              <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">{{ errors.last_name }}</p>
            </div>

            <div>
              <label for="company" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Company') }}</label>
              <input 
                id="company"
                v-model="form.company"
                type="text"
                :placeholder="$t('Company name (optional)')"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': errors.company }"
              />
              <p v-if="errors.company" class="mt-1 text-sm text-red-600">{{ errors.company }}</p>
            </div>

            <div>
              <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Phone *') }}</label>
              <input 
                id="phone"
                v-model="form.phone"
                type="tel"
                :placeholder="$t('Enter phone number')"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': errors.phone }"
              />
              <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone }}</p>
            </div>
          </div>
        </div>

        <!-- Section 3: Address Details -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ $t('Address Details') }}</h2>
          
          <div class="space-y-4">
            <div>
              <label for="address_line_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Address Line 1 *') }}</label>
              <input 
                id="address_line_1"
                v-model="form.address_line_1"
                type="text"
                :placeholder="$t('Street address, P.O. box, company name')"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': errors.address_line_1 }"
              />
              <p v-if="errors.address_line_1" class="mt-1 text-sm text-red-600">{{ errors.address_line_1 }}</p>
            </div>

            <div>
              <label for="address_line_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Address Line 2') }}</label>
              <input 
                id="address_line_2"
                v-model="form.address_line_2"
                type="text"
                :placeholder="$t('Apartment, suite, unit, building, floor, etc. (optional)')"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': errors.address_line_2 }"
              />
              <p v-if="errors.address_line_2" class="mt-1 text-sm text-red-600">{{ errors.address_line_2 }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('City *') }}</label>
                <input 
                  id="city"
                  v-model="form.city"
                  type="text"
                  :placeholder="$t('Enter city')"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="{ 'border-red-500': errors.city }"
                />
                <p v-if="errors.city" class="mt-1 text-sm text-red-600">{{ errors.city }}</p>
              </div>

              <div>
                <label for="state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('State / Province *') }}</label>
                <input 
                  id="state"
                  v-model="form.state"
                  type="text"
                  :placeholder="$t('Enter state or province')"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="{ 'border-red-500': errors.state }"
                />
                <p v-if="errors.state" class="mt-1 text-sm text-red-600">{{ errors.state }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="postal_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Postal Code *') }}</label>
                <input 
                  id="postal_code"
                  v-model="form.postal_code"
                  type="text"
                  :placeholder="$t('Enter postal code')"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="{ 'border-red-500': errors.postal_code }"
                />
                <p v-if="errors.postal_code" class="mt-1 text-sm text-red-600">{{ errors.postal_code }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 4: Default Settings -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ $t('Default Address Settings') }}</h2>
          
          <div class="space-y-3">
            <div class="flex items-start">
              <input 
                id="is_default_shipping"
                type="checkbox"
                v-model="form.is_default_shipping"
                class="mt-1 rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700"
              />
              <div class="ml-3">
                <label for="is_default_shipping" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('Set as default shipping address') }}
                </label>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('Use this address as the default for shipping orders') }}</p>
              </div>
            </div>

            <div class="flex items-start">
              <input 
                id="is_default_billing"
                type="checkbox"
                v-model="form.is_default_billing"
                class="mt-1 rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700"
              />
              <div class="ml-3">
                <label for="is_default_billing" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('Set as default billing address') }}
                </label>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('Use this address as the default for billing and invoices') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3">
          <button
            type="button"
            @click="cancel"
            :disabled="processing"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t('Cancel') }}
          </button>
          <button
            type="submit"
            :disabled="processing"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ $t(processing ? 'Saving...' : 'Update Address') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
