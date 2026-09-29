<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, computed, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import type { Order, RefundableItem, StatusOption } from '@/types/sales';

interface Props {
  order?: Order;
  refundableItems?: RefundableItem[];
  orders?: Order[];
}

const props = defineProps<Props>();
const { formatPrice, getSymbol } = useCurrency();

const selectedOrder = ref<Order | undefined>(props.order);
const refundItems = ref<RefundableItem[]>(props.refundableItems || []);

interface FormItem {
  order_item_id: number;
  qty: number;
}

const form = useForm({
  order_id: props.order?.id || null,
  items: [] as FormItem[],
  refund_method: 'online' as 'online' | 'offline',
  reason: '',
  notes: '',
  shipping_refund: 0,
  adjustment_amount: 0,
  restore_inventory: true,
});

// Watch for order selection changes
watch(() => form.order_id, (newOrderId) => {
  if (newOrderId && !props.order) {
    // Fetch refundable items for the selected order
    router.get(`/admin/sales/credit-memos/create`, { order_id: newOrderId }, {
      preserveState: true,
      preserveScroll: true,
      only: ['order', 'refundableItems'],
      onSuccess: (page) => {
        selectedOrder.value = (page.props as any).order;
        refundItems.value = (page.props as any).refundableItems || [];
      },
    });
  }
});

// Initialize selected quantities
const selectedQuantities = ref<Record<number, number>>({});
refundItems.value.forEach(item => {
  selectedQuantities.value[item.order_item_id] = 0;
});

// Calculate subtotal for refund items
const subtotal = computed(() => {
  return refundItems.value.reduce((sum, item) => {
    const qty = selectedQuantities.value[item.order_item_id] || 0;
    return sum + (qty * item.price);
  }, 0);
});

// Calculate tax (proportional to original order)
const taxAmount = computed(() => {
  if (!selectedOrder.value) return 0;
  const orderSubtotal = selectedOrder.value.subtotal;
  if (orderSubtotal === 0) return 0;
  const taxRate = selectedOrder.value.tax / orderSubtotal;
  return subtotal.value * taxRate;
});

// Calculate discount (proportional to original order)
const discountAmount = computed(() => {
  if (!selectedOrder.value) return 0;
  const orderSubtotal = selectedOrder.value.subtotal;
  if (orderSubtotal === 0) return 0;
  const discountRate = selectedOrder.value.discount / orderSubtotal;
  return subtotal.value * discountRate;
});

// Calculate grand total
const grandTotal = computed(() => {
  return subtotal.value - discountAmount.value + taxAmount.value + form.shipping_refund + form.adjustment_amount;
});

// Check if at least one item is selected
const hasSelectedItems = computed(() => {
  return Object.values(selectedQuantities.value).some(qty => qty > 0);
});

const selectAllItems = () => {
  refundItems.value.forEach(item => {
    selectedQuantities.value[item.order_item_id] = item.qty_refundable;
  });
};

const clearAllItems = () => {
  refundItems.value.forEach(item => {
    selectedQuantities.value[item.order_item_id] = 0;
  });
};

const submit = () => {
  // Build items array from selected quantities
  form.items = refundItems.value
    .filter(item => selectedQuantities.value[item.order_item_id] > 0)
    .map(item => ({
      order_item_id: item.order_item_id,
      qty: selectedQuantities.value[item.order_item_id],
    }));

  if (form.items.length === 0) {
    return;
  }

  form.post('/admin/sales/credit-memos', {
    onSuccess: () => {
      // Redirect handled by controller
    },
    onError: () => {
    },
  });
};

const cancel = () => {
  router.visit('/admin/sales/credit-memos');
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};
</script>

<template>
  <Head :title="$t('Create Credit Memo')" />

  <AdminLayout :title="$t('Create Credit Memo')">
    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $t('Create Credit Memo') }}</h1>
        <p class="text-gray-600 mt-1">{{ $t('Issue a refund for an order') }}</p>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Order Selection -->
        <div v-if="!order" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Select Order') }}</h3>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Order *') }}</label>
            <select
              v-model="form.order_id"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option :value="null">{{ $t('Select an order...') }}</option>
              <option v-for="o in orders" :key="o.id" :value="o.id">
                {{ o.order_number }} - {{ o.customer_email }} - {{ formatPrice(o.total) }}
              </option>
            </select>
            <p class="mt-1 text-sm text-gray-500">{{ $t('Select an order to create a credit memo for') }}</p>
          </div>
        </div>

        <!-- Order Information -->
        <div v-if="selectedOrder" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Order Information') }}</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <div class="text-sm text-gray-600">{{ $t('Order Number') }}</div>
              <div class="mt-1 font-medium text-gray-900">{{ selectedOrder.order_number }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-600">{{ $t('Customer') }}</div>
              <div class="mt-1 font-medium text-gray-900">{{ selectedOrder.user?.name || $t('Guest') }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-600">{{ $t('Order Total') }}</div>
              <div class="mt-1 font-medium text-gray-900">{{ formatPrice(selectedOrder.total) }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-600">{{ $t('Order Date') }}</div>
              <div class="mt-1 text-gray-900">{{ formatDate(selectedOrder.created_at) }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-600">{{ $t('Payment Method') }}</div>
              <div class="mt-1 text-gray-900">{{ selectedOrder.payment_method || '-' }}</div>
            </div>
            <div v-if="selectedOrder.total_refunded && selectedOrder.total_refunded > 0">
              <div class="text-sm text-gray-600">{{ $t('Already Refunded') }}</div>
              <div class="mt-1 font-medium text-red-600">{{ formatPrice(selectedOrder.total_refunded) }}</div>
            </div>
          </div>
        </div>

        <!-- Items Selection -->
        <div v-if="refundItems.length > 0" class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">{{ $t('Select Items to Refund') }}</h3>
            <div class="flex space-x-2">
              <button
                type="button"
                @click="selectAllItems"
                class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded"
              >
                {{ $t('Select All') }}
              </button>
              <button
                type="button"
                @click="clearAllItems"
                class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded"
              >
                {{ $t('Clear All') }}
              </button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('Product') }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('SKU') }}
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('Price') }}
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('Ordered') }}
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('Refunded') }}
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('Available') }}
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('Refund Qty') }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in refundItems" :key="item.order_item_id">
                  <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900">{{ item.product_name }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500">{{ item.sku || '-' }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm text-gray-900">{{ formatPrice(item.price) }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm text-gray-900">{{ item.qty_ordered }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm text-gray-900">{{ item.qty_refunded }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm font-medium text-gray-900">{{ item.qty_refundable }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <input
                      v-model.number="selectedQuantities[item.order_item_id]"
                      type="number"
                      min="0"
                      :max="item.qty_refundable"
                      :disabled="item.qty_refundable === 0"
                      class="w-20 px-3 py-1 text-right border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-100"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Refund Options -->
        <div v-if="selectedOrder" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Refund Options') }}</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Refund Method -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Refund Method *') }}</label>
              <select
                v-model="form.refund_method"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="online">{{ $t('Online (Payment Gateway)') }}</option>
                <option value="offline">{{ $t('Offline (Manual Refund)') }}</option>
              </select>
              <p class="mt-1 text-sm text-gray-500">
                {{ $t('Online: Refund through the original payment method') }}<br>
                {{ $t('Offline: Manual refund (cash, check, etc.)') }}
              </p>
            </div>

            <!-- Restore Inventory -->
            <div>
              <label class="flex items-center space-x-3 cursor-pointer">
                <input
                  v-model="form.restore_inventory"
                  type="checkbox"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <div>
                  <div class="text-sm font-medium text-gray-700">{{ $t('Restore Inventory') }}</div>
                  <div class="text-sm text-gray-500">{{ $t('Add refunded items back to inventory') }}</div>
                </div>
              </label>
            </div>

            <!-- Shipping Refund -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Shipping Refund') }}</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">{{ getSymbol() }}</span>
                <input
                  v-model.number="form.shipping_refund"
                  type="number"
                  step="0.01"
                  min="0"
                  :max="selectedOrder.shipping_cost"
                  placeholder="0.00"
                  class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <p class="mt-1 text-sm text-gray-500">
                {{ $t('Max: {amount}', { amount: formatPrice(selectedOrder.shipping_cost) }) }}
              </p>
            </div>

            <!-- Adjustment Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Adjustment Amount') }}</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">{{ getSymbol() }}</span>
                <input
                  v-model.number="form.adjustment_amount"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <p class="mt-1 text-sm text-gray-500">
                {{ $t('Positive for additional refund, negative for deduction') }}
              </p>
            </div>
          </div>

          <!-- Reason -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Reason for Refund') }}</label>
            <textarea
              v-model="form.reason"
              rows="3"
              :placeholder="$t('Enter the reason for this refund...')"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            ></textarea>
          </div>

          <!-- Notes -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Internal Notes') }}</label>
            <textarea
              v-model="form.notes"
              rows="3"
              :placeholder="$t('Internal notes (not visible to customer)...')"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            ></textarea>
          </div>
        </div>

        <!-- Totals Summary -->
        <div v-if="selectedOrder" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $t('Refund Summary') }}</h3>
          <div class="max-w-md ml-auto space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">{{ $t('Subtotal:') }}</span>
              <span class="font-medium text-gray-900">{{ formatPrice(subtotal) }}</span>
            </div>
            <div v-if="discountAmount > 0" class="flex justify-between text-sm">
              <span class="text-gray-600">{{ $t('Discount:') }}</span>
              <span class="font-medium text-gray-900">-{{ formatPrice(discountAmount) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">{{ $t('Tax:') }}</span>
              <span class="font-medium text-gray-900">{{ formatPrice(taxAmount) }}</span>
            </div>
            <div v-if="form.shipping_refund > 0" class="flex justify-between text-sm">
              <span class="text-gray-600">{{ $t('Shipping Refund:') }}</span>
              <span class="font-medium text-gray-900">{{ formatPrice(form.shipping_refund) }}</span>
            </div>
            <div v-if="form.adjustment_amount !== 0" class="flex justify-between text-sm">
              <span class="text-gray-600">{{ $t('Adjustment:') }}</span>
              <span class="font-medium text-gray-900">
                {{ form.adjustment_amount > 0 ? '+' : '' }}{{ formatPrice(form.adjustment_amount) }}
              </span>
            </div>
            <div class="flex justify-between text-lg font-bold pt-3 border-t border-gray-200">
              <span class="text-gray-900">{{ $t('Total Refund:') }}</span>
              <span class="text-gray-900">{{ formatPrice(grandTotal) }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-4">
          <button
            type="button"
            @click="cancel"
            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500             focus:ring-offset-2"
          >
            {{ $t('Cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing || !hasSelectedItems"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t(form.processing ? 'Creating...' : 'Create Credit Memo') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
