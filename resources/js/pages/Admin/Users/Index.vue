<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import DateDisplay from '@/components/Calendar/DateDisplay.vue'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Search, Edit, Trash2, Users } from 'lucide-vue-next'
import * as userRoutes from '@/routes/admin/users'

interface User {
  id: number
  name: string
  email: string
  role: string
  is_active: boolean
  email_verified_at: string | null
  created_at: string
}

interface Props {
  users: {
    data: User[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters: {
    search?: string
  }
}

const props = defineProps<Props>()

const search = ref(props.filters.search || '')

const searchUsers = () => {
  router.get(userRoutes.index.url(), { search: search.value }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const deleteUser = (user: User) => {
  if (confirm(`Are you sure you want to delete ${user.name}?`)) {
    router.delete(userRoutes.destroy.url({ user: user.id }), {
      preserveScroll: true,
    })
  }
}

const getRoleBadgeClass = (role: string) => {
  return role === 'admin'
    ? 'bg-purple-100 text-purple-800'
    : 'bg-blue-100 text-blue-800'
}

const getStatusBadgeClass = (isActive: boolean) => {
  return isActive
    ? 'bg-green-100 text-green-800'
    : 'bg-red-100 text-red-800'
}
</script>

<template>
  <AdminLayout>
    <Head :title="$t('User Management')" />

    <div class="py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <div class="flex items-center gap-3 mb-2">
            <Users class="w-8 h-8 text-primary" />
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $t('User Management') }}</h1>
          </div>
          <p class="text-gray-600 dark:text-gray-400">{{ $t('Manage user accounts and permissions') }}</p>
        </div>

        <!-- Search Bar -->
        <div class="mb-6">
          <div class="flex gap-4">
            <div class="relative flex-1">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <Input
                v-model="search"
                type="text"
                :placeholder="$t('Search by name or email...')"
                class="pl-10 dark:bg-gray-700/50 dark:text-gray-100"
                @keyup.enter="searchUsers"
              />
            </div>
            <Button @click="searchUsers">
              {{ $t('Search') }}
            </Button>
          </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('User') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('Role') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('Status') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('Email Verified') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('Joined') }}
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('Actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="user in props.users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                        <span class="text-primary font-semibold text-sm">
                          {{ user.name.charAt(0).toUpperCase() }}
                        </span>
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ user.name }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ user.email }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                    :class="getRoleBadgeClass(user.role)"
                  >
                    {{ user.role }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                    :class="getStatusBadgeClass(user.is_active)"
                  >
                    {{ user.is_active ? $t('Active') : $t('Inactive') }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ user.email_verified_at || $t('Not verified') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  <DateDisplay :value="user.created_at" time />
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <Link
                    :href="userRoutes.edit.url({ user: user.id })"
                    class="text-primary hover:text-primary/80 mr-3 inline-flex items-center"
                  >
                    <Edit class="w-4 h-4 mr-1" />
                    {{ $t('Edit') }}
                  </Link>
                  <button
                    @click="deleteUser(user)"
                    class="text-red-600 hover:text-red-900 inline-flex items-center"
                  >
                    <Trash2 class="w-4 h-4 mr-1" />
                    {{ $t('Delete') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="props.users.last_page > 1" class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
              <Link
                v-if="props.users.current_page > 1"
                :href="userRoutes.index.url({ query: { page: props.users.current_page - 1 } })"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                {{ $t('Previous') }}
              </Link>
              <Link
                v-if="props.users.current_page < props.users.last_page"
                :href="userRoutes.index.url({ query: { page: props.users.current_page + 1 } })"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                {{ $t('Next') }}
              </Link>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                  {{ $t('Showing') }}
                  <span class="font-medium">{{ (props.users.current_page - 1) * props.users.per_page + 1 }}</span>
                  {{ $t('to') }}
                  <span class="font-medium">{{ Math.min(props.users.current_page * props.users.per_page, props.users.total) }}</span>
                  {{ $t('of') }}
                  <span class="font-medium">{{ props.users.total }}</span>
                  {{ $t('results') }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
