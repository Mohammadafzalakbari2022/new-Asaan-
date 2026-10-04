<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref } from 'vue';

const form = useForm({
    name: '',
    code: '',
    description: '',
    color: '#3B82F6',
    discount_percentage: 0,
    is_default: false,
    auto_assignment_rules: {
        min_orders: null as number | null,
        min_spent: null as number | null,
        min_aov: null as number | null,
    },
    status: true,
});

const submit = () => {
    form.post('/admin/customers/groups', {
        preserveScroll: true,
    });
};

const generateCode = () => {
    if (form.name) {
        form.code = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    }
};
</script>

<template>
    <AdminLayout :title="$t('Create Customer Group')">
        <Head :title="$t('Create Customer Group')" />

        <div class="p-6">
            <!-- Back Button -->
            <div class="mb-4">
                <button
                    type="button"
                    @click="router.visit('/admin/customers/groups')"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ $t('Back to Customer Groups') }}
                </button>
            </div>

            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $t('Create Customer Group') }}</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $t('Create a new customer group for segmentation and group-based pricing.') }}</p>
            </div>

            <!-- Form -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Basic Information') }}</h2>
                        <div class="space-y-4">
                            <!-- Name and Code -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Group Name *') }}</label>
                                    <input
                                        v-model="form.name"
                                        @blur="generateCode"
                                        type="text"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                                        :class="{ 'border-red-500': form.errors.name }"
                                        :placeholder="$t('e.g., VIP Members, Wholesale')"
                                    />
                                    <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ $t('Group Code') }}
                                        <span class="text-gray-500 dark:text-gray-400 font-normal">{{ $t('(auto-generated)') }}</span>
                                    </label>
                                    <input
                                        v-model="form.code"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                                        :class="{ 'border-red-500': form.errors.code }"
                                        :placeholder="$t('e.g., vip-members')"
                                    />
                                    <div v-if="form.errors.code" class="text-red-600 text-sm mt-1">{{ form.errors.code }}</div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('Lowercase letters, numbers, and dashes only') }}</p>
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Description') }}</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                                    :class="{ 'border-red-500': form.errors.description }"
                                    :placeholder="$t('Brief description of this customer group...')"
                                ></textarea>
                                <div v-if="form.errors.description" class="text-red-600 text-sm mt-1">{{ form.errors.description }}</div>
                            </div>

                            <!-- Color and Discount -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Group Color') }}</label>
                                    <div class="flex items-center space-x-3">
                                        <input
                                            v-model="form.color"
                                            type="color"
                                            class="h-10 w-20 border border-gray-300 dark:border-gray-600 rounded cursor-pointer dark:bg-gray-700/50 dark:text-gray-100"
                                        />
                                        <input
                                            v-model="form.color"
                                            type="text"
                                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                                            :class="{ 'border-red-500': form.errors.color }"
                                            :placeholder="$t('#3B82F6')"
                                        />
                                    </div>
                                    <div v-if="form.errors.color" class="text-red-600 text-sm mt-1">{{ form.errors.color }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Discount Percentage') }}</label>
                                    <div class="relative">
                                        <input
                                            v-model.number="form.discount_percentage"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 pr-8 dark:bg-gray-700/50 dark:text-gray-100"
                                            :class="{ 'border-red-500': form.errors.discount_percentage }"
                                            placeholder="0.00"
                                        />
                                        <span class="absolute right-3 top-2 text-gray-500 dark:text-gray-400">%</span>
                                    </div>
                                    <div v-if="form.errors.discount_percentage" class="text-red-600 text-sm mt-1">{{ form.errors.discount_percentage }}</div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('Group-based discount (0-100%)') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Assignment Rules -->
                    <div>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Auto-Assignment Rules') }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $t('Automatically assign customers to this group based on their purchase behavior.') }}</p>
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Minimum Orders') }}</label>
                                    <input
                                        v-model.number="form.auto_assignment_rules.min_orders"
                                        type="number"
                                        min="0"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                                        :placeholder="$t('e.g., 10')"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('Total orders placed') }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Minimum Spent ($)') }}</label>
                                    <input
                                        v-model.number="form.auto_assignment_rules.min_spent"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                                        :placeholder="$t('e.g., 1000.00')"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('Total amount spent') }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('Minimum AOV ($)') }}</label>
                                    <input
                                        v-model.number="form.auto_assignment_rules.min_aov"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700/50 dark:text-gray-100"
                                        :placeholder="$t('e.g., 100.00')"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('Average order value') }}</p>
                                </div>
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <p class="text-sm text-blue-800">
                                    <strong>{{ $t('Note:') }}</strong> {{ $t("Customers must meet ALL configured rules to be auto-assigned. Leave fields empty to ignore that criteria.") }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Settings') }}</h2>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    id="is_default"
                                    class="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                                />
                                <label for="is_default" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $t('Set as Default Group') }}
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 ml-6">{{ $t('New customers will be automatically assigned to this group') }}</p>

                            <div class="flex items-center mt-4">
                                <input
                                    v-model="form.status"
                                    type="checkbox"
                                    id="status"
                                    class="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                                />
                                <label for="status" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $t('Active') }}
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 ml-6">{{ $t('Inactive groups won\'t be available for selection') }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="button"
                            @click="router.visit('/admin/customers/groups')"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            {{ $t('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">{{ $t('Creating...') }}</span>
                            <span v-else>{{ $t('Create Group') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
