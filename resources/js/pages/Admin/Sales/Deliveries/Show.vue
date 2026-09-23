<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ArrowLeft, MapPin, Phone, User, Calendar, Package, Banknote, Trash2, RefreshCw } from 'lucide-vue-next';

interface Driver {
  id: number;
  name: string;
  email: string;
  phone: string | null;
}

interface EventRow {
  id: number;
  from_status: string | null;
  to_status: string | null;
  status_change: string | null;
  note: string | null;
  created_at: string;
  actor: { id: number; name: string } | null;
}

interface DeliveryDetail {
  id: number;
  shipment_number: string;
  status: string;
  status_badge: { label: string; class: string };
  priority: string;
  scheduled_date: string | null;
  customer_phone: string | null;
  cod_amount: string | null;
  cod_received: string;
  recipient_name: string | null;
  failure_reason: string | null;
  failure_note: string | null;
  delivered_photo_path: string | null;
  last_latitude: string | null;
  last_longitude: string | null;
  notes: string | null;
  created_at: string;
  order: {
    id: number;
    order_number: string;
    customer_email: string;
    shipping_address: {
      id: number;
      full_name: string;
      full_address: string;
      phone: string | null;
    } | null;
  } | null;
  assigned_to: { id: number; name: string } | null;
  events: EventRow[];
}

interface Props {
  delivery: DeliveryDetail;
  drivers: Driver[];
  statuses: Array<{ value: string; label: string }>;
}

const props = defineProps<Props>();

const terminalStatuses = ['delivered', 'undelivered', 'cancelled'];
const isTerminal = (s: string) => terminalStatuses.includes(s);

const editForm = useForm({
  assigned_to: props.delivery.assigned_to?.id ?? '' as unknown as number,
  scheduled_date: props.delivery.scheduled_date ? props.delivery.scheduled_date.slice(0, 10) : '',
});

const saving = ref(false);

const saveChanges = () => {
  editForm.put(`/admin/sales/deliveries/${props.delivery.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      saving.value = false;
    },
  });
};

const cancelDelivery = () => {
  if (!window.confirm('Cancel this delivery?')) return;
  router.post(`/admin/sales/deliveries/${props.delivery.id}/cancel`);
};
</script>

<template>
  <Head :title="`Delivery ${props.delivery.order?.order_number || ''}`" />

  <AdminLayout :title="`Delivery ${props.delivery.order?.order_number || ''}`">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <Link href="/admin/sales/deliveries" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900">
          <ArrowLeft class="h-4 w-4" /> Back to deliveries
        </Link>
        <span v-if="props.delivery.status_badge" :class="props.delivery.status_badge.class" class="inline-flex rounded-full px-3 py-1 text-xs font-medium">
          {{ props.delivery.status_badge.label }}
        </span>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Package</h3>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Shipment</span>
                <span class="font-medium text-gray-900">{{ props.delivery.shipment_number }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Order</span>
                <span class="font-medium text-gray-900">{{ props.delivery.order?.order_number }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Customer</span>
                <span class="font-medium text-gray-900">{{ props.delivery.order?.customer_email }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Priority</span>
                <span class="font-medium capitalize text-gray-900">{{ props.delivery.priority }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">COD</span>
                <span class="font-medium text-gray-900">{{ props.delivery.cod_amount ? `$${props.delivery.cod_amount}` : '—' }}</span>
              </div>
            </div>

            <div v-if="props.delivery.order?.shipping_address" class="mt-4 rounded-lg bg-gray-50 p-4">
              <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                <MapPin class="h-4 w-4 text-gray-400" /> {{ props.delivery.order.shipping_address.full_name }}
              </p>
              <p class="mt-1 text-sm text-gray-600">{{ props.delivery.order.shipping_address.full_address }}</p>
              <p v-if="props.delivery.order.shipping_address.phone" class="mt-2 inline-flex items-center gap-1.5 text-sm text-gray-600">
                <Phone class="h-3.5 w-3.5 text-gray-400" />
                <a :href="`tel:${props.delivery.customer_phone || props.delivery.order.shipping_address.phone}`" class="text-blue-600 hover:underline">
                  {{ props.delivery.customer_phone || props.delivery.order.shipping_address.phone }}
                </a>
              </p>
            </div>
          </div>

          <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Timeline</h3>
            <ol v-if="props.delivery.events.length" class="space-y-4">
              <li v-for="e in props.delivery.events" :key="e.id" class="relative flex gap-3">
                <div class="mt-1 flex h-2.5 w-2.5 flex-none rounded-full bg-blue-500" />
                <div>
                  <p class="text-sm font-medium text-gray-900">
                    {{ e.status_change || 'Assigned' }}
                    <span v-if="e.actor" class="font-normal text-gray-500">· {{ e.actor.name }}</span>
                  </p>
                  <p v-if="e.note" class="text-sm text-gray-600">{{ e.note }}</p>
                  <p class="text-xs text-gray-400">{{ new Date(e.created_at).toLocaleString() }}</p>
                </div>
              </li>
            </ol>
            <p v-else class="text-sm text-gray-500">No events recorded yet.</p>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Assignment</h3>
            <form v-if="!isTerminal(props.delivery.status)" class="space-y-4" @submit.prevent="saveChanges">
              <div>
                <label class="mb-1 flex items-center gap-1 text-sm font-medium text-gray-700">
                  <User class="h-3.5 w-3.5 text-gray-400" /> Delivery Person
                </label>
                <select
                  v-model="editForm.assigned_to"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option v-for="d in props.drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 flex items-center gap-1 text-sm font-medium text-gray-700">
                  <Calendar class="h-3.5 w-3.5 text-gray-400" /> Scheduled Date
                </label>
                <input
                  v-model="editForm.scheduled_date"
                  type="date"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <button
                type="submit"
                :disabled="editForm.processing"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
              >
                <RefreshCw class="h-4 w-4" /> Save
              </button>
            </form>
            <div v-else class="flex items-center gap-3">
              <User class="h-4 w-4 text-gray-400" />
              <span class="text-sm text-gray-700">{{ props.delivery.assigned_to?.name }}</span>
            </div>

            <button
              v-if="!isTerminal(props.delivery.status)"
              type="button"
              class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
              @click="cancelDelivery"
            >
              <Trash2 class="h-4 w-4" /> Cancel Delivery
            </button>
          </div>

          <div v-if="props.delivery.cod_amount" class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="mb-3 flex items-center gap-1.5 text-base font-semibold text-gray-900">
              <Banknote class="h-4 w-4 text-gray-400" /> Cash on Delivery
            </h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Expected</span>
                <span class="font-medium text-gray-900">${{ props.delivery.cod_amount }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Collected</span>
                <span class="font-medium text-gray-900">${{ props.delivery.cod_received || '0.00' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>