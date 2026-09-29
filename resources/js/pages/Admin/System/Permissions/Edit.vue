<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Shield, ArrowLeft, PenLine, List } from 'lucide-vue-next'
import { useI18nStore } from '@/Stores/i18n'


interface Permission {
  id: number
  name: string
  display_name: string
  group: string
  description: string
}

interface Props {
  permission: Permission
  existingGroups: string[]
}

const props = defineProps<Props>()

const { t } = useI18nStore()

const form = useForm({
  name: props.permission.name,
  display_name: props.permission.display_name,
  group: props.permission.group,
  description: props.permission.description || '',
})

const customGroup = ref('')
const showCustomGroup = ref(false)

const toggleCustomGroup = () => {
  showCustomGroup.value = !showCustomGroup.value
  if (showCustomGroup.value) {
    customGroup.value = form.group
  } else {
    form.group = customGroup.value || props.permission.group
  }
}

const updateGroup = (value: string) => {
  if (showCustomGroup.value) {
    customGroup.value = value
    form.group = value
  } else {
    form.group = value
  }
}

const submit = () => {
  form.put(`/admin/system/permissions/${props.permission.id}`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head :title="$t('Edit Permission')" />
  
  <AdminLayout :title="$t('Edit Permission')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">{{ $t('Edit Permission') }}</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $t('Update permission details') }}</p>
          </div>
          <Link
            href="/admin/system/permissions"
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <ArrowLeft class="w-4 h-4 mr-2" />
            {{ $t('Back to Permissions') }}
          </Link>
        </div>
      </div>
      <!-- Form -->
      <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 p-6">
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Name -->
            <div class="space-y-2">
              <Label for="name" class="required">{{ $t('Permission Name') }}</Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="e.g., catalog.products.view"
                class="font-mono"
                required
              />
              <p class="text-sm text-gray-500 dark:text-gray-300">
                {{ $t('Use dot notation for permission keys (e.g., module.resource.action)') }}
              </p>
              <p v-if="form.errors.name" class="text-sm text-red-600">
                {{ form.errors.name }}
              </p>
            </div>

            <!-- Display Name -->
            <div class="space-y-2">
              <Label for="display_name" class="required">{{ $t('Display Name') }}</Label>
              <Input
                id="display_name"
                v-model="form.display_name"
                type="text"
                placeholder="e.g., View Products"
                required
              />
              <p class="text-sm text-gray-500 dark:text-gray-300">
                {{ $t('A human-readable name for this permission') }}
              </p>
              <p v-if="form.errors.display_name" class="text-sm text-red-600">
                {{ form.errors.display_name }}
              </p>
            </div>

            <!-- Group Selection -->
            <div class="space-y-2">
              <Label for="group" class="required">{{ $t('Permission Group') }}</Label>
              <select
                v-if="!showCustomGroup"
                id="group"
                v-model="form.group"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                required
              >
                <option value="" disabled>{{ $t('Select a group') }}</option>
                <option value="catalog">{{ $t('Catalog') }}</option>
                <option value="sales">{{ $t('Sales') }}</option>
                <option value="customer">{{ $t('Customer') }}</option>
                <option value="marketing">{{ $t('Marketing') }}</option>
                <option value="content">{{ $t('Content') }}</option>
                <option value="settings">{{ $t('Settings') }}</option>
                <option value="system">{{ $t('System') }}</option>
                <option value="reports">{{ $t('Reports') }}</option>
                <option
                  v-for="group in props.existingGroups"
                  :key="group"
                  :value="group"
                >
                  {{ group }}
                </option>
              </select>
              <Input
                v-else
                id="custom_group"
                v-model="customGroup"
                type="text"
                :placeholder="$t('Enter custom group name')"
                @input="updateGroup(customGroup)"
                required
              />
              <button
                type="button"
                @click="toggleCustomGroup"
                class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline-offset-2 hover:underline transition-colors"
              >
                <List v-if="showCustomGroup" class="h-3.5 w-3.5" />
                <PenLine v-else class="h-3.5 w-3.5" />
                {{ showCustomGroup ? $t('Choose from existing groups') : $t('Use a custom group name') }}
              </button>
              <p class="text-sm text-gray-500 dark:text-gray-300">
                {{ $t('Group related permissions together') }}
              </p>
              <p v-if="form.errors.group" class="text-sm text-red-600">
                {{ form.errors.group }}
              </p>
            </div>

            <!-- Description -->
            <div class="space-y-2">
              <Label for="description">{{ $t('Description') }}</Label>
              <Textarea
                id="description"
                v-model="form.description"
                :placeholder="$t('Describe what this permission allows...')"
                rows="3"
              />
              <p class="text-sm text-gray-500 dark:text-gray-300">
                {{ $t('Optional description to explain what this permission controls') }}
              </p>
              <p v-if="form.errors.description" class="text-sm text-red-600">
                {{ form.errors.description }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t dark:border-gray-800">
              <Link
                href="/admin/system/permissions"
                class="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                {{ $t('Cancel') }}
              </Link>
              <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ form.processing ? $t('Updating...') : $t('Update Permission') }}
              </button>
            </div>
          </form>
        </div>
      </div>
  </AdminLayout>
</template>

<style scoped>
.required::after {
  content: ' *';
  color: #ef4444;
}
</style>
