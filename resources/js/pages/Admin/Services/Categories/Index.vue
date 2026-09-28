<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import ConfirmDeleteModal from '@/components/Admin/ConfirmDeleteModal.vue';
import { Plus, Search, Edit, Trash2, FolderTree } from 'lucide-vue-next';

interface Category {
  id: number;
  name: string;
  full_name: string;
  slug: string;
  description: string | null;
  status: string;
  show_in_menu: boolean;
  sort_order: number;
  services_count?: number;
  parent?: { id: number; name: string } | null;
}

const props = defineProps<{
  categories: {
    data: Category[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
  };
  filters: Record<string, string>;
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const showDelete = ref<Category | null>(null);

let debounce: ReturnType<typeof setTimeout> | undefined;

function apply() {
  router.get(
    '/admin/services/categories',
    { search: search.value || undefined, status: status.value || undefined },
    { preserveState: true, replace: true },
  );
}

watch(search, () => {
  clearTimeout(debounce);
  debounce = setTimeout(apply, 350);
});

watch(status, apply);

function destroy(category: Category) {
  router.delete(`/admin/services/categories/${category.slug}`, { preserveScroll: true });
  showDelete.value = null;
}
</script>

<template>
  <Head title="Service Categories" />

  <AdminLayout title="Service Categories">
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Service Categories</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Group your services the way your customers think about them.
          </p>
        </div>
        <Link
          href="/admin/services/categories/create"
          class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700"
        >
          <Plus class="mr-2 h-4 w-4" />
          Add Category
        </Link>
      </div>

      <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="relative">
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Search</label>
            <Search class="absolute left-3 top-[38px] h-4 w-4 text-gray-400" />
            <input
              v-model="search"
              type="text"
              placeholder="Category name..."
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Status</label>
            <select
              v-model="status"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            >
              <option value="">All</option>
              <option value="enabled">Visible</option>
              <option value="disabled">Hidden</option>
            </select>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">Category</th>
                <th class="px-4 py-3 text-left font-semibold">Under</th>
                <th class="px-4 py-3 text-left font-semibold">Services</th>
                <th class="px-4 py-3 text-left font-semibold">In menu</th>
                <th class="px-4 py-3 text-left font-semibold">Status</th>
                <th class="px-4 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="category in categories.data" :key="category.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded bg-gray-100 text-gray-500 dark:bg-gray-700">
                      <FolderTree class="h-4 w-4" />
                    </div>
                    <div>
                      <p class="font-medium text-gray-900 dark:text-gray-100">{{ category.name }}</p>
                      <p class="text-xs text-gray-500">/{{ category.slug }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ category.parent?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ category.services_count ?? 0 }}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ category.show_in_menu ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-3">
                  <span
                    class="rounded-full px-2 py-1 text-xs"
                    :class="category.status === 'enabled' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                  >
                    {{ category.status === 'enabled' ? 'Visible' : 'Hidden' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <Link
                      :href="`/admin/services/categories/${category.slug}/edit`"
                      class="inline-flex items-center rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    >
                      <Edit class="mr-1 h-3.5 w-3.5" /> Edit
                    </Link>
                    <button
                      type="button"
                      class="inline-flex items-center rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-900"
                      @click="showDelete = category"
                    >
                      <Trash2 class="mr-1 h-3.5 w-3.5" /> Delete
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="categories.data.length === 0">
                <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                  No categories yet. Services can be added without one, but categories make browsing easier.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="categories.last_page > 1" class="border-t border-gray-100 p-4 dark:border-gray-700">
          <Pagination :data="categories as any" resource-name="categories" />
        </div>
      </div>
    </div>

    <ConfirmDeleteModal
      v-if="showDelete"
      :show="true"
      :title="`Delete ${showDelete.name}?`"
      message="A category that still holds services will not be deleted, because that would take those services off the website."
      @confirm="destroy(showDelete)"
      @update:show="showDelete = null"
    />
  </AdminLayout>
</template>
