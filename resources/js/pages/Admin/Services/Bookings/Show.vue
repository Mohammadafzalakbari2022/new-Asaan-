<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import {
  ArrowLeft,
  MapPin,
  Phone,
  Mail,
  CalendarClock,
  Banknote,
  User,
  Play,
  CheckCircle2,
  XCircle,
  UserMinus,
  StickyNote,
  FileText,
} from 'lucide-vue-next';

interface Booking {
  reference: string;
  status: string;
  status_label: string;
  service_name: string;
  price_snapshot: string;
  price_display: string;
  price_unit: string;
  amount_collected: string | null;
  amountVariance: number;
  effectiveAmount: number;
  scheduled_date: string;
  scheduled_slot: string;
  customer_name: string;
  customer_phone: string;
  customer_email: string | null;
  address: string | null;
  city: string | null;
  notes: string | null;
  internal_notes: string | null;
  cancel_reason: string | null;
  source: string | null;
  created_at: string;
  assigned_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
  service: { id: number; name: string; slug: string } | null;
  order: { id: number; order_number: string } | null;
  worker: { id: number; name: string; phone: string } | null;
  assignedBy: { id: number; name: string } | null;
  user: { id: number; name: string } | null;
  events: {
    id: number;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    created_at: string;
    actor: { id: number; name: string } | null;
  }[];
}

const props = defineProps<{
  booking: Booking;
  workers: { id: number; name: string; phone: string }[];
  nextStatuses: string[];
  statusLabels: Record<string, string>;
}>();

const page = usePage();
const booking = props.booking;

const statusTone: Record<string, string> = {
  booked: 'bg-blue-100 text-blue-800',
  assigned: 'bg-purple-100 text-purple-800',
  in_progress: 'bg-amber-100 text-amber-800',
  completed: 'bg-green-100 text-green-800',
  cancelled: 'bg-gray-100 text-gray-600',
};

const isFinished = computed(() => ['completed', 'cancelled'].includes(booking.status));
const canStart = computed(() => booking.status === 'assigned');
const canComplete = computed(() => booking.status === 'in_progress');
const canCancel = computed(() => !isFinished.value);

const assignForm = useForm({
  assigned_to: booking.worker?.id ?? null,
  note: '',
});

const completeForm = useForm({
  amount_collected: booking.amount_collected ?? booking.price_snapshot,
  note: '',
});

const cancelForm = useForm({ cancel_reason: '' });
const noteForm = useForm({ internal_notes: booking.internal_notes ?? '' });

const showCancel = ref(false);
const showNote = ref(false);
const showComplete = ref(false);

function act(url: string, form: typeof assignForm, close?: () => void) {
  form.post(url, {
    preserveScroll: true,
    onSuccess: () => close?.(),
  });
}

function assign() {
  assignForm.post(`/admin/services/bookings/${booking.reference}/assign`, {
    preserveScroll: true,
    onSuccess: () => (assignForm.note = ''),
  });
}

function unassign() {
  act(`/admin/services/bookings/${booking.reference}/unassign`, assignForm);
}

function start() {
  router.post(`/admin/services/bookings/${booking.reference}/start`, {}, { preserveScroll: true });
}

function complete() {
  completeForm.post(`/admin/services/bookings/${booking.reference}/complete`, {
    preserveScroll: true,
    onSuccess: () => (showComplete.value = false),
  });
}

function cancel() {
  cancelForm.post(`/admin/services/bookings/${booking.reference}/cancel`, {
    preserveScroll: true,
    onSuccess: () => {
      showCancel.value = false;
      cancelForm.cancel_reason = '';
    },
  });
}

function saveNote() {
  noteForm.post(`/admin/services/bookings/${booking.reference}/note`, {
    preserveScroll: true,
    onSuccess: () => (showNote.value = false),
  });
}

function money(value: number | string) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value || 0));
}
</script>

<template>
  <Head :title="$t('Job {reference}', { reference: booking.reference })" />

  <AdminLayout :title="$t('Job {reference}', { reference: booking.reference })">
    <div class="p-6 space-y-6">
      <div>
        <Link
          href="/admin/services/bookings"
          class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400"
        >
          <ArrowLeft class="mr-1 h-4 w-4" /> {{ $t('Back to jobs') }}
        </Link>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3">
              <h1 class="font-mono text-2xl font-bold text-gray-900 dark:text-gray-100">{{ booking.reference }}</h1>
              <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusTone[booking.status]">
                {{ booking.status_label }}
              </span>
            </div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ $t('{service} on {date} ({slot})', { service: booking.service_name, date: booking.scheduled_date, slot: booking.scheduled_slot }) }}
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button
              v-if="canStart"
              type="button"
              :disabled="assignForm.processing"
              class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700 disabled:opacity-60"
              @click="start"
            >
              <Play class="mr-1.5 h-3.5 w-3.5" /> {{ $t('Mark started') }}
            </button>

            <button
              v-if="canComplete"
              type="button"
              class="inline-flex items-center rounded-lg bg-green-600 px-3.5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-green-700"
              @click="showComplete = true"
            >
              <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" /> {{ $t('Finish job') }}
            </button>

            <button
              v-if="canCancel"
              type="button"
              class="inline-flex items-center rounded-lg border border-red-200 px-3.5 py-2 text-xs font-semibold uppercase tracking-widest text-red-600 transition hover:bg-red-50 dark:border-red-900"
              @click="showCancel = true"
            >
              <XCircle class="mr-1.5 h-3.5 w-3.5" /> {{ $t('Cancel job') }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="page.props.errors?.error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {{ page.props.errors.error }}
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('Customer') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div class="flex items-start gap-3">
                <User class="mt-0.5 h-4 w-4 text-gray-400" />
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t('Name') }}</p>
                  <p class="text-sm text-gray-900 dark:text-gray-100">{{ booking.customer_name }}</p>
                  <p v-if="booking.user" class="text-xs text-gray-500">{{ $t('Signed in') }}</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <Phone class="mt-0.5 h-4 w-4 text-gray-400" />
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t('Phone') }}</p>
                  <a :href="`tel:${booking.customer_phone}`" class="text-sm text-blue-600 hover:underline">
                    {{ booking.customer_phone }}
                  </a>
                </div>
              </div>

              <div v-if="booking.customer_email" class="flex items-start gap-3">
                <Mail class="mt-0.5 h-4 w-4 text-gray-400" />
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t('Email') }}</p>
                  <a :href="`mailto:${booking.customer_email}`" class="text-sm text-blue-600 hover:underline">
                    {{ booking.customer_email }}
                  </a>
                </div>
              </div>

              <div v-if="booking.address || booking.city" class="flex items-start gap-3">
                <MapPin class="mt-0.5 h-4 w-4 text-gray-400" />
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t('Address') }}</p>
                  <p class="text-sm text-gray-900 dark:text-gray-100">
                    <span v-if="booking.address">{{ booking.address }}</span>
                    <span v-if="booking.address && booking.city">, </span>
                    <span v-if="booking.city">{{ booking.city }}</span>
                  </p>
                </div>
              </div>
            </div>

            <div v-if="booking.notes" class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-700">
              <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t('Customer notes') }}</p>
              <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ booking.notes }}</p>
            </div>
          </section>

          <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('History') }}</h2>

            <ol class="mt-4 space-y-4">
              <li v-for="event in booking.events" :key="event.id" class="flex gap-4">
                <div class="flex flex-col items-center">
                  <div
                    class="h-2.5 w-2.5 rounded-full"
                    :class="statusTone[event.to_status ?? ''] ? 'bg-blue-500' : 'bg-gray-300'"
                  />
                  <div class="mt-1 w-px flex-1 bg-gray-200 dark:bg-gray-700" />
                </div>
                <div class="pb-1">
                  <p class="text-sm text-gray-900 dark:text-gray-100">
                    <span v-if="event.from_status && event.to_status">
                      {{ $t('{from} to {to}', { from: statusLabels[event.from_status], to: statusLabels[event.to_status] }) }}
                    </span>
                    <span v-else-if="event.to_status">{{ statusLabels[event.to_status] ?? event.to_status }}</span>
                    <span v-else>{{ $t('Note added') }}</span>
                  </p>
                  <p class="text-xs text-gray-500">
                    {{ new Date(event.created_at).toLocaleString() }}
                    <span v-if="event.actor"> &middot; {{ event.actor.name }}</span>
                  </p>
                  <p v-if="event.note" class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">
                    {{ event.note }}
                  </p>
                </div>
              </li>
            </ol>
          </section>
        </div>

        <div class="space-y-6">
          <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('Money') }}</h2>
            <div class="mt-4 space-y-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Promised') }}</span>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ money(booking.price_snapshot) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Collected') }}</span>
                <span class="font-medium text-gray-900 dark:text-gray-100">
                  {{ booking.amount_collected !== null ? money(booking.amount_collected) : $t('Not recorded') }}
                </span>
              </div>
              <div
                v-if="booking.amount_collected !== null && booking.amountVariance !== 0"
                class="flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-700"
              >
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Difference') }}</span>
                <span
                  class="font-semibold"
                  :class="booking.amountVariance > 0 ? 'text-green-600' : 'text-red-600'"
                >
                  {{ booking.amountVariance > 0 ? '+' : '' }}{{ money(booking.amountVariance) }}
                </span>
              </div>
              <p class="text-xs text-gray-500">{{ booking.price_display }}</p>
            </div>

            <div v-if="booking.order" class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
              <FileText class="h-4 w-4 text-gray-400" />
              <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Order') }}
                <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ booking.order.order_number }}</span>
              </p>
            </div>
          </section>

          <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('When') }}</h2>
            <div class="mt-4 space-y-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Booked on') }}</span>
                <span class="text-gray-900 dark:text-gray-100"><DateDisplay :value="booking.created_at" /></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Scheduled') }}</span>
                <span class="text-gray-900 dark:text-gray-100">{{ booking.scheduled_date }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Slot') }}</span>
                <span class="text-gray-900 dark:text-gray-100">{{ booking.scheduled_slot }}</span>
              </div>
              <div v-if="booking.completed_at" class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Finished') }}</span>
                <span class="text-gray-900 dark:text-gray-100">
                  {{ new Date(booking.completed_at).toLocaleString() }}
                </span>
              </div>
              <div v-if="booking.cancel_reason" class="flex items-start justify-between gap-4">
                <span class="text-gray-600 dark:text-gray-400">{{ $t('Cancelled because') }}</span>
                <span class="text-right text-red-600">{{ booking.cancel_reason }}</span>
              </div>
            </div>
          </section>

          <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('Worker') }}</h2>

            <div v-if="booking.worker" class="mt-4">
              <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ booking.worker.name }}</p>
              <p v-if="booking.worker.phone" class="text-xs text-gray-500">{{ booking.worker.phone }}</p>
              <p v-if="booking.assignedBy" class="mt-1 text-xs text-gray-500">
                {{ $t('Assigned by {name}', { name: booking.assignedBy.name }) }}
              </p>
              <button
                v-if="canCancel"
                type="button"
                :disabled="assignForm.processing"
                class="mt-3 inline-flex items-center text-sm text-red-600 hover:underline disabled:opacity-60"
                @click="unassign"
              >
                <UserMinus class="mr-1.5 h-3.5 w-3.5" /> {{ $t('Take off this job') }}
              </button>
            </div>

            <form v-else class="mt-4 space-y-3" novalidate @submit.prevent="assign">
              <div>
                <label for="field-assigned_to" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('Who is doing it?') }}
                </label>
                <select
                  id="field-assigned_to"
                  v-model="assignForm.assigned_to"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                >
                  <option :value="null">{{ $t('Choose a worker') }}</option>
                  <option v-for="worker in workers" :key="worker.id" :value="worker.id">
                    {{ worker.name }}{{ worker.phone ? ` (${worker.phone})` : '' }}
                  </option>
                </select>
                <p v-if="page.props.errors?.assigned_to" class="mt-1 text-sm text-red-600">
                  {{ page.props.errors.assigned_to }}
                </p>
              </div>
              <div>
                <label for="field-assign-note" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('Note for the worker') }}
                </label>
                <input
                  id="field-assign-note"
                  v-model="assignForm.note"
                  type="text"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                />
              </div>
              <button
                type="submit"
                :disabled="assignForm.processing"
                class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700 disabled:opacity-60"
              >
                {{ assignForm.processing ? $t('Assigning...') : $t('Assign job') }}
              </button>
            </form>
          </section>

          <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('Private notes') }}</h2>
              <button type="button" class="text-sm text-blue-600 hover:underline" @click="showNote = true">
                {{ booking.internal_notes ? $t('Edit') : $t('Add') }}
              </button>
            </div>
            <p v-if="booking.internal_notes" class="mt-3 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">
              {{ booking.internal_notes }}
            </p>
            <p v-else class="mt-3 text-sm text-gray-500">{{ $t('Only your staff see this.') }}</p>
          </section>
        </div>
      </div>
    </div>

    <!-- Finishing a job always asks for the money taken, so an empty booking
         can never be marked done by accident. -->
    <div v-if="showComplete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $t('Finish this job') }}</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('How much did you take on the day? The customer was quoted {amount}.', { amount: money(booking.price_snapshot) }) }}
        </p>

        <form class="mt-4 space-y-4" novalidate @submit.prevent="complete">
          <div>
            <label for="field-amount_collected" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Amount collected') }} <span class="text-red-500">*</span>
            </label>
            <input
              id="field-amount_collected"
              v-model="completeForm.amount_collected"
              type="number"
              step="0.01"
              min="0"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              :class="page.props.errors?.amount_collected ? 'border-red-500' : ''"
            />
            <p v-if="page.props.errors?.amount_collected" class="mt-1 text-sm text-red-600">
              {{ page.props.errors.amount_collected }}
            </p>
          </div>

          <div>
            <label for="field-complete-note" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Note') }}
            </label>
            <input
              id="field-complete-note"
              v-model="completeForm.note"
              type="text"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            />
          </div>

          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="completeForm.processing"
              class="flex-1 rounded-lg bg-green-600 px-3 py-2.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-700 disabled:opacity-60"
            >
              {{ completeForm.processing ? $t('Saving...') : $t('Confirm') }}
            </button>
            <button
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-2.5 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:border-gray-600 dark:text-gray-200"
              @click="showComplete = false"
            >
              {{ $t('Back') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showCancel" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $t('Cancel this job') }}</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('The reason is kept on the record so nobody has to guess later.') }}
        </p>

        <form class="mt-4 space-y-4" novalidate @submit.prevent="cancel">
          <div>
            <label for="field-cancel_reason" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Why is it being cancelled?') }} <span class="text-red-500">*</span>
            </label>
            <input
              id="field-cancel_reason"
              v-model="cancelForm.cancel_reason"
              type="text"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              :class="page.props.errors?.cancel_reason ? 'border-red-500' : ''"
            />
            <p v-if="page.props.errors?.cancel_reason" class="mt-1 text-sm text-red-600">
              {{ page.props.errors.cancel_reason }}
            </p>
          </div>

          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="cancelForm.processing"
              class="flex-1 rounded-lg bg-red-600 px-3 py-2.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-700 disabled:opacity-60"
            >
              {{ cancelForm.processing ? $t('Cancelling...') : $t('Yes, cancel it') }}
            </button>
            <button
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-2.5 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:border-gray-600 dark:text-gray-200"
              @click="showCancel = false"
            >
              {{ $t('Back') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showNote" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
        <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
          <StickyNote class="h-5 w-5" /> {{ $t('Private note') }}
        </h3>

        <form class="mt-4 space-y-4" novalidate @submit.prevent="saveNote">
          <div>
            <label for="field-internal_notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('What should the office remember?') }}
            </label>
            <textarea
              id="field-internal_notes"
              v-model="noteForm.internal_notes"
              rows="4"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              :class="page.props.errors?.internal_notes ? 'border-red-500' : ''"
            />
            <p v-if="page.props.errors?.internal_notes" class="mt-1 text-sm text-red-600">
              {{ page.props.errors.internal_notes }}
            </p>
          </div>

          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="noteForm.processing"
              class="flex-1 rounded-lg bg-blue-600 px-3 py-2.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {{ noteForm.processing ? $t('Saving...') : $t('Save note') }}
            </button>
            <button
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-2.5 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:border-gray-600 dark:text-gray-200"
              @click="showNote = false"
            >
              {{ $t('Back') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
