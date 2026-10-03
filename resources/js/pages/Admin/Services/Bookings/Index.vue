<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import DatePicker from '@/components/Calendar/DatePicker.vue';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import { useI18nStore } from '@/Stores/i18n';
import { Search, Download, Eye, User, CalendarClock, CheckCircle2, Inbox } from 'lucide-vue-next';

interface Booking {
  reference: string;
  service_name: string;
  customer_name: string;
  customer_phone: string;
  scheduled_date: string;
  scheduled_slot: string;
  status: string;
  status_label: string;
  price_snapshot: string;
  price_display: string;
  worker: { id: number; name: string } | null;
}

const props = defineProps<{
  bookings: {
    data: Booking[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
  };
  workers: { id: number; name: string; phone: string }[];
  statuses: Record<string, string>;
  filters: Record<string, string>;
  stats: { open: number; unassigned: number; today: number; completed: number };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const assignedTo = ref(props.filters.assigned_to ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

let debounce: ReturnType<typeof setTimeout> | undefined;

const { t } = useI18nStore();

/*
 * The booking list is the screen the owner checks first thing in the morning,
 * so the counters at the top are the three questions they actually have: how many
 * jobs are still live, how many nobody has picked up, and how many are on today.
 */
const cards = computed(() => [
  { label: t('Open jobs'), value: props.stats.open, icon: Inbox, tone: 'text-blue-600 bg-blue-50 dark:bg-blue-500/10' },
  { label: t('Nobody assigned'), value: props.stats.unassigned, icon: User, tone: 'text-amber-600 bg-amber-50 dark:bg-amber-500/10' },
  { label: t('Scheduled today'), value: props.stats.today, icon: CalendarClock, tone: 'text-purple-600 bg-purple-50 dark:bg-purple-500/10' },
  { label: t('Completed'), value: props.stats.completed, icon: CheckCircle2, tone: 'text-green-600 bg-green-50 dark:bg-green-500/10' },
]);

function apply() {
  router.get(
    '/admin/services/bookings',
    {
      search: search.value || undefined,
      status: status.value || undefined,
      assigned_to: assignedTo.value || undefined,
      from: from.value || undefined,
      to: to.value || undefined,
    },
    { preserveState: true, replace: true },
  );
}

watch(search, () => {
  clearTimeout(debounce);
  debounce = setTimeout(apply, 350);
});

watch([status, assignedTo, from, to], apply);

function clearFilters() {
  search.value = '';
  status.value = '';
  assignedTo.value = '';
  from.value = '';
  to.value = '';

  router.get('/admin/services/bookings', {}, { preserveState: true, replace: true });
}

function exportCsv() {
  const params = new URLSearchParams();

  if (search.value) params.set('search', search.value);
  if (status.value) params.set('status', status.value);
  if (assignedTo.value) params.set('assigned_to', assignedTo.value);
  if (from.value) params.set('from', from.value);
  if (to.value) params.set('to', to.value);

  const query = params.toString();

  window.location.href = `/admin/services/bookings/export/csv${query ? `?${query}` : ''}`;
}

const statusTone: Record<string, string> = {
  booked: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
  assigned: 'bg-purple-100 text-purple-800 dark:bg-purple-500/15 dark:text-purple-300',
  in_progress: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
  completed: 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
  cancelled: 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-300',
};

const hasFilters = computed(
  () => !!(search.value || status.value || assignedTo.value || from.value || to.value),
);
</script>

<template>
  <Head :title="$t('Service Jobs')" />

  <AdminLayout :title="$t('Service Jobs')">
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $t('Service Jobs') }}</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Every job booked from the services pages, newest first.') }}
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
          @click="exportCsv"
        >
          <Download class="mr-2 h-4 w-4" />
          {{ $t('Export') }}
        </button>
      </div>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div
          v-for="card in cards"
          :key="card.label"
          class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg" :class="card.tone">
            <component :is="card.icon" class="h-5 w-5" />
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ card.label }}</p>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ card.value }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
          <div class="relative md:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Search') }}</label>
            <Search class="absolute left-3 top-[38px] h-4 w-4 text-gray-400" />
            <input
              v-model="search"
              type="text"
              :placeholder="$t('Reference, name or phone...')"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Status') }}</label>
            <select
              v-model="status"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            >
              <option value="">{{ $t('All') }}</option>
              <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Assigned to') }}</label>
            <select
              v-model="assignedTo"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            >
              <option value="">{{ $t('Anyone') }}</option>
              <option v-for="worker in workers" :key="worker.id" :value="worker.id">{{ worker.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('From') }}</label>
              <DatePicker
                v-model="from"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('To') }}</label>
              <DatePicker
                v-model="to"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
            </div>
          </div>
        </div>

        <div v-if="hasFilters" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
          <button type="button" class="text-sm text-blue-600 hover:underline" @click="clearFilters">
            {{ $t('Clear all filters') }}
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('Reference') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('Service') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('Customer') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('When') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('Worker') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('Price') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('Status') }}</th>
                <th class="px-4 py-3 text-right font-semibold">{{ $t('View') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="booking in bookings.data" :key="booking.reference" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ booking.reference }}</td>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ booking.service_name }}</td>
                <td class="px-4 py-3">
                  <p class="text-gray-900 dark:text-gray-100">{{ booking.customer_name }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ booking.customer_phone }}</p>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                  <p><DateDisplay :value="booking.scheduled_date" /></p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ booking.scheduled_slot }}</p>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                  <span v-if="booking.worker">{{ booking.worker.name }}</span>
                  <span v-else class="text-amber-600">{{ $t('Unassigned') }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ booking.price_display }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2 py-1 text-xs" :class="statusTone[booking.status] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-300'">
                    {{ booking.status_label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <Link
                    :href="`/admin/services/bookings/${booking.reference}`"
                    class="inline-flex items-center rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                  >
                    <Eye class="mr-1 h-3.5 w-3.5" /> {{ $t('Open') }}
                  </Link>
                </td>
              </tr>

              <tr v-if="bookings.data.length === 0">
                <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                  {{ $t('No jobs match these filters.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="bookings.last_page > 1" class="border-t border-gray-100 p-4 dark:border-gray-700">
          <Pagination :data="bookings as any" resource-name="bookings" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
