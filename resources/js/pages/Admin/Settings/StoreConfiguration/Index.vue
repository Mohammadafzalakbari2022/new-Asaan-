<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import TiptapEditor from '@/components/Admin/TiptapEditor.vue'
import {
  Store,
  MapPin,
  Phone,
  Share2,
  ShoppingBag,
  FileText,
  Save,
  Package,
  Globe,
  Mail,
  ShieldCheck,
  CreditCard,
  Building2,
  ShoppingCart,
  Info
} from 'lucide-vue-next'

interface Props {
  settings?: {
    store_name: string
    store_description: string
    business_registration: string
    vat_number: string
    store_license: string
    store_email: string
    support_email: string
    store_phone: string
    store_phone_alt: string
    store_whatsapp: string
    store_address_1: string
    store_address_2: string
    store_city: string
    store_state: string
    store_postal_code: string
    store_country: string
    store_timezone: string
    social_facebook: string
    social_instagram: string
    social_twitter: string
    social_linkedin: string
    social_youtube: string
    social_tiktok: string
    social_pinterest: string
    policy_privacy: string
    policy_terms: string
    policy_return: string
    policy_shipping: string
    checkout_allow_guest: boolean
    checkout_require_account: boolean
  }
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
    policy_privacy: '',
    policy_terms: '',
    policy_return: '',
    policy_shipping: '',
    checkout_allow_guest: true,
    checkout_require_account: false,
  }),
})

const activeTab = ref('details')

// Shown read-only: the country is decided by the store, not the admin form.
const storeCountry = ref(props.settings.store_country || '')

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
})

const save = () => {
  if (activeTab.value === 'checkout') {
    router.post('/admin/settings/store', {
      _section: 'checkout',
      checkout_allow_guest: form.checkout_allow_guest,
      checkout_require_account: form.checkout_require_account,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        // Toast shown automatically via flash message
      },
      onError: () => {
        // Errors handled by Inertia form
      },
    })
    return
  }

  form.post('/admin/settings/store', {
    preserveScroll: true,
    onSuccess: () => {
      // Toast notification will be shown automatically via flash message
    },
    onError: () => {
      // Errors handled by Inertia form
    },
  })
}

watch(() => form.store_email, (newEmail) => {
  if (!form.support_email || form.support_email === '') {
    form.support_email = newEmail
  }
})
</script>

<template>
  <AdminLayout :title="$t('Store Configuration')">
    <Head :title="$t('Store Configuration')" />

    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Store Configuration') }}</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $t('Manage store details, contact information, social media links, and policies') }}
          </p>
        </div>
        <div>
            <button
                @click="save"
                :disabled="form.processing"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <Save v-if="!form.processing" class="w-4 h-4 mr-2" />
                <svg v-else class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ form.processing ? $t('Saving...') : $t('Save Configuration') }}
            </button>
        </div>
      </div>

      <form @submit.prevent="save" novalidate>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
          <!-- Tabs Navigation -->
          <div class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800">
            <nav class="flex space-x-8 px-6 overflow-x-auto" :aria-label="$t('Tabs')">
              <button
                type="button"
                @click="activeTab = 'details'"
                :class="[
                  activeTab === 'details'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
                  'group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 whitespace-nowrap'
                ]"
              >
                <Store class="w-4 h-4 mr-2" :class="activeTab === 'details' ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'" />
                {{ $t('Store Details') }}
              </button>
              <button
                type="button"
                @click="activeTab = 'contact'"
                :class="[
                  activeTab === 'contact'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
                  'group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 whitespace-nowrap'
                ]"
              >
                <MapPin class="w-4 h-4 mr-2" :class="activeTab === 'contact' ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'" />
                {{ $t('Contact & Address') }}
              </button>
              <button
                type="button"
                @click="activeTab = 'social'"
                :class="[
                  activeTab === 'social'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
                  'group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 whitespace-nowrap'
                ]"
              >
                <Share2 class="w-4 h-4 mr-2" :class="activeTab === 'social' ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'" />
                {{ $t('Social Media') }}
              </button>
              <button
                type="button"
                @click="activeTab = 'checkout'"
                :class="[
                  activeTab === 'checkout'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
                  'group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 whitespace-nowrap'
                ]"
              >
                <ShoppingBag class="w-4 h-4 mr-2" :class="activeTab === 'checkout' ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'" />
                {{ $t('Checkout') }}
              </button>
              <button
                type="button"
                @click="activeTab = 'policies'"
                :class="[
                  activeTab === 'policies'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
                  'group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 whitespace-nowrap'
                ]"
              >
                <FileText class="w-4 h-4 mr-2" :class="activeTab === 'policies' ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'" />
                {{ $t('Policies') }}
              </button>
            </nav>
          </div>

          <!-- Store Details Tab -->
          <div v-show="activeTab === 'details'" class="p-6 sm:p-8">
            <div class="space-y-8">
              <div>
                 <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <Store class="w-5 h-5 text-gray-400" />
                    {{ $t('Business Information') }}
                </h3>
                  <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                      <label for="store_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('Business Name') }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="store_name"
                        v-model="form.store_name"
                        type="text"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                        required
                      />
                      <p v-if="form.errors.store_name" class="mt-1 text-sm text-red-600">{{ form.errors.store_name }}</p>
                    </div>

                    <div>
                      <label for="business_registration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('Business Registration Number') }}
                      </label>
                      <input
                        id="business_registration"
                        v-model="form.business_registration"
                        type="text"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      />
                      <p v-if="form.errors.business_registration" class="mt-1 text-sm text-red-600">{{ form.errors.business_registration }}</p>
                    </div>
                  </div>
              </div>

              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 pt-6 border-t border-gray-100 dark:border-gray-700">
                <div>
                  <label for="vat_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('VAT Number') }}
                  </label>
                  <input
                    id="vat_number"
                    v-model="form.vat_number"
                    type="text"
                    class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                  />
                  <p v-if="form.errors.vat_number" class="mt-1 text-sm text-red-600">{{ form.errors.vat_number }}</p>
                </div>

                <div>
                  <label for="store_license" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('License Number') }}
                  </label>
                  <input
                    id="store_license"
                    v-model="form.store_license"
                    type="text"
                    class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                  />
                  <p v-if="form.errors.store_license" class="mt-1 text-sm text-red-600">{{ form.errors.store_license }}</p>
                </div>

                <div>
                  <label for="store_timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('Store Timezone') }} <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                      <div class="absolute inset-y-0 left-0 padding-l-3 flex items-center pl-3 pointer-events-none">
                            <Globe class="h-4 w-4 text-gray-400" />
                      </div>
                      <select
                        id="store_timezone"
                        v-model="form.store_timezone"
                        class="block w-full pl-10 pr-10 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                        required
                      >
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America/New York</option>
                        <option value="America/Chicago">America/Chicago</option>
                        <option value="America/Denver">America/Denver</option>
                        <option value="America/Los_Angeles">America/Los Angeles</option>
                        <option value="Europe/London">Europe/London</option>
                        <option value="Europe/Paris">Europe/Paris</option>
                        <option value="Asia/Dubai">Asia/Dubai</option>
                        <option value="Asia/Kolkata">Asia/Kolkata</option>
                        <option value="Asia/Singapore">Asia/Singapore</option>
                        <option value="Asia/Tokyo">Asia/Tokyo</option>
                        <option value="Australia/Sydney">Australia/Sydney</option>
                      </select>
                  </div>
                  <p v-if="form.errors.store_timezone" class="mt-1 text-sm text-red-600">{{ form.errors.store_timezone }}</p>
                </div>
              </div>

              <div>
                <label for="store_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                  {{ $t('Business Description') }}
                </label>
                <textarea
                  id="store_description"
                  v-model="form.store_description"
                  rows="4"
                  class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                  :placeholder="$t('Brief description of your business for SEO purposes...')"
                ></textarea>
                <p v-if="form.errors.store_description" class="mt-1 text-sm text-red-600">{{ form.errors.store_description }}</p>
              </div>
            </div>
          </div>

          <!-- Contact & Address Tab -->
          <div v-show="activeTab === 'contact'" class="p-6 sm:p-8">
            <div class="space-y-8">
               <div>
                 <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <MapPin class="w-5 h-5 text-gray-400" />
                    {{ $t('Contact Information') }}
                </h3>
                  <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                      <label for="store_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('Primary Email') }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="store_email"
                        v-model="form.store_email"
                        type="email"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                        required
                      />
                      <p v-if="form.errors.store_email" class="mt-1 text-sm text-red-600">{{ form.errors.store_email }}</p>
                    </div>

                    <div>
                      <label for="support_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('Support Email') }}
                      </label>
                      <input
                        id="support_email"
                        v-model="form.support_email"
                        type="email"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      />
                      <p v-if="form.errors.support_email" class="mt-1 text-sm text-red-600">{{ form.errors.support_email }}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 mt-6">
                    <div>
                      <label for="store_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('Phone Number') }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="store_phone"
                        v-model="form.store_phone"
                        type="tel"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                        required
                      />
                      <p v-if="form.errors.store_phone" class="mt-1 text-sm text-red-600">{{ form.errors.store_phone }}</p>
                    </div>

                    <div>
                      <label for="store_phone_alt" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('Alternate Phone') }}
                      </label>
                      <input
                        id="store_phone_alt"
                        v-model="form.store_phone_alt"
                        type="tel"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      />
                      <p v-if="form.errors.store_phone_alt" class="mt-1 text-sm text-red-600">{{ form.errors.store_phone_alt }}</p>
                    </div>

                    <div>
                      <label for="store_whatsapp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('WhatsApp Number') }}
                      </label>
                      <input
                        id="store_whatsapp"
                        v-model="form.store_whatsapp"
                        type="tel"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      />
                      <p v-if="form.errors.store_whatsapp" class="mt-1 text-sm text-red-600">{{ form.errors.store_whatsapp }}</p>
                    </div>
                  </div>
               </div>

              <div class="pt-6 border-t border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $t('Store Address') }}</h3>
                
                <div class="space-y-6">
                  <div>
                    <label for="store_address_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                      {{ $t('Street Address') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      id="store_address_1"
                      v-model="form.store_address_1"
                      type="text"
                      class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      required
                    />
                    <p v-if="form.errors.store_address_1" class="mt-1 text-sm text-red-600">{{ form.errors.store_address_1 }}</p>
                  </div>

                  <div>
                    <label for="store_address_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                      {{ $t('Address Line 2') }}
                    </label>
                    <input
                      id="store_address_2"
                      v-model="form.store_address_2"
                      type="text"
                      class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                    />
                    <p v-if="form.errors.store_address_2" class="mt-1 text-sm text-red-600">{{ form.errors.store_address_2 }}</p>
                  </div>

                  <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                      <label for="store_city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('City') }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="store_city"
                        v-model="form.store_city"
                        type="text"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                        required
                      />
                      <p v-if="form.errors.store_city" class="mt-1 text-sm text-red-600">{{ form.errors.store_city }}</p>
                    </div>

                    <div>
                      <label for="store_state" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('State/Province') }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="store_state"
                        v-model="form.store_state"
                        type="text"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                        required
                      />
                      <p v-if="form.errors.store_state" class="mt-1 text-sm text-red-600">{{ form.errors.store_state }}</p>
                    </div>

                    <div>
                      <label for="store_postal_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('Postal Code') }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="store_postal_code"
                        v-model="form.store_postal_code"
                        type="text"
                        class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                        required
                      />
                      <p v-if="form.errors.store_postal_code" class="mt-1 text-sm text-red-600">{{ form.errors.store_postal_code }}</p>
                    </div>
                  </div>

                  <div>
                    <label for="store_country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                      {{ $t('Country') }}
                    </label>
                    <div
                      id="store_country"
                      class="block w-full px-3 py-2.5 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-500 dark:text-gray-400"
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <Share2 class="w-5 h-5 text-gray-400" />
                    {{ $t('Social Media Profiles') }}
                </h3>
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label for="social_facebook" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('Facebook') }}
                  </label>
                  <input
                    id="social_facebook"
                    v-model="form.social_facebook"
                    type="url"
                    class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                    placeholder="https://facebook.com/yourstore"
                  />
                  <p v-if="form.errors.social_facebook" class="mt-1 text-sm text-red-600">{{ form.errors.social_facebook }}</p>
                </div>

                <div>
                  <label for="social_instagram" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('Instagram') }}
                  </label>
                  <input
                    id="social_instagram"
                    v-model="form.social_instagram"
                    type="url"
                    class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                    placeholder="https://instagram.com/yourstore"
                  />
                  <p v-if="form.errors.social_instagram" class="mt-1 text-sm text-red-600">{{ form.errors.social_instagram }}</p>
                </div>

                <div>
                  <label for="social_twitter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('Twitter/X') }}
                  </label>
                  <input
                    id="social_twitter"
                    v-model="form.social_twitter"
                    type="url"
                    class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                    placeholder="https://twitter.com/yourstore"
                  />
                  <p v-if="form.errors.social_twitter" class="mt-1 text-sm text-red-600">{{ form.errors.social_twitter }}</p>
                </div>

                <div>
                    <label for="social_linkedin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                      {{ $t('LinkedIn') }}
                    </label>
                    <input
                      id="social_linkedin"
                      v-model="form.social_linkedin"
                      type="url"
                      class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      placeholder="https://linkedin.com/company/yourstore"
                    />
                    <p v-if="form.errors.social_linkedin" class="mt-1 text-sm text-red-600">{{ form.errors.social_linkedin }}</p>
                  </div>
    
                  <div>
                    <label for="social_youtube" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                      {{ $t('YouTube') }}
                    </label>
                    <input
                      id="social_youtube"
                      v-model="form.social_youtube"
                      type="url"
                      class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      placeholder="https://youtube.com/@yourstore"
                    />
                    <p v-if="form.errors.social_youtube" class="mt-1 text-sm text-red-600">{{ form.errors.social_youtube }}</p>
                  </div>
    
                  <div>
                    <label for="social_tiktok" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                      {{ $t('TikTok') }}
                    </label>
                    <input
                      id="social_tiktok"
                      v-model="form.social_tiktok"
                      type="url"
                      class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      placeholder="https://tiktok.com/@yourstore"
                    />
                    <p v-if="form.errors.social_tiktok" class="mt-1 text-sm text-red-600">{{ form.errors.social_tiktok }}</p>
                  </div>
    
                  <div>
                    <label for="social_pinterest" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                      {{ $t('Pinterest') }}
                    </label>
                    <input
                      id="social_pinterest"
                      v-model="form.social_pinterest"
                      type="url"
                      class="block w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow duration-200"
                      placeholder="https://pinterest.com/yourstore"
                    />
                    <p v-if="form.errors.social_pinterest" class="mt-1 text-sm text-red-600">{{ form.errors.social_pinterest }}</p>
                  </div>
              </div>
            </div>
          </div>


          <!-- Checkout Tab -->
          <div v-show="activeTab === 'checkout'" class="p-6 sm:p-8">
            <div class="space-y-8">
              <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                    <ShoppingCart class="w-5 h-5 text-gray-400" />
                    {{ $t('Checkout Preferences') }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('Configure checkout flow and account requirements used in your storefront') }}</p>
              </div>

              <div class="space-y-6 border-t border-gray-100 dark:border-gray-700 pt-6">
                  <!-- Allow Guest Checkout -->
                  <div class="flex items-start">
                    <div class="flex items-center h-5">
                      <input
                        id="checkout_allow_guest"
                        v-model="form.checkout_allow_guest"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 transition duration-150 ease-in-out"
                      />
                    </div>
                    <div class="ml-3 text-sm">
                      <label for="checkout_allow_guest" class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $t('Allow Guest Checkout') }}
                      </label>
                      <p class="text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('Allow customers to complete checkout without creating an account. They can optionally create one during checkout.') }}
                      </p>
                    </div>
                  </div>

                  <!-- Require Account Creation -->
                  <div class="flex items-start">
                    <div class="flex items-center h-5">
                      <input
                        id="checkout_require_account"
                        v-model="form.checkout_require_account"
                        type="checkbox"
                        :disabled="!form.checkout_allow_guest"
                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out"
                      />
                    </div>
                    <div class="ml-3 text-sm">
                      <label for="checkout_require_account" class="font-medium text-gray-700 dark:text-gray-300" :class="{ 'text-gray-400 dark:text-gray-600': !form.checkout_allow_guest }">
                        {{ $t('Require Account Creation') }}
                      </label>
                      <p class="text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('When enabled, guests must create an account during checkout (cannot proceed without registration).') }}
                      </p>
                    </div>
                  </div>
              </div>

              <!-- Info Box -->
              <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50 rounded-lg p-4">
                <div class="flex">
                   <Info class="h-5 w-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" />
                  <div>
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ $t('Configuration Options') }}</h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                      <ul class="list-disc list-inside space-y-1">
                        <li><strong>{{ $t('Both disabled:') }}</strong> {{ $t('Customers must log in or register before checkout (no guest option)') }}</li>
                        <li><strong>{{ $t('Guest allowed only:') }}</strong> {{ $t('Customers can checkout as guests or create an account (optional)') }}</li>
                        <li><strong>{{ $t('Both enabled:') }}</strong> {{ $t('Customers can start as guest but must create account during checkout') }}</li>
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
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                        <FileText class="w-5 h-5 text-gray-400" />
                        {{ $t('Store Policies') }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('Define the legal policies for your store to ensure transparency with customers.') }}</p>
                </div>

                <div class="space-y-8 border-t border-gray-100 dark:border-gray-700 pt-6">
                  <div>
                    <label for="policy_privacy" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      {{ $t('Privacy Policy') }}
                    </label>
                    <TiptapEditor
                      v-model="form.policy_privacy"
                      :placeholder="$t('Enter your privacy policy content...')"
                    />
                    <p v-if="form.errors.policy_privacy" class="mt-1 text-sm text-red-600">{{ form.errors.policy_privacy }}</p>
                  </div>

                  <div>
                    <label for="policy_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      {{ $t('Terms & Conditions') }}
                    </label>
                    <TiptapEditor
                      v-model="form.policy_terms"
                      :placeholder="$t('Enter your terms and conditions...')"
                    />
                    <p v-if="form.errors.policy_terms" class="mt-1 text-sm text-red-600">{{ form.errors.policy_terms }}</p>
                  </div>

                  <div>
                    <label for="policy_return" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      {{ $t('Return Policy') }}
                    </label>
                    <TiptapEditor
                      v-model="form.policy_return"
                      :placeholder="$t('Enter your return and refund policy...')"
                    />
                    <p v-if="form.errors.policy_return" class="mt-1 text-sm text-red-600">{{ form.errors.policy_return }}</p>
                  </div>

                  <div>
                    <label for="policy_shipping" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      {{ $t('Shipping Policy') }}
                    </label>
                    <TiptapEditor
                      v-model="form.policy_shipping"
                      :placeholder="$t('Enter your shipping terms and conditions...')"
                    />
                    <p v-if="form.errors.policy_shipping" class="mt-1 text-sm text-red-600">{{ form.errors.policy_shipping }}</p>
                  </div>
              </div>
            </div>
          </div>

          <!-- Save Button -->
          <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end">
             <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Save v-if="!form.processing" class="w-4 h-4 mr-2" />
                <svg v-else class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ form.processing ? $t('Saving...') : $t('Save Configuration') }}
             </button>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

