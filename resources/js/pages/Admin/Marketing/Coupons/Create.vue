<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import DatePicker from '@/components/Calendar/DatePicker.vue';
import { useI18nStore } from '@/Stores/i18n';

const { t } = useI18nStore();

const form = useForm({
  code: '',
  name: '',
  description: '',
  type: 'percentage',
  value: 0,
  max_discount: null as number | null,
  min_order_amount: null as number | null,
  is_active: true,
  is_public: true,
  stackable: false,
  auto_apply: false,
  first_order_only: false,
  usage_limit_total: null as number | null,
  usage_limit_per_customer: null as number | null,
  start_date: '',
  end_date: '',
  days_of_week: [] as string[],
  time_restrictions: {
    start: '',
    end: '',
  },
  customer_groups: [] as number[],
  min_account_age_days: null as number | null,
  applicable_products: [] as number[],
  excluded_products: [] as number[],
  applicable_categories: [] as number[],
  excluded_categories: [] as number[],
  buy_quantity: null as number | null,
  get_quantity: null as number | null,
  priority: 0,
});

const activeTab = ref('general');

const daysOfWeek = [
  { value: 'monday', label: t('Monday') },
  { value: 'tuesday', label: t('Tuesday') },
  { value: 'wednesday', label: t('Wednesday') },
  { value: 'thursday', label: t('Thursday') },
  { value: 'friday', label: t('Friday') },
  { value: 'saturday', label: t('Saturday') },
  { value: 'sunday', label: t('Sunday') },
];

const showBuyXGetY = computed(() => form.type === 'buy_x_get_y');
const showMaxDiscount = computed(() => form.type === 'percentage');
const showValue = computed(() => form.type !== 'free_shipping');

const toggleDay = (day: string) => {
  const index = form.days_of_week.indexOf(day);
  if (index > -1) {
    form.days_of_week.splice(index, 1);
  } else {
    form.days_of_week.push(day);
  }
};

const submit = () => {
  form.post('/admin/marketing/coupons', {
    onSuccess: () => {
      router.visit('/admin/marketing/coupons');
    },
  });
};
</script>

<template>
  <AdminLayout :title="$t('Create Coupon')">
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $t('Create Coupon') }}</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $t('Create a new discount coupon') }}</p>
        </div>
        <div class="flex gap-3">
          <Link
            href="/admin/marketing/coupons"
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            {{ $t('Cancel') }}
          </Link>
          <button
            @click="submit"
            :disabled="form.processing"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t('Save Coupon') }}
          </button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="border-b border-gray-200 dark:border-gray-700">
          <nav class="flex -mb-px">
            <button
              @click="activeTab = 'general'"
              :class="[
                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'general'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
              ]"
            >
              {{ $t('General') }}
            </button>
            <button
              @click="activeTab = 'conditions'"
              :class="[
                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'conditions'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
              ]"
            >
              {{ $t('Conditions') }}
            </button>
            <button
              @click="activeTab = 'limits'"
              :class="[
                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'limits'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
              ]"
            >
              {{ $t('Usage Limits') }}
            </button>
            <button
              @click="activeTab = 'restrictions'"
              :class="[
                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'restrictions'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
              ]"
            >
              {{ $t('Restrictions') }}
            </button>
          </nav>
        </div>

        <form @submit.prevent="submit" class="p-6">
          <!-- General Tab -->
          <div v-show="activeTab === 'general'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Basic Information') }}</h3>
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Coupon Code') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      id="code"
                      v-model="form.code"
                      type="text"
                      placeholder="SAVE20"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase dark:bg-gray-700/50 dark:text-gray-100"
                      :class="{ 'border-red-500': form.errors.code }"
                    />
                    <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                  </div>

                  <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Display Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      id="name"
                      v-model="form.name"
                      type="text"
                      :placeholder="$t('20% Off All Orders')"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                      :class="{ 'border-red-500': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                  </div>
                </div>

                <div>
                  <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Description') }}</label>
                  <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    :placeholder="$t('Brief description of the coupon')"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                  ></textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Discount Type') }} <span class="text-red-500">*</span>
                    </label>
                    <select
                      id="type"
                      v-model="form.type"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                      :class="{ 'border-red-500': form.errors.type }"
                    >
                      <option value="percentage">{{ $t('Percentage Discount') }}</option>
                      <option value="fixed_amount">{{ $t('Fixed Amount') }}</option>
                      <option value="free_shipping">{{ $t('Free Shipping') }}</option>
                      <option value="buy_x_get_y">{{ $t('Buy X Get Y') }}</option>
                      <option value="fixed_price">{{ $t('Fixed Price') }}</option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
                  </div>

                  <div v-if="showValue">
                    <label for="value" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ form.type === 'percentage' ? $t('Percentage (%)') : $t('Amount ($)') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      id="value"
                      v-model.number="form.value"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                      :class="{ 'border-red-500': form.errors.value }"
                    />
                    <p v-if="form.errors.value" class="mt-1 text-sm text-red-600">{{ form.errors.value }}</p>
                  </div>

                  <div v-if="showMaxDiscount">
                    <label for="max_discount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Max Discount ($)') }}</label>
                    <input
                      id="max_discount"
                      v-model.number="form.max_discount"
                      type="number"
                      step="0.01"
                      min="0"
                      :placeholder="$t('Optional')"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                  </div>
                </div>

                <!-- Buy X Get Y Fields -->
                <div v-if="showBuyXGetY" class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="buy_quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Buy Quantity') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      id="buy_quantity"
                      v-model.number="form.buy_quantity"
                      type="number"
                      min="1"
                      placeholder="2"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                  </div>
                  <div>
                    <label for="get_quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Get Quantity') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      id="get_quantity"
                      v-model.number="form.get_quantity"
                      type="number"
                      min="1"
                      placeholder="1"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Start Date') }} <span class="text-red-500">*</span>
                    </label>
                    <DatePicker
                      id="start_date"
                      v-model="form.start_date"
                      with-time
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      :class="{ 'border-red-500': form.errors.start_date }"
                    />
                    <p v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">{{ form.errors.start_date }}</p>
                  </div>
                  <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('End Date') }}</label>
                    <DatePicker
                      id="end_date"
                      v-model="form.end_date"
                      with-time
                      :placeholder="$t('Optional - no expiry')"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('Leave empty for no expiration') }}</p>
                  </div>
                </div>

                <div class="flex items-center gap-6">
                  <label class="flex items-center">
                    <input
                      type="checkbox"
                      v-model="form.is_active"
                      class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('Active') }}</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="checkbox"
                      v-model="form.is_public"
                      class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('Public (visible to customers)') }}</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="checkbox"
                      v-model="form.auto_apply"
                      class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('Auto Apply') }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Conditions Tab -->
          <div v-show="activeTab === 'conditions'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Order Conditions') }}</h3>
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="min_order_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Minimum Order Amount ($)') }}
                    </label>
                    <input
                      id="min_order_amount"
                      v-model.number="form.min_order_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      :placeholder="$t('Optional')"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                  </div>
                  <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Priority') }}</label>
                    <input
                      id="priority"
                      v-model.number="form.priority"
                      type="number"
                      min="0"
                      :placeholder="$t('0 = highest')"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                  </div>
                </div>

                <div class="flex items-center gap-6">
                  <label class="flex items-center">
                    <input
                      type="checkbox"
                      v-model="form.first_order_only"
                      class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('First Order Only') }}</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="checkbox"
                      v-model="form.stackable"
                      class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('Can Stack with Other Coupons') }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Usage Limits Tab -->
          <div v-show="activeTab === 'limits'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Usage Limits') }}</h3>
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="usage_limit_total" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Total Usage Limit') }}
                    </label>
                    <input
                      id="usage_limit_total"
                      v-model.number="form.usage_limit_total"
                      type="number"
                      min="1"
                      :placeholder="$t('Unlimited')"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('Leave empty for unlimited uses') }}</p>
                  </div>
                  <div>
                    <label for="usage_limit_per_customer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      {{ $t('Usage Limit Per Customer') }}
                    </label>
                    <input
                      id="usage_limit_per_customer"
                      v-model.number="form.usage_limit_per_customer"
                      type="number"
                      min="1"
                      :placeholder="$t('Unlimited')"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('How many times each customer can use this') }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Restrictions Tab -->
          <div v-show="activeTab === 'restrictions'" class="space-y-6">
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Time Restrictions') }}</h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Days of Week') }}</label>
                  <div class="grid grid-cols-4 gap-3">
                    <label
                      v-for="day in daysOfWeek"
                      :key="day.value"
                      class="flex items-center"
                    >
                      <input
                        type="checkbox"
                        :checked="form.days_of_week.includes(day.value)"
                        @change="toggleDay(day.value)"
                        class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                      />
                      <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ day.label }}</span>
                    </label>
                  </div>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('Leave empty to allow all days') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="time_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Start Time') }}</label>
                    <input
                      id="time_start"
                      v-model="form.time_restrictions.start"
                      type="time"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                  </div>
                  <div>
                    <label for="time_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('End Time') }}</label>
                    <input
                      id="time_end"
                      v-model="form.time_restrictions.end"
                      type="time"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Customer Restrictions') }}</h3>
              <div class="space-y-4">
                <div>
                  <label for="min_account_age_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ $t('Minimum Account Age (Days)') }}
                  </label>
                  <input
                    id="min_account_age_days"
                    v-model.number="form.min_account_age_days"
                    type="number"
                    min="0"
                    :placeholder="$t('Optional')"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                  />
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('Customer account must be at least this many days old') }}</p>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
