<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import DateDisplay from '@/components/Calendar/DateDisplay.vue'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { ArrowLeft, Save, Key, User } from 'lucide-vue-next'
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
  user: User
}

const props = defineProps<Props>()

// User profile form
const profileForm = useForm({
  name: props.user.name,
  email: props.user.email,
  role: props.user.role,
  is_active: props.user.is_active,
})

const updateProfile = () => {
  profileForm.put(userRoutes.update.url({ user: props.user.id }), {
    preserveScroll: true,
  })
}

// Password change form
const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const changePassword = () => {
  passwordForm.put(userRoutes.changePassword.url({ user: props.user.id }), {
    preserveScroll: true,
    onSuccess: () => {
      passwordForm.reset()
    },
  })
}
</script>

<template>
  <AdminLayout>
    <Head :title="$t('Edit User: {name}', { name: props.user.name })" />

    <div class="py-8">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <Link
            :href="userRoutes.index.url()"
            class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 mb-4"
          >
            <ArrowLeft class="w-4 h-4 mr-2" />
            {{ $t('Back to Users') }}
          </Link>
          <div class="flex items-center gap-3 mb-2">
            <User class="w-8 h-8 text-primary" />
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $t('Edit User') }}</h1>
          </div>
          <p class="text-gray-600 dark:text-gray-400">{{ $t('Update user information and manage permissions') }}</p>
        </div>

        <!-- User Profile Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">{{ $t('Profile Information') }}</h2>
          
          <form @submit.prevent="updateProfile" class="space-y-6">
            <!-- Name -->
            <div>
              <Label for="name">{{ $t('Name') }}</Label>
              <Input
                id="name"
                v-model="profileForm.name"
                type="text"
                class="mt-1 bg-white dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': profileForm.errors.name }"
                required
              />
              <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">
                {{ profileForm.errors.name }}
              </p>
            </div>

            <!-- Email -->
            <div>
              <Label for="email">{{ $t('Email') }}</Label>
              <Input
                id="email"
                v-model="profileForm.email"
                type="email"
                class="mt-1 bg-white dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': profileForm.errors.email }"
                required
              />
              <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">
                {{ profileForm.errors.email }}
              </p>
            </div>

            <!-- Role -->
            <div>
              <Label for="role">{{ $t('Role') }}</Label>
              <select
                id="role"
                v-model="profileForm.role"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': profileForm.errors.role }"
              >
                <option value="customer">{{ $t('Customer') }}</option>
                <option value="admin">{{ $t('Admin') }}</option>
                <option value="delivery">{{ $t('Delivery Person') }}</option>
              </select>
              <p v-if="profileForm.errors.role" class="mt-1 text-sm text-red-600">
                {{ profileForm.errors.role }}
              </p>
            </div>

            <!-- Active Status -->
            <div class="flex items-center">
              <input
                id="is_active"
                v-model="profileForm.is_active"
                type="checkbox"
                class="rounded border-gray-300 dark:border-gray-600 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700"
              />
              <Label for="is_active" class="ml-2 mb-0">{{ $t('Active Account') }}</Label>
            </div>

            <!-- Additional Info -->
            <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-md space-y-2">
              <div class="text-sm text-gray-600 dark:text-gray-400">
                <strong>{{ $t('Email Verified:') }}</strong> {{ props.user.email_verified_at || $t('Not verified') }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                <strong>{{ $t('Account Created:') }}</strong> <DateDisplay :value="props.user.created_at" time />
              </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
              <Button type="submit" :disabled="profileForm.processing">
                <Save class="w-4 h-4 mr-2" />
                {{ profileForm.processing ? $t('Saving...') : $t('Save Changes') }}
              </Button>
            </div>
          </form>
        </div>

        <!-- Password Change Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="flex items-center gap-3 mb-6">
            <Key class="w-6 h-6 text-primary" />
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $t('Change Password') }}</h2>
          </div>
          
          <form @submit.prevent="changePassword" class="space-y-6">
            <!-- Current Password -->
            <div>
              <Label for="current_password">{{ $t('Current Password') }}</Label>
              <Input
                id="current_password"
                v-model="passwordForm.current_password"
                type="password"
                class="mt-1 bg-white dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': passwordForm.errors.current_password }"
                required
              />
              <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">
                {{ passwordForm.errors.current_password }}
              </p>
            </div>

            <!-- New Password -->
            <div>
              <Label for="password">{{ $t('New Password') }}</Label>
              <Input
                id="password"
                v-model="passwordForm.password"
                type="password"
                class="mt-1 bg-white dark:bg-gray-700/50 dark:text-gray-100"
                :class="{ 'border-red-500': passwordForm.errors.password }"
                required
              />
              <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">
                {{ passwordForm.errors.password }}
              </p>
            </div>

            <!-- Confirm Password -->
            <div>
              <Label for="password_confirmation">{{ $t('Confirm Password') }}</Label>
              <Input
                id="password_confirmation"
                v-model="passwordForm.password_confirmation"
                type="password"
                class="mt-1 bg-white dark:bg-gray-700/50 dark:text-gray-100"
                required
              />
            </div>

            <!-- Info -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
              <p class="text-sm text-yellow-800">
                <strong>{{ $t('Note:') }}</strong> {{ $t("This will change the user's password immediately. The user will need to use the new password on their next login.") }}
              </p>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
              <Button type="submit" :disabled="passwordForm.processing">
                <Key class="w-4 h-4 mr-2" />
                {{ passwordForm.processing ? $t('Changing...') : $t('Change Password') }}
              </Button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
