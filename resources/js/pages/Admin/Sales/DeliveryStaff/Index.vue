<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import { debounce } from 'lodash';
import { Search, Plus, Users, X, Trash2, KeyRound, Edit2 } from 'lucide-vue-next';

interface StaffRow {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  created_at: string;
}

interface Props {
  staff: {
    data: StaffRow[];
    current_page: number;
    last_page: number;
    total: number;
  };
  filters: { search?: string };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');

const applyFilters = () => {
  router.get('/admin/sales/delivery-staff', { search: search.value }, { preserveState: true, preserveScroll: true });
};

const performSearch = debounce(applyFilters, 300);

const showCreate = ref(false);
const createForm = useForm({
  name: '',
  email: '',
  phone: '',
  password: '',
  is_active: true,
});

const submitCreate = () => {
  createForm.post('/admin/sales/delivery-staff', {
    onSuccess: () => {
      showCreate.value = false;
      createForm.reset();
    },
  });
};

const editTarget = ref<StaffRow | null>(null);
const editForm = useForm({ name: '', email: '', phone: '', is_active: true });

const openEdit = (row: StaffRow) => {
  editTarget.value = row;
  editForm.reset();
  editForm.clearErrors();
  editForm.name = row.name;
  editForm.email = row.email;
  editForm.phone = row.phone || '';
  editForm.is_active = row.is_active;
};

const submitEdit = () => {
  if (!editTarget.value) return;
  editForm.put(`/admin/sales/delivery-staff/${editTarget.value.id}`, {
    onSuccess: () => {
      editTarget.value = null;
    },
  });
};

const pwTarget = ref<StaffRow | null>(null);
const pwForm = useForm({ password: '' });

const openPw = (row: StaffRow) => {
  pwTarget.value = row;
  pwForm.reset();
  pwForm.clearErrors();
};

const submitPw = () => {
  if (!pwTarget.value) return;
  pwForm.post(`/admin/sales/delivery-staff/${pwTarget.value.id}/reset-password`, {
    onSuccess: () => {
      pwTarget.value = null;
    },
  });
};

const deleteUser = (row: StaffRow) => {
  if (!window.confirm(`Remove ${row.name} from delivery staff?`)) return;
  router.delete(`/admin/sales/delivery-staff/${row.id}`);
};
</script>

<template>
  <Head :title="$t('Delivery Staff')" />

  <AdminLayout :title="$t('Delivery Staff')">
    <div class="space-y-6">
      <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              v-model="search"
              type="text"
              :placeholder="$t('Search delivery people...')"
              class="h-9 w-64 rounded-lg border border-gray-300 dark:border-gray-600 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
              @input="performSearch"
            />
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            @click="showCreate = true"
          >
            <Plus class="h-4 w-4" /> {{ $t('Add Delivery Person') }}
          </button>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Name') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Email') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Phone') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Status') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Joined') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $t('Actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template v-if="props.staff.data.length">
                <tr v-for="s in props.staff.data" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5">
                      <div class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                        <Users class="h-4 w-4" />
                      </div>
                      <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ s.name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ s.email }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ s.phone || '—' }}</td>
                  <td class="px-4 py-3">
                    <span
                      :class="s.is_active ? 'bg-green-100 text-green-800 border-green-200' : 'bg-gray-100 dark:bg-gray-800/60 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700'"
                      class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium"
                    >
                      {{ $t(s.is_active ? 'Active' : 'Inactive') }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400"><DateDisplay :value="s.created_at" /></td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <button type="button" class="rounded-lg p-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-blue-600" :title="$t('Edit')" @click="openEdit(s)">
                        <Edit2 class="h-4 w-4" />
                      </button>
                      <button type="button" class="rounded-lg p-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-blue-600" :title="$t('Reset password')" @click="openPw(s)">
                        <KeyRound class="h-4 w-4" />
                      </button>
                      <button type="button" class="rounded-lg p-1.5 text-gray-500 dark:text-gray-400 hover:bg-red-50 hover:text-red-600" :title="$t('Remove')" @click="deleteUser(s)">
                        <Trash2 class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-else>
                <td colspan="6" class="px-4 py-16 text-center">
                  <Users class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                  <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('No delivery staff yet. Add your first delivery person.') }}</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="props.staff.last_page > 1" class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 px-4 py-3">
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('Page {page} of {last}', { page: props.staff.current_page, last: props.staff.last_page }) }}</p>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="props.staff.current_page <= 1"
              class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm disabled:opacity-40"
              @click="router.get('/admin/sales/delivery-staff', { page: props.staff.current_page - 1, ...props.filters })"
            >
              {{ $t('Previous') }}
            </button>
            <button
              type="button"
              :disabled="props.staff.current_page >= props.staff.last_page"
              class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm disabled:opacity-40"
              @click="router.get('/admin/sales/delivery-staff', { page: props.staff.current_page + 1, ...props.filters })"
            >
              {{ $t('Next') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showCreate = false">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $t('Add Delivery Person') }}</h3>
          <button type="button" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-700" @click="showCreate = false">
            <X class="h-5 w-5 text-gray-500 dark:text-gray-400" />
          </button>
        </div>
        <form class="space-y-4" @submit.prevent="submitCreate">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Name') }}</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
            />
            <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Email') }}</label>
            <input
              v-model="createForm.email"
              type="email"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
            />
            <p v-if="createForm.errors.email" class="mt-1 text-xs text-red-600">{{ createForm.errors.email }}</p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Phone') }}</label>
            <input
              v-model="createForm.phone"
              type="text"
              placeholder="07xx xxx xxx"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Password') }}</label>
            <input
              v-model="createForm.password"
              type="password"
              minlength="8"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
            />
            <p v-if="createForm.errors.password" class="mt-1 text-xs text-red-600">{{ createForm.errors.password }}</p>
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
            {{ $t('Active (can log in)') }}
          </label>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm" @click="showCreate = false">{{ $t('Cancel') }}</button>
            <button
              type="submit"
              :disabled="createForm.processing"
              class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {{ $t(createForm.processing ? 'Saving...' : 'Add Delivery Person') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit -->
    <div v-if="editTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editTarget = null">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $t('Edit Delivery Person') }}</h3>
          <button type="button" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-700" @click="editTarget = null">
            <X class="h-5 w-5 text-gray-500 dark:text-gray-400" />
          </button>
        </div>
        <form class="space-y-4" @submit.prevent="submitEdit">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Name') }}</label>
            <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100" />
            <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Email') }}</label>
            <input v-model="editForm.email" type="email" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100" />
            <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Phone') }}</label>
            <input v-model="editForm.phone" type="text" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100" />
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
            {{ $t('Active (can log in)') }}
          </label>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm" @click="editTarget = null">{{ $t('Cancel') }}</button>
            <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
              {{ $t('Save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reset password -->
    <div v-if="pwTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="pwTarget = null">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $t('Reset password — {name}', { name: pwTarget.name }) }}</h3>
          <button type="button" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-700" @click="pwTarget = null">
            <X class="h-5 w-5 text-gray-500 dark:text-gray-400" />
          </button>
        </div>
        <form class="space-y-4" @submit.prevent="submitPw">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('New Password') }}</label>
            <input v-model="pwForm.password" type="password" minlength="8" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100" />
            <p v-if="pwForm.errors.password" class="mt-1 text-xs text-red-600">{{ pwForm.errors.password }}</p>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm" @click="pwTarget = null">{{ $t('Cancel') }}</button>
            <button type="submit" :disabled="pwForm.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
              {{ $t('Reset Password') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>