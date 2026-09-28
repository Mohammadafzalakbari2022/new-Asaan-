<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ArrowLeft, Save } from 'lucide-vue-next';

interface Category {
  id: number;
  name: string;
}

interface Service {
  id: number;
  name: string;
  slug: string;
  short_description: string | null;
  description: string | null;
  service_category_id: number | null;
  price: string;
  price_unit: string;
  price_note: string | null;
  duration_minutes: number | null;
  duration_label: string | null;
  service_area: string | null;
  includes: string[] | null;
  excludes: string[] | null;
  icon: string | null;
  image: string | null;
  image_url: string | null;
  icon_only: boolean;
  status: string;
  featured: boolean;
  booking_enabled: boolean;
  sort_order: number;
  meta_title: string | null;
  meta_description: string | null;
}

const props = defineProps<{
  service?: Service;
  categories: Category[];
  units: Record<string, string>;
}>();

const isEdit = !!props.service;

const form = useForm({
  name: props.service?.name ?? '',
  slug: props.service?.slug ?? '',
  short_description: props.service?.short_description ?? '',
  description: props.service?.description ?? '',
  service_category_id: props.service?.service_category_id ?? null,
  price: props.service?.price ?? '',
  price_unit: props.service?.price_unit ?? 'per_job',
  price_note: props.service?.price_note ?? '',
  duration_minutes: props.service?.duration_minutes ?? null,
  duration_label: props.service?.duration_label ?? '',
  service_area: props.service?.service_area ?? '',
  includes: (props.service?.includes ?? []).join('\n'),
  excludes: (props.service?.excludes ?? []).join('\n'),
  icon: props.service?.icon ?? '',
  icon_only: props.service?.icon_only ?? false,
  remove_image: false,
  status: props.service?.status ?? 'enabled',
  featured: props.service?.featured ?? false,
  booking_enabled: props.service?.booking_enabled ?? true,
  sort_order: props.service?.sort_order ?? 0,
  meta_title: props.service?.meta_title ?? '',
  meta_description: props.service?.meta_description ?? '',
  image: null as File | null,
});

const page = usePage();

/**
 * A list written one item per line in a plain box, because owners know their own
 * inclusions far better than they know a repeating field.
 */
function toList(value: string): string[] {
  return value
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.length > 0);
}

const fieldOrder = [
  'name',
  'service_category_id',
  'price',
  'price_unit',
  'short_description',
  'description',
  'duration_minutes',
  'service_area',
  'includes',
  'excludes',
  'image',
  'status',
] as const;

/**
 * Everything wrong is reported at once and the first thing to fix is focused,
 * so nobody is left hunting down one problem per attempt.
 */
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
  const payload: Record<string, unknown> = {
    ...form.data,
    includes: toList(form.includes),
    excludes: toList(form.excludes),
  };

  if (form.image) {
    payload.image = form.image;
  } else {
    delete payload.image;
  }

  if (isEdit) {
    form.put(`/admin/services/${props.service!.slug}`, payload, { preserveScroll: true });
  } else {
    form.post('/admin/services', payload, { preserveScroll: true });
  }
}

function onFile(event: Event) {
  const input = event.target as HTMLInputElement;
  form.image = input.files?.[0] ?? null;
}
</script>

<template>
  <Head :title="isEdit ? 'Edit Service' : 'Add Service'" />

  <AdminLayout :title="isEdit ? 'Edit Service' : 'Add Service'">
    <div class="p-6 space-y-6">
      <div>
        <Link
          href="/admin/services"
          class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400"
        >
          <ArrowLeft class="mr-1 h-4 w-4" /> Back to services
        </Link>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
          {{ isEdit ? `Edit ${props.service!.name}` : 'Add a service' }}
        </h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          The price here is what the customer sees, and what the job is worth.
        </p>
      </div>

      <form class="grid grid-cols-1 gap-6 lg:grid-cols-3" novalidate @submit.prevent="submit">
        <div class="space-y-6 lg:col-span-2">
          <section class="space-y-5 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">What it is</h2>

            <div>
              <label for="field-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Name <span class="text-red-500">*</span>
              </label>
              <input
                id="field-name"
                v-model="form.name"
                type="text"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                :class="page.props.errors?.name ? 'border-red-500' : ''"
              />
              <p v-if="page.props.errors?.name" class="mt-1 text-sm text-red-600">
                {{ page.props.errors.name }}
              </p>
            </div>

            <div>
              <label for="field-service_category_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Category
              </label>
              <select
                id="field-service_category_id"
                v-model="form.service_category_id"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              >
                <option :value="null">No category</option>
                <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
              <p v-if="page.props.errors?.service_category_id" class="mt-1 text-sm text-red-600">
                {{ page.props.errors.service_category_id }}
              </p>
            </div>

            <div>
              <label for="field-short_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Short description
              </label>
              <input
                id="field-short_description"
                v-model="form.short_description"
                type="text"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
              <p class="mt-1 text-xs text-gray-500">One line, shown on the service card.</p>
            </div>

            <div>
              <label for="field-description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Full description
              </label>
              <textarea
                id="field-description"
                v-model="form.description"
                rows="5"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
            </div>
          </section>

          <section class="space-y-5 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Price and time</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <label for="field-price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Price <span class="text-red-500">*</span>
                </label>
                <input
                  id="field-price"
                  v-model="form.price"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="page.props.errors?.price ? 'border-red-500' : ''"
                />
                <p v-if="page.props.errors?.price" class="mt-1 text-sm text-red-600">
                  {{ page.props.errors.price }}
                </p>
              </div>

              <div>
                <label for="field-price_unit" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Price is per
                </label>
                <select
                  id="field-price_unit"
                  v-model="form.price_unit"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                  :class="page.props.errors?.price_unit ? 'border-red-500' : ''"
                >
                  <option v-for="(label, value) in units" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="page.props.errors?.price_unit" class="mt-1 text-sm text-red-600">
                  {{ page.props.errors.price_unit }}
                </p>
              </div>
            </div>

            <div>
              <label for="field-price_note" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Price note
              </label>
              <input
                id="field-price_note"
                v-model="form.price_note"
                type="text"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
              <p class="mt-1 text-xs text-gray-500">Anything the customer should know about the price.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <label for="field-duration_minutes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Takes (minutes)
                </label>
                <input
                  id="field-duration_minutes"
                  v-model.number="form.duration_minutes"
                  type="number"
                  min="0"
                  step="15"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                />
                <p class="mt-1 text-xs text-gray-500">The website turns this into "2 hours" and so on.</p>
              </div>

              <div>
                <label for="field-duration_label" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Or your own wording
                </label>
                <input
                  id="field-duration_label"
                  v-model="form.duration_label"
                  type="text"
                  placeholder="half a day"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                />
                <p class="mt-1 text-xs text-gray-500">Used instead of the minutes when filled in.</p>
              </div>
            </div>
          </section>

          <section class="space-y-5 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">
              What the customer gets
            </h2>

            <div>
              <label for="field-includes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Included
              </label>
              <textarea
                id="field-includes"
                v-model="form.includes"
                rows="4"
                placeholder="One item per line"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
            </div>

            <div>
              <label for="field-excludes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Not included
              </label>
              <textarea
                id="field-excludes"
                v-model="form.excludes"
                rows="3"
                placeholder="One item per line"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
              <p class="mt-1 text-xs text-gray-500">Customers ask about this, so answering it up front saves arguments.</p>
            </div>

            <div>
              <label for="field-service_area" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Where you cover it
              </label>
              <input
                id="field-service_area"
                v-model="form.service_area"
                type="text"
                placeholder="Kabul, Herat, Jalalabad"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
            </div>
          </section>
        </div>

        <div class="space-y-6">
          <section class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Picture</h2>

            <img
              v-if="props.service?.image_url"
              :src="props.service.image_url"
              :alt="props.service.name"
              class="h-32 w-full rounded-lg object-cover"
            />

            <div>
              <label for="field-image" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Photo
              </label>
              <input
                id="field-image"
                type="file"
                accept="image/*"
                class="w-full text-sm text-gray-600"
                @change="onFile"
              />
              <p v-if="page.props.errors?.image" class="mt-1 text-sm text-red-600">
                {{ page.props.errors.image }}
              </p>
            </div>

            <label v-if="props.service?.image_url" class="flex items-center gap-2 text-sm text-gray-600">
              <input id="field-remove_image" v-model="form.remove_image" type="checkbox" class="rounded" />
              Remove the current photo when saving
            </label>

            <div>
              <label for="field-icon" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Icon name
              </label>
              <input
                id="field-icon"
                v-model="form.icon"
                type="text"
                placeholder="wrench"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
              <input id="field-icon_only" v-model="form.icon_only" type="checkbox" class="rounded" />
              Show the icon only, with no photo
            </label>
          </section>

          <section class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">On the website</h2>

            <div>
              <label for="field-status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Visibility
              </label>
              <select
                id="field-status"
                v-model="form.status"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              >
                <option value="enabled">On the website</option>
                <option value="disabled">Hidden</option>
              </select>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-600">
              <input id="field-booking_enabled" v-model="form.booking_enabled" type="checkbox" class="mt-0.5 rounded" />
              <span>
                Customers can book this online
                <span class="block text-xs text-gray-500">Switch off to show it without taking bookings.</span>
              </span>
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-600">
              <input id="field-featured" v-model="form.featured" type="checkbox" class="rounded" />
              Show as popular on the services page
            </label>

            <div>
              <label for="field-sort_order" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Order
              </label>
              <input
                id="field-sort_order"
                v-model.number="form.sort_order"
                type="number"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              />
              <p class="mt-1 text-xs text-gray-500">Lower numbers come first.</p>
            </div>
          </section>

          <div class="flex items-center gap-3">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700 disabled:opacity-60"
            >
              <Save class="mr-2 h-4 w-4" />
              {{ form.processing ? 'Saving...' : isEdit ? 'Save changes' : 'Create service' }}
            </button>
            <Link
              href="/admin/services"
              class="rounded-lg border border-gray-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
            >
              Cancel
            </Link>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
