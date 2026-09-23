<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { debounce } from 'lodash';
import {
  Search,
  Plus,
  MapPin,
  Eye,
  User,
  Calendar,
  X,
  Trash2,
  RefreshCw,
  Phone,
} from 'lucide-vue-next';

interface Driver {
  id: number;
  name: string;
  email: string;
  phone: string | null;
}

interface ShipmentOption {
  id: number;
  shipment_number: string;
  order_number: string | null;
  customer: string | null;
  status: string;
}

interface DeliveryRow {
  id: number;
  status: string;
  status_badge: { label: string; class: string } | null;
  priority: string;
  scheduled_date: string | null;
  customer_phone: string | null;
  cod_amount: number | null;
  shipment_number: string;
  order_number: string | null;
  order: {
    id: number;
    order_number: string;
    customer_email: string;
  } | null;
  assigned_to: {
    id: number;
    name: string;
  } | null;
}

interface Props {
  deliveries: {
    data: DeliveryRow[];
    current_page: number;
    last_page: number;
    total: number;
  };
  filters: { status?: string; driver?: string; search?: string };
  statuses: Array<{ value: string; label: string }>;
  drivers: Driver[];
  availableShipments: ShipmentOption[];
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const driverFilter = ref(props.filters.driver || '');

const applyFilters = () => {
  router.get('/admin/sales/deliveries', {
    search: search.value,
    status: statusFilter.value,
    driver: driverFilter.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const performSearch = debounce(applyFilters, 300);

const showAssign = ref(false);
const assignForm = useForm({
  shipment_id: '' as unknown as number,
  assigned_to: '' as unknown as number,
  scheduled_date: '',
  priority: 'normal',
  cod_amount: '',
  notes: '',
});

const submitAssign = () => {
  assignForm.post('/admin/sales/deliveries', {
    onSuccess: () => {
      showAssign.value = false;
      assignForm.reset();
    },
  });
};

const reassignTarget = ref<DeliveryRow | null>(null);
const reassignForm = useForm({ assigned_to: '' as unknown as number });

const openReassign = (row: DeliveryRow) => {
  reassignTarget.value = row;
  reassignForm.reset();
  reassignForm.clearErrors();
};

const submitReassign = () => {
  if (!reassignTarget.value) return;
  reassignForm.put(`/admin/sales/deliveries/${reassignTarget.value.id}`, {
    onSuccess: () => {
      reassignTarget.value = null;
    },
  });
};

const cancelTarget = ref<DeliveryRow | null>(null);

const confirmCancel = (row: DeliveryRow) => {
  if (!window.confirm(`Cancel delivery for shipment ${row.shipment_number}?`)) return;
  router.post(`/admin/sales/deliveries/${row.id}/cancel`);
};

const terminalStatuses = ['delivered', 'undelivered', 'cancelled'];

const isTerminal = (status: string) => terminalStatuses.includes(status);
</script>

<template>
  <Head title="Deliveries" />

  <AdminLayout title="Deliveries">
    <div class="space-y-6">
      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-center gap-3">
            <div class="relative">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                v-model="search"
                type="text"
                placeholder="Search order / phone..."
                class="h-9 w-64 rounded-lg border border-gray-300 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                @input="performSearch"
              />
            </div>

            <select
              v-model="statusFilter"
              class="h-9 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none"
              @change="applyFilters"
            >
              <option value="">All statuses</option>
              <option v-for="s in props.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>

            <select
              v-model="driverFilter"
              class="h-9 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none"
              @change="applyFilters"
            >
              <option value="">All drivers</option>
              <option v-for="d in props.drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            @click="showAssign = true"
          >
            <Plus class="h-4 w-4" />
            Assign Delivery
          </button>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Delivery</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Order</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Driver</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Priority</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Scheduled</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">COD</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template v-if="props.deliveries.data.length">
                <tr v-for="d in props.deliveries.data" :key="d.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ d.shipment_number }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    <div>{{ d.order?.order_number }}</div>
                    <div class="text-xs text-gray-400">{{ d.order?.customer_email }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    <div class="flex items-center gap-1.5">
                      <User class="h-3.5 w-3.5 text-gray-400" />
                      {{ d.assigned_to?.name || '—' }}
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="d.status_badge" :class="d.status_badge.class" class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium">
                      {{ d.status_badge.label }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ d.priority }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    <span v-if="d.scheduled_date" class="inline-flex items-center gap-1">
                      <Calendar class="h-3.5 w-3.5 text-gray-400" />
                      {{ new Date(d.scheduled_date).toLocaleDateString() }}
                    </span>
                    <span v-else>—</span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ d.cod_amount != null ? `$${d.cod_amount}` : '—' }}</td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <Link :href="`/admin/sales/deliveries/${d.id}`" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-blue-600" title="View">
                        <Eye class="h-4 w-4" />
                      </Link>
                      <button
                        v-if="!isTerminal(d.status)"
                        type="button"
                        class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-blue-600"
                        title="Reassign"
                        :disabled="!props.drivers.length"
                        @click="openReassign(d)"
                      >
                        <RefreshCw class="h-4 w-4" />
                      </button>
                      <button
                        v-if="!isTerminal(d.status)"
                        type="button"
                        class="rounded-lg p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600"
                        title="Cancel"
                        @click="confirmCancel(d)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-else>
                <td colspan="8" class="px-4 py-16 text-center">
                  <MapPin class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                  <p class="text-sm text-gray-500">No deliveries yet. Assign a shipment to a driver to get started.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="props.deliveries.last_page > 1" class="flex items-center justify-between border-t border-gray-200 px-4 py-3">
          <p class="text-sm text-gray-500">
            Page {{ props.deliveries.current_page }} of {{ props.deliveries.last_page }} ({{ props.deliveries.total }} total)
          </p>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="props.deliveries.current_page <= 1"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-40"
              @click="router.get('/admin/sales/deliveries', { page: props.deliveries.current_page - 1, ...props.filters })"
            >
              Previous
            </button>
            <button
              type="button"
              :disabled="props.deliveries.current_page >= props.deliveries.last_page"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-40"
              @click="router.get('/admin/sales/deliveries', { page: props.deliveries.current_page + 1, ...props.filters })"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign delivery -->
    <div v-if="showAssign" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showAssign = false">
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Assign Delivery</h3>
          <button type="button" class="rounded-lg p-1 hover:bg-gray-100" @click="showAssign = false">
            <X class="h-5 w-5 text-gray-500" />
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="submitAssign">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Shipment</label>
            <select
              v-model="assignForm.shipment_id"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="" disabled>Select a shipment</option>
              <option v-for="s in props.availableShipments" :key="s.id" :value="s.id">
                {{ s.shipment_number }} — {{ s.order_number || s.customer || '' }}
              </option>
            </select>
            <p v-if="assignForm.errors.shipment_id" class="mt-1 text-xs text-red-600">{{ assignForm.errors.shipment_id }}</p>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Delivery Person</label>
            <select
              v-model="assignForm.assigned_to"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="" disabled>Select a driver</option>
              <option v-for="d in props.drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <p v-if="assignForm.errors.assigned_to" class="mt-1 text-xs text-red-600">{{ assignForm.errors.assigned_to }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Scheduled Date</label>
              <input
                v-model="assignForm.scheduled_date"
                type="date"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Priority</label>
              <select
                v-model="assignForm.priority"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option value="normal">Normal</option>
                <option value="high">High</option>
              </select>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">COD Amount (USD)</label>
            <input
              v-model="assignForm.cod_amount"
              type="number"
              min="0"
              step="0.01"
              placeholder="Cash on delivery amount, if any"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
            <textarea
              v-model="assignForm.notes"
              rows="2"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="showAssign = false">Cancel</button>
            <button
              type="submit"
              :disabled="assignForm.processing"
              class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {{ assignForm.processing ? 'Assigning...' : 'Assign' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reassign -->
    <div v-if="reassignTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="reassignTarget = null">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Reassign delivery</h3>
          <button type="button" class="rounded-lg p-1 hover:bg-gray-100" @click="reassignTarget = null">
            <X class="h-5 w-5 text-gray-500" />
          </button>
        </div>
        <form class="space-y-4" @submit.prevent="submitReassign">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">New Delivery Person</label>
            <select
              v-model="reassignForm.assigned_to"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="" disabled>Select a driver</option>
              <option v-for="d in props.drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <p v-if="reassignForm.errors.assigned_to" class="mt-1 text-xs text-red-600">{{ reassignForm.errors.assigned_to }}</p>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="reassignTarget = null">Cancel</button>
            <button
              type="submit"
              :disabled="reassignForm.processing"
              class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
            >
              Reassign
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>