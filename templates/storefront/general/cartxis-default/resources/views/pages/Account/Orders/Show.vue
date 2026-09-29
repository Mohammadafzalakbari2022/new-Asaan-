<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';

interface Address {
  first_name: string;
  last_name: string;
  company?: string;
  address_line1: string;
  address_line2?: string;
  city: string;
  state: string;
  postal_code: string;
  country: string;
  phone: string;
}

interface OrderItem {
  id: number;
  product_name: string;
  product_slug?: string;
  quantity: number;
  unit_price: number;
  total: number;
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_method: string;
  payment_status: string;
  customer_name: string;
  customer_email: string;
  customer_phone?: string;
  subtotal: number;
  tax_total: number;
  shipping_total: number;
  discount_total: number;
  total: number;
  notes?: string;
  created_at: string;
  items: OrderItem[];
  shipping_address?: Address;
  billing_address?: Address;
}

interface Props {
  order: Order;
}

const props = defineProps<Props>();

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    processing: 'bg-blue-100 text-blue-800 border-blue-200',
    shipped: 'bg-purple-100 text-purple-800 border-purple-200',
    delivered: 'bg-green-100 text-green-800 border-green-200',
    cancelled: 'bg-red-100 text-red-800 border-red-200',
  };
  return colors[status] || 'bg-gray-100 text-gray-800 border-gray-200';
};

const formatPrice = (price: number) => {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2,
  }).format(price);
};

const formatAddress = (address?: Address) => {
  if (!address) return 'N/A';
  const parts = [
    `${address.first_name} ${address.last_name}`,
    address.company,
    address.address_line1,
    address.address_line2,
    `${address.city}, ${address.state} ${address.postal_code}`,
    address.country,
  ].filter(Boolean);
  return parts.join('\n');
};
</script>

<template>
  <ThemeLayout>
    <Head :title="$t('Order #{number}', { number: order.order_number })" />

    <div class="container mx-auto px-4 py-8">
      <div class="max-w-6xl mx-auto">
        <!-- Header with Back Button -->
        <div class="mb-6">
          <Link
            href="/account/orders"
            class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 mb-4"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ $t('Back to Orders') }}
          </Link>
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-3xl font-bold mb-2">{{ $t('Order #{number}', { number: order.order_number }) }}</h1>
              <p class="text-gray-600">{{ $t('Placed on {date}', { date: order.created_at }) }}</p>
            </div>
            <span
              :class="[
                'px-4 py-2 rounded-full text-sm font-medium border',
                getStatusColor(order.status)
              ]"
            >
              {{ $t(order.status.charAt(0).toUpperCase() + order.status.slice(1)) }}
            </span>
          </div>
        </div>

        <!-- Order Timeline (Visual Status) -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 class="text-lg font-semibold mb-4">{{ $t('Order Status') }}</h2>
          <div class="flex items-center justify-between relative">
            <!-- Progress Line -->
            <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200">
              <div
                :class="[
                  'h-full transition-all duration-500',
                  order.status === 'pending' ? 'w-1/4 bg-yellow-500' : '',
                  order.status === 'processing' ? 'w-1/2 bg-blue-500' : '',
                  order.status === 'shipped' ? 'w-3/4 bg-purple-500' : '',
                  order.status === 'delivered' ? 'w-full bg-green-500' : '',
                  order.status === 'cancelled' ? 'w-1/4 bg-red-500' : '',
                ]"
              ></div>
            </div>

            <!-- Status Steps -->
            <div class="flex items-center justify-between w-full relative z-10">
              <div class="flex flex-col items-center">
                <div
                  :class="[
                    'w-10 h-10 rounded-full border-4 flex items-center justify-center',
                    ['pending', 'processing', 'shipped', 'delivered'].includes(order.status)
                      ? 'bg-green-500 border-white'
                      : 'bg-gray-200 border-white'
                  ]"
                >
                  <svg v-if="['processing', 'shipped', 'delivered'].includes(order.status)" class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <span class="text-xs mt-2 font-medium">{{ $t('Pending') }}</span>
              </div>

              <div class="flex flex-col items-center">
                <div
                  :class="[
                    'w-10 h-10 rounded-full border-4 flex items-center justify-center',
                    ['processing', 'shipped', 'delivered'].includes(order.status)
                      ? 'bg-green-500 border-white'
                      : 'bg-gray-200 border-white'
                  ]"
                >
                  <svg v-if="['shipped', 'delivered'].includes(order.status)" class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <span class="text-xs mt-2 font-medium">{{ $t('Processing') }}</span>
              </div>

              <div class="flex flex-col items-center">
                <div
                  :class="[
                    'w-10 h-10 rounded-full border-4 flex items-center justify-center',
                    ['shipped', 'delivered'].includes(order.status)
                      ? 'bg-green-500 border-white'
                      : 'bg-gray-200 border-white'
                  ]"
                >
                  <svg v-if="order.status === 'delivered'" class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <span class="text-xs mt-2 font-medium">{{ $t('Shipped') }}</span>
              </div>

              <div class="flex flex-col items-center">
                <div
                  :class="[
                    'w-10 h-10 rounded-full border-4 flex items-center justify-center',
                    order.status === 'delivered'
                      ? 'bg-green-500 border-white'
                      : 'bg-gray-200 border-white'
                  ]"
                >
                  <svg v-if="order.status === 'delivered'" class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <span class="text-xs mt-2 font-medium">{{ $t('Delivered') }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Content: Order Items -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
              <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">{{ $t('Order Items') }}</h2>
              </div>
              <div class="divide-y divide-gray-200">
                <div
                  v-for="item in order.items"
                  :key="item.id"
                  class="p-6 flex items-center gap-4"
                >
                  <div class="flex-1">
                    <Link
                      v-if="item.product_slug"
                      :href="`/product/${item.product_slug}`"
                      class="font-semibold text-gray-900 hover:text-blue-600"
                    >
                      {{ item.product_name }}
                    </Link>
                    <h3 v-else class="font-semibold text-gray-900">
                      {{ item.product_name }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                      {{ $t('Quantity: {qty}', { qty: item.quantity }) }} × {{ formatPrice(item.unit_price) }}
                    </p>
                  </div>
                  <div class="text-right">
                    <p class="font-semibold text-gray-900">{{ formatPrice(item.total) }}</p>
                  </div>
                </div>
              </div>

              <!-- Order Totals -->
              <div class="p-6 border-t border-gray-200 bg-gray-50">
                <div class="space-y-2 max-w-sm ml-auto">
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $t('Subtotal') }}</span>
                    <span class="font-medium">{{ formatPrice(order.subtotal) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $t('Shipping') }}</span>
                    <span class="font-medium">{{ formatPrice(order.shipping_total) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $t('Tax') }}</span>
                    <span class="font-medium">{{ formatPrice(order.tax_total) }}</span>
                  </div>
                  <div v-if="order.discount_total > 0" class="flex justify-between text-sm text-green-600">
                    <span>{{ $t('Discount') }}</span>
                    <span class="font-medium">-{{ formatPrice(order.discount_total) }}</span>
                  </div>
                  <div class="flex justify-between text-lg font-bold border-t pt-2">
                    <span>{{ $t('Total') }}</span>
                    <span>{{ formatPrice(order.total) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Order Notes -->
            <div v-if="order.notes" class="bg-blue-50 rounded-lg border border-blue-200 p-6">
              <h3 class="font-semibold text-blue-900 mb-2">{{ $t('Order Notes') }}</h3>
              <p class="text-sm text-blue-800 whitespace-pre-wrap">{{ order.notes }}</p>
            </div>
          </div>

          <!-- Sidebar: Customer & Shipping Info -->
          <div class="space-y-6">
            <!-- Customer Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 class="font-semibold mb-4">{{ $t('Customer Information') }}</h3>
              <div class="space-y-2 text-sm">
                <div>
                  <p class="text-gray-600">{{ $t('Name') }}</p>
                  <p class="font-medium">{{ order.customer_name }}</p>
                </div>
                <div>
                  <p class="text-gray-600">{{ $t('Email') }}</p>
                  <p class="font-medium">{{ order.customer_email }}</p>
                </div>
                <div v-if="order.customer_phone">
                  <p class="text-gray-600">{{ $t('Phone') }}</p>
                  <p class="font-medium">{{ order.customer_phone }}</p>
                </div>
              </div>
            </div>

            <!-- Payment Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 class="font-semibold mb-4">{{ $t('Payment Information') }}</h3>
              <div class="space-y-2 text-sm">
                <div>
                  <p class="text-gray-600">{{ $t('Payment Method') }}</p>
                  <p class="font-medium capitalize">{{ order.payment_method }}</p>
                </div>
                <div>
                  <p class="text-gray-600">{{ $t('Payment Status') }}</p>
                  <span
                    :class="[
                      'inline-block px-2 py-1 rounded text-xs font-medium',
                      order.payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                    ]"
                  >
                    {{ $t(order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Shipping Address -->
            <div v-if="order.shipping_address" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 class="font-semibold mb-4">{{ $t('Shipping Address') }}</h3>
              <address class="text-sm text-gray-700 whitespace-pre-line not-italic">
                {{ formatAddress(order.shipping_address) }}
              </address>
            </div>

            <!-- Billing Address -->
            <div v-if="order.billing_address" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 class="font-semibold mb-4">{{ $t('Billing Address') }}</h3>
              <address class="text-sm text-gray-700 whitespace-pre-line not-italic">
                {{ formatAddress(order.billing_address) }}
              </address>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 class="font-semibold mb-4">{{ $t('Order Actions') }}</h3>
              <div class="space-y-2">
                <button
                  class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                  @click="() => window.print()"
                >
                  {{ $t('Print Invoice') }}
                </button>
                <Link
                  href="/"
                  class="block w-full px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium text-center"
                >
                  {{ $t('Continue Shopping') }}
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ThemeLayout>
</template>
