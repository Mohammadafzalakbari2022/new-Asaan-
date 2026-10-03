<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ArrowLeft, Save } from 'lucide-vue-next';

interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  parent_id: number | null;
  status: string;
  show_in_menu: boolean;
  sort_order: number;
  image: string | null;
  image_url: string | null;
}

/*
 * One form for a new category and for changing one, exactly as the service
 * editor does, so the two never drift apart.
 */
const props = defineProps<{
  category?: Category;
  categories: { id: number; name: string }[];
}>();

const isEdit = !!props.category;

const form = useForm({
  name: props.category?.name ?? '',
  slug: props.category?.slug ?? '',
  description: props.category?.description ?? '',
  parent_id: props.category?.parent_id ?? null,
  status: props.category?.status ?? 'enabled',
  show_in_menu: props.category?.show_in_menu ?? true,
  sort_order: props.category?.sort_order ?? 0,
  image: null as File | null,
  remove_image: false,
});

const page = usePage();

const fieldOrder = ['name', 'parent_id', 'description', 'status', 'image'] as const;

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

function onFile(event: Event) {
  form.image = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
  const payload: Record<string, unknown> = { ...form.data };

  if (form.image) {
    payload.image = form.image;
  } else {
    delete payload.image;
  }

  if (isEdit) {
    form.put(`/admin/services/categories/${props.category!.slug}`, payload, { preserveScroll: true });
  } else {
    form.post('/admin/services/categories', payload, { preserveScroll: true });
  }
}
</script>

<template>
  <Head :title="isEdit ? $t('Edit Category') : $t('Add Category')" />

  <AdminLayout :title="isEdit ? $t('Edit Category') : $t('Add Category')">
    <div class="p-6 space-y-6">
      <div>
        <Link
          href="/admin/services/categories"
          class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400"
        >
          <ArrowLeft class="mr-1 h-4 w-4" /> {{ $t('Back to categories') }}
        </Link>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
          {{ isEdit ? $t('Edit {name}', { name: props.category!.name }) : $t('Add a category') }}
        </h1>
      </div>

      <form class="max-w-3xl space-y-6" novalidate @submit.prevent="submit">
        <section class="space-y-5 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
          <div>
            <label for="field-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Name') }} <span class="text-red-500">*</span>
            </label>
            <input
              id="field-name"
              v-model="form.name"
              type="text"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              :class="page.props.errors?.name ? 'border-red-500' : ''"
            />
            <p v-if="page.props.errors?.name" class="mt-1 text-sm text-red-600">{{ page.props.errors.name }}</p>
          </div>

          <div>
            <label for="field-parent_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Sits under') }}
            </label>
            <select
              id="field-parent_id"
              v-model="form.parent_id"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
              :class="page.props.errors?.parent_id ? 'border-red-500' : ''"
            >
              <option :value="null">{{ $t('Top level') }}</option>
              <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
            <p v-if="page.props.errors?.parent_id" class="mt-1 text-sm text-red-600">
              {{ page.props.errors.parent_id }}
            </p>
          </div>

          <div>
            <label for="field-description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Description') }}
            </label>
            <textarea
              id="field-description"
              v-model="form.description"
              rows="3"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            />
          </div>

          <div>
            <label for="field-image" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Picture') }}
            </label>
            <input id="field-image" type="file" accept="image/*" class="w-full text-sm text-gray-600 dark:text-gray-300" @change="onFile" />
            <img
              v-if="props.category?.image_url"
              :src="props.category.image_url"
              :alt="props.category.name"
              class="mt-3 h-24 w-24 rounded-lg object-cover"
            />
          </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
          <div>
            <label for="field-status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Visibility') }}
            </label>
            <select
              id="field-status"
              v-model="form.status"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            >
              <option value="enabled">{{ $t('Visible') }}</option>
              <option value="disabled">{{ $t('Hidden') }}</option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('A hidden category also takes its services off the website.') }}</p>
          </div>

          <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <input v-model="form.show_in_menu" type="checkbox" class="rounded" />
            {{ $t('Show in the storefront menu') }}
          </label>

          <div>
            <label for="field-sort_order" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ $t('Order') }}
            </label>
            <input
              id="field-sort_order"
              v-model.number="form.sort_order"
              type="number"
              class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
            />
          </div>
        </section>

        <div class="flex items-center gap-3">
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700 disabled:opacity-60"
          >
            <Save class="mr-2 h-4 w-4" />
            {{ form.processing ? $t('Saving...') : isEdit ? $t('Save changes') : $t('Create category') }}
          </button>
          <Link
            href="/admin/services/categories"
            class="rounded-lg border border-gray-300 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
          >
            {{ $t('Cancel') }}
          </Link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
