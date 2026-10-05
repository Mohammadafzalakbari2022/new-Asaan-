<script setup lang="ts">
import NewPasswordController from '@/actions/App/Http/Controllers/Auth/NewPasswordController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import ThemeLayout from '../../layouts/ThemeLayout.vue';

const page = usePage();
const theme = computed(() => page.props.theme as any);
const siteConfig = computed(() => page.props.siteConfig as any);
const primaryColor = computed(() => theme.value?.settings?.['colors.primary'] || theme.value?.settings?.['colors.primary_color'] || theme.value?.settings?.primary_color || '#3b82f6');

defineProps<{
    email: string;
    token: string;
}>();
</script>

<template>
    <ThemeLayout>
        <Head :title="$t('Reset Password')" />

        <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gray-50 dark:bg-slate-900">
            <div class="w-full max-w-md">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100 mb-2">
                        {{ $t('Reset Password') }}
                    </h1>
                    <p class="text-gray-600 dark:text-slate-400">
                        {{ $t('Enter your new password below') }}
                    </p>
                </div>

                <!-- Card -->
                <div class="bg-white dark:bg-slate-900 rounded-lg shadow-lg p-8">
                    <!-- Reset Password Form -->
                    <Form
                        v-bind="NewPasswordController.store.form()"
                        :reset-on-success="['password', 'password_confirmation']"
                        v-slot="{ errors, processing }"
                        class="space-y-6"
                    >
                        <!-- Hidden Fields -->
                        <input type="hidden" name="token" :value="token" />
                        <input type="hidden" name="email" :value="email" />

                        <!-- Email Field (Readonly) -->
                        <div class="space-y-2">
                            <Label for="email" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('Email Address') }}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                :value="email"
                                readonly
                                class="w-full bg-gray-50 dark:bg-slate-900"
                                disabled
                            />
                        </div>

                        <!-- New Password Field -->
                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('New Password') }}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autofocus
                                autocomplete="new-password"
                                class="w-full"
                            />
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $t('Must be at least 8 characters') }}</p>
                            <InputError :message="errors.password" />
                        </div>

                        <!-- Confirm Password Field -->
                        <div class="space-y-2">
                            <Label for="password_confirmation" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('Confirm New Password') }}
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                class="w-full"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="w-full text-white font-medium py-2.5 rounded-md hover:opacity-90 transition-opacity"
                            :style="{ backgroundColor: primaryColor }"
                            :tabindex="3"
                            :disabled="processing"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="h-5 w-5 animate-spin mr-2"
                            />
                            {{ processing ? $t('Resetting Password...') : $t('Reset Password') }}
                        </Button>
                    </Form>

                    <!-- Divider -->
                    <div class="relative my-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-slate-600"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white dark:bg-slate-900 text-gray-500 dark:text-slate-400">{{ $t('or') }}</span>
                        </div>
                    </div>

                    <!-- Back to Login -->
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-slate-400">
                            {{ $t('Remember your password?') }}
                            <TextLink
                                href="/login"
                                class="font-medium hover:underline"
                                :style="{ color: primaryColor }"
                                :tabindex="4"
                            >
                                {{ $t('Back to login') }}
                            </TextLink>
                        </p>
                    </div>
                </div>

                <!-- Back to Shop -->
                <div class="text-center mt-6">
                    <TextLink
                        href="/"
                        class="text-sm text-gray-600 dark:text-slate-400 hover:underline inline-flex items-center"
                        :tabindex="5"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        {{ $t('Back to shop') }}
                    </TextLink>
                </div>
            </div>
        </div>
    </ThemeLayout>
</template>
