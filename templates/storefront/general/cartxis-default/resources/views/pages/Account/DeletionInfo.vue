<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';

interface Props {
    appName: string;
    supportEmail: string;
    appUrl: string;
}

const props = defineProps<Props>();
</script>

<template>
    <ThemeLayout>
        <Head :title="$t('Account Deletion Request')" />

        <div class="container mx-auto px-4 py-12 max-w-3xl">
            <!-- Header -->
            <div class="mb-10">
                <h1 class="text-3xl font-bold mb-3">{{ $t('Account & Data Deletion') }}</h1>
                <p class="text-gray-600">
                    {{ $t('This page explains how to delete your {app} account and what happens to your data.', { app: props.appName }) }}
                </p>
            </div>

            <!-- How to Delete -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">{{ $t('How to Delete Your Account') }}</h2>
                <p class="text-gray-700 mb-4">
                    {{ $t('You can delete your account directly from within the app or website by following these steps:') }}
                </p>
                <ol class="list-decimal list-inside space-y-3 text-gray-700">
                    <li>
                        {{ $t('Log in to your account at') }}
                        <a :href="props.appUrl + '/account/login'" class="text-blue-600 hover:underline">
                            {{ props.appUrl }}/account/login
                        </a>
                    </li>
                    <li>{{ $t('Go to') }} <strong>{{ $t('My Profile') }}</strong> {{ $t('(accessible from the top navigation or account menu)') }}</li>
                    <li>{{ $t('Scroll to the') }} <strong>{{ $t('Danger Zone') }}</strong> {{ $t('section at the bottom of the page') }}</li>
                    <li>{{ $t('Click') }} <strong>{{ $t('"Delete Account"') }}</strong></li>
                    <li>{{ $t('Enter your current password to confirm, then click') }} <strong>{{ $t('"Yes, Delete My Account"') }}</strong></li>
                </ol>
                <p class="text-gray-600 text-sm mt-4">
                    {{ $t('Your account will be permanently deleted immediately after confirmation.') }}
                </p>
            </div>

            <!-- What gets deleted -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">{{ $t('What Data Is Deleted') }}</h2>
                <p class="text-gray-700 mb-3">
                    {{ $t('When you delete your account, the following personal data is') }} <strong>{{ $t('permanently and immediately deleted') }}</strong>:
                </p>
                <ul class="list-disc list-inside space-y-2 text-gray-700 mb-4">
                    <li>{{ $t('Your account profile (name, email address, phone number)') }}</li>
                    <li>{{ $t('Saved delivery addresses') }}</li>
                    <li>{{ $t('Wishlist items') }}</li>
                    <li>{{ $t('Shopping cart contents') }}</li>
                    <li>{{ $t('All active login sessions and API tokens') }}</li>
                </ul>
            </div>

            <!-- What is retained -->
            <div class="bg-amber-50 rounded-lg border border-amber-200 p-6 mb-6">
                <h2 class="text-xl font-semibold text-amber-900 mb-4">{{ $t('What Data Is Retained') }}</h2>
                <p class="text-amber-800 mb-3">
                    {{ $t('To comply with financial and legal obligations,') }} <strong>{{ $t('order history is retained') }}</strong> {{ $t('but fully anonymized:') }}
                </p>
                <ul class="list-disc list-inside space-y-2 text-amber-800 mb-3">
                    <li>{{ $t('Your name and email address are removed from all orders') }}</li>
                    <li>{{ $t('The order record itself (items, amounts, dates) is kept for accounting purposes') }}</li>
                    <li>{{ $t('Anonymized order records are') }} <strong>{{ $t('not') }}</strong> {{ $t('linked to you in any way after deletion') }}</li>
                </ul>
                <p class="text-amber-700 text-sm">
                    {{ $t('Retention period: anonymized order records are kept for a minimum of 7 years as required by standard accounting regulations.') }}
                </p>
            </div>

            <!-- Contact / alternative -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">{{ $t('Request Deletion by Email') }}</h2>
                <p class="text-gray-700 mb-3">
                    {{ $t('If you are unable to log in or access your account, you can request deletion by contacting our support team:') }}
                </p>
                <p class="text-gray-700">
                    {{ $t('Email:') }}
                    <a :href="'mailto:' + props.supportEmail" class="text-blue-600 hover:underline font-medium">
                        {{ props.supportEmail }}
                    </a>
                </p>
                <p class="text-gray-600 text-sm mt-3">
                    {{ $t('Please include the email address associated with your account. We will process the request within 7 business days.') }}
                </p>
            </div>

            <!-- Developer info -->
            <p class="text-gray-500 text-sm text-center">
                {{ $t('This page is provided by') }} <strong>{{ props.appName }}</strong> {{ $t('in compliance with') }}
                {{ $t('Google Play Store and Apple App Store data-safety requirements.') }}
            </p>
        </div>
    </ThemeLayout>
</template>
