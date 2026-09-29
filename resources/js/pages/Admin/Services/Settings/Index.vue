<script setup lang="ts">
import { nextTick, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ArrowLeft, Save, Plus, Trash2, Clock, Info } from 'lucide-vue-next';

interface Slot {
  label: string;
  start: string;
  end: string;
}

/*
 * The settings a customer feels when they book, written as plain questions:
 * how much notice, how far ahead, and what times you actually work.
 */
const props = defineProps<{
  settings: {
    booking_enabled: boolean;
    lead_time_hours: number;
    booking_window_hours: number;
    same_day_allowed: boolean;
    time_slots: Slot[];
    capacity_per_slot: number;
    coverage_note: string;
    contact_phone: string;
    contact_whatsapp: string;
    require_login_to_book: boolean;
    auto_assign: boolean;
    reference_prefix: string;
  };
}>();

const page = usePage();

const form = useForm<{
  booking_enabled: boolean;
  lead_time_hours: number;
  booking_window_hours: number;
  same_day_allowed: boolean;
  time_slots: Slot[];
  capacity_per_slot: number;
  coverage_note: string;
  contact_phone: string;
  contact_whatsapp: string;
  require_login_to_book: boolean;
  auto_assign: boolean;
  reference_prefix: string;
}>({
  booking_enabled: true,
  lead_time_hours: 24,
  booking_window_hours: 168,
  same_day_allowed: false,
  time_slots: [],
  capacity_per_slot: 10,
  coverage_note: '',
  contact_phone: '',
  contact_whatsapp: '',
  require_login_to_book: false,
  auto_assign: false,
  reference_prefix: 'SRV-',
});

/**
 * The settings arrive from the server as the truth, so the form starts from
 * what is actually saved rather than from defaults that may disagree.
 */
watch(
  () => props.settings,
  (value) => {
    if (value) {
      form.booking_enabled = value.booking_enabled;
      form.lead_time_hours = value.lead_time_hours;
      form.booking_window_hours = value.booking_window_hours;
      form.same_day_allowed = value.same_day_allowed;
      form.time_slots = (value.time_slots ?? []).map((slot: Slot) => ({ ...slot }));
      form.capacity_per_slot = value.capacity_per_slot;
      form.coverage_note = value.coverage_note ?? '';
      form.contact_phone = value.contact_phone ?? '';
      form.contact_whatsapp = value.contact_whatsapp ?? '';
      form.require_login_to_book = value.require_login_to_book;
      form.auto_assign = value.auto_assign;
      form.reference_prefix = value.reference_prefix;
    }
  },
  { immediate: true },
);

function addSlot() {
  form.time_slots.push({ label: '', start: '09:00', end: '12:00' });
}

function removeSlot(index: number) {
  form.time_slots.splice(index, 1);
}

const fieldOrder = [
  'lead_time_hours',
  'booking_window_hours',
  'capacity_per_slot',
  'reference_prefix',
  'contact_phone',
  'contact_whatsapp',
  'coverage_note',
] as const;

watch(
  () => page.props.errors,
  async (errors) => {
    if (!errors || Object.keys(errors).length === 0) {
      return;
    }

    await nextTick();

    const first = fieldOrder.find((name) => errors[name]);

    if (first) {
      document.getElementById(`field-${first}`)?.focus();
    }
  },
);

function submit() {
  form.put('/admin/services/settings', { preserveScroll: true });
}
</script>

<template>
  <Head :title="$t('Booking Settings')" />

  <AdminLayout :title="$t('Booking Settings')">
    <div class="p-6 space-y-6">
      <div>
        <Link
          href="/admin/services"
          class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400"
        >
          <ArrowLeft class="mr-1 h-4 w-4" /> {{ $t('Back to services') }}
        </Link>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $t('Booking Settings') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('These are the rules a customer is held to when they book online.') }}
        </p>
      </div>

      <form class="grid grid-cols-1 gap-6 lg:grid-cols-3" novalidate @submit.prevent="submit">
        <div class="space-y-6 lg:col-span-2">
          <section class="space-y-5 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('When customers can book') }}</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <label for="field-lead_time_hours" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('Notice you need, in hours') }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="field-lead_time_hours"
                  v-model.number="form.lead_time_hours"
                  type="number"
                  min="0"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="page.props.errors?.lead_time_hours ? 'border-red-500' : ''"
                />
                <p class="mt-1 text-xs text-gray-500">{{ $t('24 means the earliest booking is tomorrow morning.') }}</p>
                <p v-if="page.props.errors?.lead_time_hours" class="mt-1 text-sm text-red-600">
                  {{ page.props.errors.lead_time_hours }}
                </p>
              </div>

              <div>
                <label for="field-booking_window_hours" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('How far ahead, in hours') }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="field-booking_window_hours"
                  v-model.number="form.booking_window_hours"
                  type="number"
                  min="1"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="page.props.errors?.booking_window_hours ? 'border-red-500' : ''"
                />
                <p class="mt-1 text-xs text-gray-500">{{ $t('168 is a week ahead.') }}</p>
                <p v-if="page.props.errors?.booking_window_hours" class="mt-1 text-sm text-red-600">
                  {{ page.props.errors.booking_window_hours }}
                </p>
              </div>
            </div>

            <div>
              <label for="field-capacity_per_slot" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $t('Jobs you can take in one time slot') }} <span class="text-red-500">*</span>
              </label>
              <input
                id="field-capacity_per_slot"
                v-model.number="form.capacity_per_slot"
                type="number"
                min="1"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100 sm:w-40"
                :class="page.props.errors?.capacity_per_slot ? 'border-red-500' : ''"
              />
              <p class="mt-1 text-xs text-gray-500">{{ $t('Once this many jobs are booked, that time disappears from the website.') }}</p>
              <p v-if="page.props.errors?.capacity_per_slot" class="mt-1 text-sm text-red-600">
                {{ page.props.errors.capacity_per_slot }}
              </p>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-600">
              <input v-model="form.same_day_allowed" type="checkbox" class="mt-0.5 rounded" />
              <span>
                {{ $t('Accept jobs for today') }}
                <span class="block text-xs text-gray-500">{{ $t('Only works if your notice period is zero.') }}</span>
              </span>
            </label>
          </section>

          <section class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
              <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                <Clock class="h-4 w-4" /> {{ $t('Time slots') }}
              </h2>
              <button
                type="button"
                class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                @click="addSlot"
              >
                <Plus class="mr-1 h-3.5 w-3.5" /> {{ $t('Add a slot') }}
              </button>
            </div>

            <p class="flex items-start gap-2 text-xs text-gray-500">
              <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
              {{ $t('These names are exactly what a customer picks. Keep them short.') }}
            </p>

            <div
              v-for="(slot, index) in form.time_slots"
              :key="index"
              class="grid grid-cols-1 items-end gap-3 rounded-lg border border-gray-100 p-3 sm:grid-cols-12 dark:border-gray-700"
            >
              <div class="sm:col-span-4">
                <label :for="`slot-label-${index}`" class="mb-1 block text-xs font-medium text-gray-600">{{ $t('Name') }}</label>
                <input
                  :id="`slot-label-${index}`"
                  v-model="slot.label"
                  type="text"
                  :placeholder="$t('Morning')"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                />
                <p v-if="page.props.errors?.[`time_slots.${index}.label`]" class="mt-1 text-xs text-red-600">
                  {{ page.props.errors[`time_slots.${index}.label`] }}
                </p>
              </div>
              <div class="sm:col-span-3">
                <label :for="`slot-start-${index}`" class="mb-1 block text-xs font-medium text-gray-600">{{ $t('From') }}</label>
                <input
                  :id="`slot-start-${index}`"
                  v-model="slot.start"
                  type="time"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="page.props.errors?.[`time_slots.${index}.start`] ? 'border-red-500' : ''"
                />
                <p v-if="page.props.errors?.[`time_slots.${index}.start`]" class="mt-1 text-xs text-red-600">
                  {{ page.props.errors[`time_slots.${index}.start`] }}
                </p>
              </div>
              <div class="sm:col-span-3">
                <label :for="`slot-end-${index}`" class="mb-1 block text-xs font-medium text-gray-600">{{ $t('Until') }}</label>
                <input
                  :id="`slot-end-${index}`"
                  v-model="slot.end"
                  type="time"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="page.props.errors?.[`time_slots.${index}.end`] ? 'border-red-500' : ''"
                />
                <p v-if="page.props.errors?.[`time_slots.${index}.end`]" class="mt-1 text-xs text-red-600">
                  {{ page.props.errors[`time_slots.${index}.end`] }}
                </p>
              </div>
              <div class="sm:col-span-2 sm:text-right">
                <button
                  type="button"
                  class="inline-flex items-center text-sm text-red-600 hover:underline"
                  @click="removeSlot(index)"
                >
                  <Trash2 class="mr-1 h-3.5 w-3.5" /> {{ $t('Remove') }}
                </button>
              </div>
            </div>

            <p v-if="form.time_slots.length === 0" class="text-sm text-gray-500">
              {{ $t('No slots yet. Without at least one, customers cannot pick a time.') }}
            </p>
          </section>

          <section class="space-y-5 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('Contact and coverage') }}</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <label for="field-contact_phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('Phone shown to customers') }}
                </label>
                <input
                  id="field-contact_phone"
                  v-model="form.contact_phone"
                  type="text"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                />
              </div>
              <div>
                <label for="field-contact_whatsapp" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ $t('WhatsApp number') }}
                </label>
                <input
                  id="field-contact_whatsapp"
                  v-model="form.contact_whatsapp"
                  type="text"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                />
              </div>
            </div>

            <div>
              <label for="field-coverage_note" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $t('Where you cover') }}
              </label>
              <textarea
                id="field-coverage_note"
                v-model="form.coverage_note"
                rows="3"
                :placeholder="$t('We cover the whole city. Outside that, ask us first.')"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
              <p class="mt-1 text-xs text-gray-500">{{ $t('Shown on the booking page so customers know before they start.') }}</p>
            </div>
          </section>
        </div>

        <div class="space-y-6">
          <section class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('Bookings') }}</h2>

            <label class="flex items-start gap-2 text-sm text-gray-600">
              <input v-model="form.booking_enabled" type="checkbox" class="mt-0.5 rounded" />
              <span>
                {{ $t('Take bookings on the website') }}
                <span class="block text-xs text-gray-500">{{ $t('Switch off to pause bookings without hiding services.') }}</span>
              </span>
            </label>

            <label class="flex items-start gap-2 text-sm text-gray-600">
              <input v-model="form.require_login_to_book" type="checkbox" class="mt-0.5 rounded" />
              <span>
                {{ $t('Customers must be signed in to book') }}
                <span class="block text-xs text-gray-500">{{ $t('Turn off to let guests book with just a phone number.') }}</span>
              </span>
            </label>

            <label class="flex items-start gap-2 text-sm text-gray-600">
              <input v-model="form.auto_assign" type="checkbox" class="mt-0.5 rounded" />
              <span>
                {{ $t('Give every new job to a worker automatically') }}
                <span class="block text-xs text-gray-500">{{ $t('Only when there is one active delivery worker.') }}</span>
              </span>
            </label>
          </section>

          <section class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $t('Booking references') }}</h2>
            <div>
              <label for="field-reference_prefix" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $t('Prefix') }} <span class="text-red-500">*</span>
              </label>
              <input
                id="field-reference_prefix"
                v-model="form.reference_prefix"
                type="text"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                :class="page.props.errors?.reference_prefix ? 'border-red-500' : ''"
              />
              <p class="mt-1 text-xs text-gray-500">{{ $t('Customers quote this, so keep it short.') }}</p>
              <p v-if="page.props.errors?.reference_prefix" class="mt-1 text-sm text-red-600">
                {{ page.props.errors.reference_prefix }}
              </p>
            </div>
          </section>

          <div class="flex items-center gap-3">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700 disabled:opacity-60"
            >
              <Save class="mr-2 h-4 w-4" />
              {{ form.processing ? $t('Saving...') : $t('Save settings') }}
            </button>
            <Link
              href="/admin/services"
              class="rounded-lg border border-gray-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
            >
              {{ $t('Cancel') }}
            </Link>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
