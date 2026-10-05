<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';
import CurrencySelector from '../../../components/CurrencySelector.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { ref } from 'vue';

interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
}

interface Props {
  user: User;
}

const props = defineProps<Props>();

// Profile Information Form
const profileForm = useForm({
  name: props.user.name,
  email: props.user.email,
});

const updateProfile = () => {
  profileForm.put('/account/profile', {
    preserveScroll: true,
  });
};

// Password Form
const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const updatePassword = () => {
  passwordForm.put('/account/profile/password', {
    preserveScroll: true,
    onSuccess: () => {
      passwordForm.reset();
    },
  });
};

// Email Preferences Form
const preferencesForm = useForm({
  newsletter_subscribed: true,
  order_notifications: true,
  promotional_emails: false,
});

const updatePreferences = () => {
  preferencesForm.put('/account/profile/preferences', {
    preserveScroll: true,
  });
};

// Delete Account
const showDeleteModal = ref(false);
const deleteForm = useForm({
  password: '',
});

const deleteAccount = () => {
  showDeleteModal.value = true;
};

const cancelDelete = () => {
  showDeleteModal.value = false;
  deleteForm.reset();
};

const confirmDelete = () => {
  deleteForm.delete('/account/profile', {
    preserveScroll: true,
    onError: () => {
      // Keep modal open so user can correct the password
    },
  });
};
</script>

<template>
  <ThemeLayout>
    <Head :title="$t('My Profile')" />

    <div class="container mx-auto px-4 py-8">
      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold mb-2">{{ $t('My Profile') }}</h1>
          <p class="text-gray-600 dark:text-slate-400">{{ $t('Manage your account settings and preferences') }}</p>
        </div>

        <div class="space-y-6">
          <!-- Display Language -->
          <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-xl font-semibold mb-2 dark:text-slate-100">{{ $t('Display Language') }}</h2>
            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
              {{ $t('Choose the language the site is shown to you in.') }}
            </p>
            <LanguageSwitcher />
          </div>

          <!-- Display Currency -->
          <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-xl font-semibold mb-2">{{ $t('Display Currency') }}</h2>
            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
              {{ $t('Choose how prices are shown to you. Your account is billed in AFN either way.') }}
            </p>
            <CurrencySelector />
          </div>

          <!-- Personal Information -->
          <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-xl font-semibold mb-6">{{ $t('Personal Information') }}</h2>
            
            <form @submit.prevent="updateProfile" class="space-y-4">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                  {{ $t('Full Name') }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="name"
                  v-model="profileForm.name"
                  type="text"
                  required
                  class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="{ 'border-red-500': profileForm.errors.name }"
                />
                <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">
                  {{ profileForm.errors.name }}
                </p>
              </div>

              <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                  {{ $t('Email Address') }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="email"
                  v-model="profileForm.email"
                  type="email"
                  required
                  class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="{ 'border-red-500': profileForm.errors.email }"
                />
                <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">
                  {{ profileForm.errors.email }}
                </p>
                <p v-if="!user.email_verified_at" class="mt-1 text-sm text-yellow-600">
                  {{ $t('Your email address is not verified. Please check your inbox for a verification link.') }}
                </p>
              </div>

              <div class="flex items-center gap-3 pt-2">
                <button
                  type="submit"
                  :disabled="profileForm.processing"
                  class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {{ profileForm.processing ? $t('Saving...') : $t('Save Changes') }}
                </button>
                <span v-if="profileForm.recentlySuccessful" class="text-sm text-green-600">
                  {{ $t('✓ Saved successfully') }}
                </span>
              </div>
            </form>
          </div>

          <!-- Change Password -->
          <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-xl font-semibold mb-6">{{ $t('Change Password') }}</h2>
            
            <form @submit.prevent="updatePassword" class="space-y-4">
              <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                  {{ $t('Current Password') }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="current_password"
                  v-model="passwordForm.current_password"
                  type="password"
                  required
                  class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="{ 'border-red-500': passwordForm.errors.current_password }"
                />
                <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">
                  {{ passwordForm.errors.current_password }}
                </p>
              </div>

              <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                  {{ $t('New Password') }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="password"
                  v-model="passwordForm.password"
                  type="password"
                  required
                  class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="{ 'border-red-500': passwordForm.errors.password }"
                />
                <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">
                  {{ passwordForm.errors.password }}
                </p>
                <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">
                  Minimum 8 characters
                </p>
              </div>

              <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                  {{ $t('Confirm New Password') }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="password_confirmation"
                  v-model="passwordForm.password_confirmation"
                  type="password"
                  required
                  class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>

              <div class="flex items-center gap-3 pt-2">
                <button
                  type="submit"
                  :disabled="passwordForm.processing"
                  class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {{ passwordForm.processing ? $t('Updating...') : $t('Update Password') }}
                </button>
                <span v-if="passwordForm.recentlySuccessful" class="text-sm text-green-600">
                  {{ $t('✓ Password updated') }}
                </span>
              </div>
            </form>
          </div>

          <!-- Email Preferences -->
          <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-xl font-semibold mb-6">{{ $t('Email Preferences') }}</h2>
            
            <form @submit.prevent="updatePreferences" class="space-y-4">
              <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                  <input
                    v-model="preferencesForm.order_notifications"
                    type="checkbox"
                    class="mt-1 w-4 h-4 text-blue-600 border-gray-300 dark:border-slate-600 rounded focus:ring-blue-500"
                  />
                  <div>
                    <div class="font-medium">{{ $t('Order Status Updates') }}</div>
                    <div class="text-sm text-gray-600 dark:text-slate-400">{{ $t('Get notified about your order status changes') }}</div>
                  </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer">
                  <input
                    v-model="preferencesForm.newsletter_subscribed"
                    type="checkbox"
                    class="mt-1 w-4 h-4 text-blue-600 border-gray-300 dark:border-slate-600 rounded focus:ring-blue-500"
                  />
                  <div>
                    <div class="font-medium">{{ $t('Newsletter Subscription') }}</div>
                    <div class="text-sm text-gray-600 dark:text-slate-400">{{ $t('Receive our weekly newsletter with product updates') }}</div>
                  </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer">
                  <input
                    v-model="preferencesForm.promotional_emails"
                    type="checkbox"
                    class="mt-1 w-4 h-4 text-blue-600 border-gray-300 dark:border-slate-600 rounded focus:ring-blue-500"
                  />
                  <div>
                    <div class="font-medium">{{ $t('Promotional Offers') }}</div>
                    <div class="text-sm text-gray-600 dark:text-slate-400">{{ $t('Get exclusive deals and special promotions') }}</div>
                  </div>
                </label>
              </div>

              <div class="flex items-center gap-3 pt-2">
                <button
                  type="submit"
                  :disabled="preferencesForm.processing"
                  class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {{ preferencesForm.processing ? $t('Saving...') : $t('Save Preferences') }}
                </button>
                <span v-if="preferencesForm.recentlySuccessful" class="text-sm text-green-600">
                  {{ $t('✓ Preferences saved') }}
                </span>
              </div>
            </form>
          </div>

          <!-- Danger Zone -->
          <div class="bg-red-50 rounded-lg border border-red-200 p-6">
            <h2 class="text-xl font-semibold text-red-900 mb-4">{{ $t('Danger Zone') }}</h2>
            <p class="text-sm text-red-800 mb-4">
              {{ $t('Once you delete your account, all your personal data will be permanently removed.') }}
              {{ $t('Your order history will be anonymized but retained for our records.') }}
            </p>
            
            <button
              @click="deleteAccount"
              class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              {{ $t('Delete Account') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="cancelDelete"
      >
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
          <h3 class="text-xl font-bold text-red-700 mb-2">{{ $t('Delete Your Account') }}</h3>
          <p class="text-sm text-gray-700 dark:text-slate-300 mb-4">
            {{ $t('This action is') }} <strong>{{ $t('permanent and irreversible') }}</strong>. {{ $t('Your account, cart,') }}
            {{ $t('addresses, and wishlist will be deleted. Orders will be anonymized.') }}
          </p>

          <div class="mb-4">
            <label for="delete-password" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
              {{ $t('Confirm your password') }}
            </label>
            <input
              id="delete-password"
              v-model="deleteForm.password"
              type="password"
              autocomplete="current-password"
              class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
              :class="{ 'border-red-500': deleteForm.errors.password }"
              @keyup.enter="confirmDelete"
            />
            <p v-if="deleteForm.errors.password" class="mt-1 text-sm text-red-600">
              {{ deleteForm.errors.password }}
            </p>
          </div>

          <div class="flex gap-3 justify-end">
            <button
              type="button"
              @click="cancelDelete"
              class="px-5 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
            >
              {{ $t('Cancel') }}
            </button>
            <button
              type="button"
              :disabled="deleteForm.processing || !deleteForm.password"
              @click="confirmDelete"
              class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ deleteForm.processing ? $t('Deleting...') : $t('Yes, Delete My Account') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </ThemeLayout>
</template>
