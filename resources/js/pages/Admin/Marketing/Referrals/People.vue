<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import { Search, Filter, X, Lock, Users, Copy, Check } from 'lucide-vue-next';

interface Person {
  id: number;
  name: string;
  email: string;
  code: string;
  code_status: string;
  clicks: number;
  referred_count: number;
  rewarded_count: number;
  invited_by: number | null;
  available: number;
  locked: number;
  earned: number;
  created_at: string | null;
}

interface Props {
  people: {
    data: Person[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  filters: {
    search?: string;
    filter?: string;
  };
}

const props = defineProps<Props>();

const { formatPrice } = useCurrency();

const searchQuery = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.filter ?? 'all');

// Inertia keeps the old page props alive until the new response lands, so the
// search box has to be re-seeded or it will show a stale word after navigating.
watch(() => props.filters, (value) => {
  searchQuery.value = value.search ?? '';
  statusFilter.value = value.filter ?? 'all';
});

const applyFilters = () => {
  router.get(
    '/admin/marketing/referrals/people',
    { search: searchQuery.value || undefined, filter: statusFilter.value !== 'all' ? statusFilter.value : undefined },
    { preserveState: true, replace: true }
  );
};

const clearFilters = () => {
  searchQuery.value = '';
  statusFilter.value = 'all';
  router.get('/admin/marketing/referrals/people', {}, { preserveState: true, replace: true });
};

const copied = ref<number | null>(null);

const copyCode = async (person: Person) => {
  try {
    await navigator.clipboard.writeText(person.code);
    copied.value = person.id;
    setTimeout(() => {
      if (copied.value === person.id) copied.value = null;
    }, 1500);
  } catch {
    // Clipboard access can be refused by the browser. Nothing is lost, the code
    // is on screen and can be selected by hand.
  }
};
</script>

<template>
  <Head :title="$t('Referral People')" />

  <AdminLayout :title="$t('Referral People')">
    <div class="p-6 space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('People') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('Every customer taking part in the programme, with their code and balances.') }}
        </p>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
          <div class="md:col-span-8">
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
              {{ $t('Search') }}
            </label>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                v-model="searchQuery"
                type="text"
                :placeholder="$t('Name, email or code...')"
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all placeholder:text-gray-400"
                @keyup.enter="applyFilters"
              />
            </div>
          </div>

          <div class="md:col-span-4">
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
              {{ $t('Invites') }}
            </label>
            <div class="relative">
              <Filter class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <select
                v-model="statusFilter"
                @change="applyFilters"
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all appearance-none cursor-pointer"
              >
                <option value="all">{{ $t('Everyone') }}</option>
                <option value="earned">{{ $t('Has earned a reward') }}</option>
                <option value="pending">{{ $t('Has a pending invite') }}</option>
                <option value="none">{{ $t('Has not invited anyone') }}</option>
              </select>
            </div>
          </div>
        </div>

        <div v-if="searchQuery || statusFilter !== 'all'" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
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
        <div v-if="people.data.length === 0" class="px-6 py-12 text-center">
          <Users class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600" />
          <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            {{
              searchQuery || statusFilter !== 'all'
                ? $t('Nobody matches those filters.')
                : $t('No customers have joined the programme yet.')
            }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Customer') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Code') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Invites') }}</th>
                <th class="hidden md:table-cell px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Clicks') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Available') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Locked') }}</th>
                <th class="px-6 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="person in people.data" :key="person.id" class="hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-colors">
                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">{{ person.name }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ person.email }}</div>
                  <div v-if="person.invited_by" class="mt-0.5 text-[10px] text-purple-500 dark:text-purple-400">
                    {{ $t('Invited by another member') }}
                  </div>
                </td>

                <td class="px-6 py-4">
                  <button
                    @click="copyCode(person)"
                    class="inline-flex items-center gap-1.5 font-mono text-sm text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                    :title="copied === person.id ? $t('Copied') : $t('Copy code')"
                  >
                    {{ person.code }}
                    <Check v-if="copied === person.id" class="w-3.5 h-3.5 text-green-600" />
                    <Copy v-else class="w-3.5 h-3.5 text-gray-400" />
                  </button>
                  <div v-if="person.code_status === 'disabled'" class="mt-0.5 text-[10px] text-red-500">
                    {{ $t('Code disabled') }}
                  </div>
                </td>

                <td class="px-6 py-4 text-right">
                  <div class="text-sm text-gray-900 dark:text-white">{{ person.referred_count }}</div>
                  <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ $t('{count} rewarded', { count: person.rewarded_count }) }}</div>
                </td>

                <td class="hidden md:table-cell px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">
                  {{ person.clicks }}
                </td>

                <td class="px-6 py-4 text-right text-sm font-medium text-green-600 dark:text-green-400">
                  {{ formatPrice(person.available) }}
                </td>

                <td class="px-6 py-4 text-right text-sm text-amber-600 dark:text-amber-400">
                  <span class="inline-flex items-center">
                    <Lock v-if="person.locked > 0" class="w-3 h-3 mr-1" />
                    {{ formatPrice(person.locked) }}
                  </span>
                </td>

                <td class="px-6 py-4 text-right whitespace-nowrap">
                  <Link
                    :href="`/admin/marketing/referrals/people/${person.id}`"
                    class="text-blue-600 dark:text-blue-400 hover:underline text-xs font-semibold"
                  >
                    {{ $t('Open') }}
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="people.data.length > 0" class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
          <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ $t('Showing {from} to {to} of {total} people', { from: people.from, to: people.to, total: people.total }) }}
          </div>
          <div class="flex gap-2">
            <Link
              v-if="people.prev_page_url"
              :href="people.prev_page_url"
              class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
            >
              {{ $t('Previous') }}
            </Link>
            <Link
              v-if="people.next_page_url"
              :href="people.next_page_url"
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
