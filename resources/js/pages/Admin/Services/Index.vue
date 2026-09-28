<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import ConfirmDeleteModal from '@/components/Admin/ConfirmDeleteModal.vue';
import {
  Plus,
  Search,
  Wrench,
  CheckCircle,
  EyeOff,
  Star,
  Trash2,
  Edit,
  Clock,
} from 'lucide-vue-next';

interface Service {
  id: number;
  name: string;
  slug: string;
  price: string;
  price_unit: string;
  price_display: string;
  duration_display: string | null;
  image_url: string | null;
  status: string;
  featured: boolean;
  booking_enabled: boolean;
  sort_order: number;
  service_area: string | null;
  category?: { id: number; name: string } | null;
}

interface Props {
  services: {
    data: Service[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  categories: { id: number; name: string }[];
  filters: Record<string, string>;
  stats: { total: number; published: number; bookable: number; open_jobs: number };
}

const props = defineProps<Props>();

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.service_category_id ?? '');
const status = ref(props.filters.status ?? '');
const booking = ref(props.filters.booking_enabled ?? '');

const selected = ref<number[]>([]);
const showDelete = ref<Service | null>(null);

let debounce: ReturnType<typeof setTimeout> | undefined;

function apply() {
  router.get(
    '/admin/services',
    {
      search: search.value || undefined,
      service_category_id: category.value || undefined,
      status: status.value || undefined,
      booking_enabled: booking.value || undefined,
    },
    { preserveState: true, replace: true },
  );
}

watch(search, () => {
  clearTimeout(debounce);
  debounce = setTimeout(apply, 350);
});

watch([category, status, booking], apply);

function toggleAll(event: Event) {
  const checked = (event.target as HTMLInputElement).checked;
  selected.value = checked ? props.services.data.map((s) => s.id) : [];
}

const bulk = useForm({ ids: [] as number[], status: 'disabled' });

function bulkStatus() {
  if (bulk.ids.length === 0) {
    return;
  }

  bulk.ids = selected.value;
  bulk.post('/admin/services/bulk-status', {
    preserveScroll: true,
    onSuccess: () => {
      selected.value = [];
    },
  });
}

function destroy(service: Service) {
  router.delete(`/admin/services/${service.slug}`, { preserveScroll: true });
  showDelete.value = null;
}
</script>

<template>
  <Head title="Services" />

  <AdminLayout title="Services">
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Services</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            The work you offer, the price, and whether customers can book it.
          </p>
        </div>
        <Link
          href="/admin/services/create"
          class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700"
        >
          <Plus class="mr-2 h-4 w-4" />
          Add Service
        </Link>
      </div>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">All services</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.total }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">On the website</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.published }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Bookable</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.bookable }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Open jobs</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.open_jobs }}</p>
          <Link href="/admin/services/bookings" class="mt-1 inline-block text-xs text-blue-600 underline">See jobs</Link>
        </div>
      </div>

      <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div class="relative">
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Search</label>
            <Search class="absolute left-3 top-[38px] h-4 w-4 text-gray-400" />
            <input
              v-model="search"
              type="text"
              placeholder="Name or description..."
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Category</label>
            <select
              v-model="category"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 text-sm text-gray-900 focus:border-blue-500 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            >
              <option value="">All categories</option>
              <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </div>

          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Status</label>
            <select
              v-model="status"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 text-sm text-gray-900 focus:border-blue-500 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            >
              <option value="">All</option>
              <option value="enabled">On the website</option>
              <option value="disabled">Hidden</option>
            </select>
          </div>

          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Booking</label>
            <select
              v-model="booking"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 text-sm text-gray-900 focus:border-blue-500 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            >
              <option value="">All</option>
              <option value="1">Bookable</option>
              <option value="0">Not bookable</option>
            </select>
          </div>
        </div>
      </div>

      <div
        v-if="selected.length"
        class="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-4"
      >
        <p class="text-sm text-blue-900">{{ selected.length }} selected</p>
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold uppercase text-white hover:bg-blue-700"
            @click="bulkStatus"
          >
            Hide selected
          </button>
          <button type="button" class="text-sm text-blue-900 underline" @click="selected = []">Clear</button>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-700/50">
              <tr>
                <th class="w-10 px-4 py-3">
                  <input
                    type="checkbox"
                    :checked="selected.length === services.data.length && services.data.length > 0"
                    @change="toggleAll"
                  />
                </th>
                <th class="px-4 py-3 text-left font-semibold">Service</th>
                <th class="px-4 py-3 text-left font-semibold">Category</th>
                <th class="px-4 py-3 text-left font-semibold">Price</th>
                <th class="px-4 py-3 text-left font-semibold">Takes</th>
                <th class="px-4 py-3 text-left font-semibold">Booking</th>
                <th class="px-4 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="service in services.data" :key="service.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                <td class="px-4 py-3">
                  <input v-model="selected" type="checkbox" :value="service.id" />
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <img
                      v-if="service.image_url"
                      :src="service.image_url"
                      :alt="service.name"
                      class="h-10 w-10 rounded object-cover"
                    />
                    <div
                      v-else
                      class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 text-gray-400 dark:bg-gray-700"
                    >
                      <Wrench class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ service.name }}
                        <Star v-if="service.featured" class="ml-1 inline h-3.5 w-3.5 text-amber-500" />
                      </p>
                      <p class="text-xs text-gray-500">/{{ service.slug }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ service.category?.name ?? '—' }}</td>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ service.price_display }}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                  <span v-if="service.duration_display" class="inline-flex items-center gap-1">
                    <Clock class="h-3.5 w-3.5" />{{ service.duration_display }}
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="px-4 py-3">
                  <span
                    v-if="service.status !== 'enabled'"
                    class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                  >
                    <EyeOff class="h-3 w-3" /> Hidden
                  </span>
                  <span
                    v-else-if="service.booking_enabled"
                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs text-green-800"
                  >
                    <CheckCircle class="h-3 w-3" /> Bookable
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-800"
                  >
                    Display only
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <a
                      :href="`/services/${service.slug}`"
                      target="_blank"
                      class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    >
                      View
                    </a>
                    <Link
                      :href="`/admin/services/${service.slug}/edit`"
                      class="inline-flex items-center rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    >
                      <Edit class="mr-1 h-3.5 w-3.5" /> Edit
                    </Link>
                    <button
                      type="button"
                      class="inline-flex items-center rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-900"
                      @click="showDelete = service"
                    >
                      <Trash2 class="mr-1 h-3.5 w-3.5" /> Delete
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="services.data.length === 0">
                <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                  No services yet. Add your first one to get started.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="services.last_page > 1" class="border-t border-gray-100 p-4 dark:border-gray-700">
          <Pagination :data="services as any" resource-name="services" />
        </div>
      </div>
    </div>

    <ConfirmDeleteModal
      v-if="showDelete"
      :show="true"
      :title="`Delete ${showDelete.name}?`"
      message="A service that already has jobs is hidden from the website instead, so those jobs keep their record."
      @confirm="destroy(showDelete)"
      @update:show="showDelete = null"
    />
  </AdminLayout>
</template>
