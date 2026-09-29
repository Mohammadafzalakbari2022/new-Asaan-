<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import { Search, Filter, X, Lock, Undo2, CheckCircle2, Ban } from 'lucide-vue-next';

interface Commission {
  id: number;
  referrer_name: string;
  referrer_email: string | null;
  referred_name: string;
  order_number: string | null;
  level: number;
  amount: number;
  reward_snapshot: number;
  share_snapshot: number;
  status: string;
  unlocks_at: string | null;
  is_locked: boolean;
  reversal_reason: string | null;
  created_at: string | null;
}

interface Props {
  commissions: {
    data: Commission[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  filters: {
    search?: string;
    status?: string;
    level?: string;
  };
}

const props = defineProps<Props>();

const { formatPrice } = useCurrency();

const searchQuery = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const levelFilter = ref(props.filters.level ?? '');

watch(() => props.filters, (value) => {
  searchQuery.value = value.search ?? '';
  statusFilter.value = value.status ?? '';
  levelFilter.value = value.level ?? '';
});

const applyFilters = () => {
  router.get(
    '/admin/marketing/referrals/commissions',
    {
      search: searchQuery.value || undefined,
      status: statusFilter.value || undefined,
      level: levelFilter.value || undefined,
    },
    { preserveState: true, replace: true }
  );
};

const clearFilters = () => {
  searchQuery.value = '';
  statusFilter.value = '';
  levelFilter.value = '';
  router.get('/admin/marketing/referrals/commissions', {}, { preserveState: true, replace: true });
};

const reverseForm = useForm({ reason: '' });
const reversing = ref<number | null>(null);

const openReverse = (commission: Commission) => {
  reversing.value = reversing.value === commission.id ? null : commission.id;
  reverseForm.reason = '';
  reverseForm.clearErrors();
};

const submitReverse = (commission: Commission) => {
  reverseForm.post(`/admin/marketing/referrals/commissions/${commission.id}/reverse`, {
    preserveScroll: true,
    onSuccess: () => {
      reversing.value = null;
      reverseForm.reset();
    },
  });
};

const pageTotal = (data: Commission[]) => data.reduce((sum, c) => sum + (c.status === 'active' ? c.amount : 0), 0);
</script>

<template>
  <Head :title="$t('Referral Rewards')" />

  <AdminLayout :title="$t('Referral Rewards')">
    <div class="p-6 space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Rewards') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('Every referral reward that has been granted, with the order that triggered it.') }}
        </p>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
          <div class="md:col-span-6">
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">{{ $t('Search') }}</label>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                v-model="searchQuery"
                type="text"
                :placeholder="$t('Customer or order number...')"
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all placeholder:text-gray-400"
                @keyup.enter="applyFilters"
              />
            </div>
          </div>

          <div class="md:col-span-3">
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">{{ $t('Status') }}</label>
            <div class="relative">
              <Filter class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <select
                v-model="statusFilter"
                @change="applyFilters"
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all appearance-none cursor-pointer"
              >
                <option value="">{{ $t('Any status') }}</option>
                <option value="active">{{ $t('Active') }}</option>
                <option value="reversed">{{ $t('Reversed') }}</option>
                <option value="locked">{{ $t('Locked right now') }}</option>
                <option value="available">{{ $t('Available to spend') }}</option>
              </select>
            </div>
          </div>

          <div class="md:col-span-3">
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">{{ $t('Level') }}</label>
            <select
              v-model="levelFilter"
              @change="applyFilters"
              class="w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all appearance-none cursor-pointer"
            >
              <option value="">{{ $t('Any level') }}</option>
              <option value="1">{{ $t('Level 1') }}</option>
              <option value="2">{{ $t('Level 2') }}</option>
            </select>
          </div>
        </div>

        <div v-if="searchQuery || statusFilter || levelFilter" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
          <button
            @click="clearFilters"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors flex items-center gap-2"
          >
            <X class="w-4 h-4" />
            {{ $t('Clear Filters') }}
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div v-if="commissions.data.length === 0" class="px-6 py-12 text-center">
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{
              searchQuery || statusFilter || levelFilter
                ? $t('No rewards match those filters.')
                : $t('No rewards have been granted yet.')
            }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Earned by') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('From') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Order') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Amount') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Status') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <template v-for="c in commissions.data" :key="c.id">
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/20">
                  <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ c.referrer_name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $t('Level {level}', { level: c.level }) }}</div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ c.referred_name }}</td>
                  <td class="px-6 py-4">
                    <div class="text-sm font-mono text-gray-900 dark:text-white">{{ c.order_number ?? '—' }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ c.created_at }}</div>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatPrice(c.amount) }}</div>
                    <div class="text-[10px] text-gray-400 dark:text-gray-500">
                      {{ $t('{share}% of {amount}', { share: c.share_snapshot, amount: formatPrice(c.reward_snapshot) }) }}
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span
                      v-if="c.status === 'reversed'"
                      class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300"
                      :title="c.reversal_reason ?? undefined"
                    >
                      <Ban class="w-3 h-3" />
                      {{ $t('Reversed') }}
                    </span>
                    <span
                      v-else-if="c.is_locked"
                      class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                    >
                      <Lock class="w-3 h-3" />
                      {{ $t('Until {date}', { date: c.unlocks_at }) }}
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                    >
                      <CheckCircle2 class="w-3 h-3" />
                      {{ $t('Available') }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <button
                      v-if="c.status === 'active'"
                      @click="openReverse(c)"
                      class="inline-flex items-center text-xs font-semibold text-red-600 dark:text-red-400 hover:underline"
                    >
                      <Undo2 class="w-3.5 h-3.5 mr-1" />
                      {{ $t('Reverse') }}
                    </button>
                    <span v-else class="text-xs text-gray-400">—</span>
                  </td>
                </tr>

                <tr v-if="reversing === c.id" class="bg-red-50 dark:bg-red-900/10">
                  <td colspan="6" class="px-6 py-4">
                    <label class="block text-xs font-semibold text-red-800 dark:text-red-300 mb-1.5">
                      {{ $t('Why is this being taken back? The customer sees this in their history.') }}
                    </label>
                    <input
                      v-model="reverseForm.reason"
                      type="text"
                      :placeholder="$t('e.g. The referred order was refunded')"
                      class="w-full px-3 py-2 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500/20 focus:border-red-500"
                      @keyup.enter="submitReverse(c)"
                    />
                    <p v-if="reverseForm.errors.reason" class="mt-1 text-xs text-red-600 dark:text-red-400">
                      {{ reverseForm.errors.reason }}
                    </p>
                    <div class="mt-3 flex gap-2">
                      <button
                        @click="submitReverse(c)"
                        :disabled="reverseForm.processing"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg disabled:opacity-50"
                      >
                        {{ reverseForm.processing ? $t('Reversing...') : $t('Take back') }}
                      </button>
                      <button
                        @click="reversing = null"
                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg"
                      >
                        {{ $t('Cancel') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div v-if="commissions.data.length > 0" class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
          <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ $t('Showing {from} to {to} of {total} rewards', { from: commissions.from, to: commissions.to, total: commissions.total }) }}
            ({{ $t('{amount} active on this page', { amount: formatPrice(pageTotal(commissions.data)) }) }})
          </div>
          <div class="flex gap-2">
            <Link
              v-if="commissions.prev_page_url"
              :href="commissions.prev_page_url"
              class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
            >
              {{ $t('Previous') }}
            </Link>
            <Link
              v-if="commissions.next_page_url"
              :href="commissions.next_page_url"
              class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
            >
              {{ $t('Next') }}
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

