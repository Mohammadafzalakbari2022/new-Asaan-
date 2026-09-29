<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'

interface PaymentMethod {
  id: number
  code: string
  name: string
  description: string
  type: string
  is_active: boolean
  is_default: boolean
  sort_order: number
  instructions: string
  configuration: Record<string, any>
}

interface GatewayMeta {
  webhookUrl: string
  sandboxUrl: string
  productionUrl: string
  docsUrl: string
}

const props = defineProps<{
  method: PaymentMethod
  gatewayMeta?: GatewayMeta | null
}>()

const page = usePage()
const errors = computed(() => (page.props.errors as Record<string, string>) || {})

const form = useForm({
  name: props.method.name || 'HesabPay',
  description:
    props.method.description ||
    'Pay with the HesabPay wallet, AfPay card, or an international card.',
  instructions: props.method.instructions || '',
  configuration: {
    mode: props.method.configuration?.mode || 'sandbox',
    test_api_key: props.method.configuration?.test_api_key || '',
    api_key: props.method.configuration?.api_key || '',
  },
})

const isProduction = computed(() => form.configuration.mode === 'production')

// A key is only required for the environment that is actually selected.
const activeKeyMissing = computed(() => {
  if (isProduction.value) {
    return !form.configuration.api_key
  }
  return !form.configuration.test_api_key
})

const savedWebhooks = ref(false)
const webhookUrl = computed(() => props.gatewayMeta?.webhookUrl || '')

const copyWebhookUrl = async () => {
  if (!webhookUrl.value) return
  try {
    await navigator.clipboard.writeText(webhookUrl.value)
    savedWebhooks.value = true
    setTimeout(() => (savedWebhooks.value = false), 2000)
  } catch {
    savedWebhooks.value = false
  }
}

const save = () => {
  form.post('/admin/settings/payment-methods/hesabpay/save', {
    preserveScroll: true,
  })
}
</script>

<template>
  <AdminLayout :title="$t('Payment Methods - HesabPay')">
    <Head :title="$t('Configure HesabPay')" />

    <div>
      <!-- Page Header -->
      <div class="mb-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-white font-bold">
              {{ $t('HesabPay Configuration') }}
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              {{ $t('Accept HesabPay wallet, AFN, AfPay and international card payments') }}
            </p>
          </div>
          <Link
            href="/admin/settings/payment-methods"
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ $t('Back to Payment Methods') }}
          </Link>
        </div>
      </div>

      <form @submit.prevent="save" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <!-- Basic Information -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ $t('Basic Information') }}
          </h2>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {{ $t('Method Name') }} <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                :class="['w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent', errors.name ? 'border-red-500' : 'border-gray-300 dark:border-gray-600']"
                placeholder="HesabPay"
              />
              <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {{ $t('Description') }}
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {{ $t('Customer Instructions') }}
              </label>
              <textarea
                v-model="form.instructions"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                :placeholder="$t('Instructions shown to customers at checkout...')"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Environment -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ $t('Environment Mode') }}
          </h2>

          <div class="flex items-center gap-4 mb-6">
            <label
              class="relative flex items-center gap-3 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all"
              :class="form.configuration.mode === 'sandbox' ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20' : 'border-gray-200 dark:border-gray-600'"
            >
              <input v-model="form.configuration.mode" type="radio" value="sandbox" class="sr-only" />
              <div
                class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                :class="form.configuration.mode === 'sandbox' ? 'border-amber-500' : 'border-gray-300'"
              >
                <div v-if="form.configuration.mode === 'sandbox'" class="w-2 h-2 rounded-full bg-amber-500"></div>
              </div>
              <div>
                <div
                  class="text-sm font-semibold"
                  :class="form.configuration.mode === 'sandbox' ? 'text-amber-700 dark:text-amber-400' : 'text-gray-700 dark:text-gray-300'"
                >
                  {{ $t('Sandbox') }}
                </div>
                <div class="text-xs text-gray-500">{{ $t('Test payments, no real charges') }}</div>
              </div>
            </label>

            <label
              class="relative flex items-center gap-3 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all"
              :class="form.configuration.mode === 'production' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-600'"
            >
              <input v-model="form.configuration.mode" type="radio" value="production" class="sr-only" />
              <div
                class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                :class="form.configuration.mode === 'production' ? 'border-green-500' : 'border-gray-300'"
              >
                <div v-if="form.configuration.mode === 'production'" class="w-2 h-2 rounded-full bg-green-500"></div>
              </div>
              <div>
                <div
                  class="text-sm font-semibold"
                  :class="form.configuration.mode === 'production' ? 'text-green-700 dark:text-green-400' : 'text-gray-700 dark:text-gray-300'"
                >
                  {{ $t('Production') }}
                </div>
                <div class="text-xs text-gray-500">{{ $t('Real payments, real charges') }}</div>
              </div>
            </label>
          </div>

          <div
            v-if="form.configuration.mode === 'sandbox'"
            class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6"
          >
            <p class="text-sm text-amber-800 dark:text-amber-200">
              <strong>{{ $t('Sandbox mode:') }}</strong>
              {{ $t('Customers are not charged. Use a key from the HesabPay sandbox dashboard.') }}
            </p>
          </div>

          <div
            v-else
            class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6"
          >
            <p class="text-sm text-green-800 dark:text-green-200">
              <strong>{{ $t('Production mode:') }}</strong>
              {{ $t('Real money will be taken from customers. Use a live key from the HesabPay dashboard.') }}
            </p>
          </div>
        </div>

        <!-- API Keys -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ $t('API Key') }}
          </h2>

          <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
            <p class="text-sm text-blue-900 dark:text-blue-200">
              <strong>{{ $t('Where to get your key:') }}</strong>
              <template v-if="isProduction">
                {{ $t('Log in to') }}
                <a :href="gatewayMeta?.productionUrl" target="_blank" rel="noopener" class="underline">
                  developers.hesab.com
                </a>
                {{ $t('and copy the API key from the dashboard.') }}
              </template>
              <template v-else>
                {{ $t('Log in to') }}
                <a :href="gatewayMeta?.sandboxUrl" target="_blank" rel="noopener" class="underline">
                  developers-sandbox.hesab.com
                </a>
                {{ $t('and copy the API key from the dashboard.') }}
              </template>
            </p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {{ $t('Sandbox API Key') }}
                <span v-if="!isProduction" class="text-red-500">*</span>
                <span v-else class="text-gray-400 dark:text-gray-500">({{ $t('not used in production mode') }})</span>
              </label>
              <input
                v-model="form.configuration.test_api_key"
                type="password"
                autocomplete="off"
                :class="['w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent', errors['configuration.test_api_key'] ? 'border-red-500' : 'border-gray-300 dark:border-gray-600']"
                :placeholder="$t('Paste the sandbox API key')"
              />
              <p v-if="errors['configuration.test_api_key']" class="mt-1 text-sm text-red-600">
                {{ errors['configuration.test_api_key'] }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {{ $t('Live API Key') }}
                <span v-if="isProduction" class="text-red-500">*</span>
                <span v-else class="text-gray-400 dark:text-gray-500">({{ $t('not used in sandbox mode') }})</span>
              </label>
              <input
                v-model="form.configuration.api_key"
                type="password"
                autocomplete="off"
                :class="['w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent', errors['configuration.api_key'] ? 'border-red-500' : 'border-gray-300 dark:border-gray-600']"
                :placeholder="$t('Paste the live API key')"
              />
              <p v-if="errors['configuration.api_key']" class="mt-1 text-sm text-red-600">
                {{ errors['configuration.api_key'] }}
              </p>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ $t('The API key stays on the server. It is never sent to the browser or the mobile app.') }}
            </p>

            <div
              v-if="activeKeyMissing"
              class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4"
            >
              <p class="text-sm text-amber-800 dark:text-amber-200">
                <strong>{{ $t('Not ready yet:') }}</strong>
                {{
                  $t(
                    'Checkout will show a "not configured" message until a key is saved for the environment you selected.'
                  )
                }}
              </p>
            </div>
          </div>
        </div>

        <!-- Webhook setup -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('Webhook Setup') }}
          </h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{
              $t(
                'HesabPay tells this store that a payment succeeded by calling the address below. Copy it into the HesabPay dashboard, under Developer, then Webhooks, then Add Webhook. Subscribe to both payment_success and payment_failure.'
              )
            }}
          </p>

          <div v-if="webhookUrl" class="flex items-stretch gap-2">
            <input
              type="text"
              readonly
              :value="webhookUrl"
              class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white rounded-lg font-mono text-sm"
              @focus="($event.target as HTMLInputElement).select()"
            />
            <button
              type="button"
              @click="copyWebhookUrl"
              class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              {{ savedWebhooks ? $t('Copied') : $t('Copy') }}
            </button>
          </div>
          <p v-else class="text-sm text-amber-700 dark:text-amber-400">
            {{
              $t(
                'The webhook address is unavailable. Activate the HesabPay extension and reload this page to see it.'
              )
            }}
          </p>

          <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mt-4">
            <p class="text-sm text-blue-900 dark:text-blue-200">
              <strong>{{ $t('Why this matters:') }}</strong>
              {{
                $t(
                  'An order is only marked as paid after HesabPay confirms it here. Without this, orders stay pending forever and customers are never told their payment worked.'
                )
              }}
              <a :href="gatewayMeta?.docsUrl" target="_blank" rel="noopener" class="underline">
                {{ $t('Read the HesabPay docs') }}
              </a>
            </p>
          </div>
        </div>

        <!-- What customers see -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ $t('What Customers See') }}
          </h2>
          <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <p class="text-sm text-green-900 dark:text-green-200 mb-2">
              <strong>{{ $t('One option covers all of these:') }}</strong>
            </p>
            <ul class="text-sm text-green-800 dark:text-green-300 space-y-1 ml-4 list-disc">
              <li>{{ $t('HesabPay wallet') }}</li>
              <li>{{ $t('AfPay cards') }}</li>
              <li>{{ $t('International cards (Visa, Mastercard)') }}</li>
              <li>{{ $t('AFN payments') }}</li>
            </ul>
            <p class="text-sm text-green-900 dark:text-green-200 mt-3">
              {{
                $t(
                  'Customers are taken to the HesabPay checkout page to pay, then returned to this store.'
                )
              }}
            </p>
          </div>
        </div>

        <!-- Submit -->
        <div class="p-6 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex justify-end">
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ form.processing ? $t('Saving...') : $t('Save Configuration') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
