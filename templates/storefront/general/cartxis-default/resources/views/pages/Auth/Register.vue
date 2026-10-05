<script setup lang="ts">
import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ThemeLayout from '../../layouts/ThemeLayout.vue';

const page = usePage();
const theme = computed(() => page.props.theme as any);
const siteConfig = computed(() => page.props.siteConfig as any);
const primaryColor = computed(() => theme.value?.settings?.['colors.primary'] || theme.value?.settings?.['colors.primary_color'] || theme.value?.settings?.primary_color || '#3b82f6');

const showPassword = ref(false);
const showConfirmPassword = ref(false);

// Set by the referral middleware while an invitation is still waiting to be
// applied. Null when nobody sent this visitor, so nothing is shown.
const referrerName = computed(() => {
    const name = (page.props as Record<string, unknown>).referralReferrerName;

    return typeof name === 'string' && name.trim() !== '' ? name.trim() : null;
});
</script>

<template>
    <ThemeLayout>
        <Head :title="$t('Create Account')" />

        <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gray-50 dark:bg-slate-900">
            <div class="w-full max-w-md">
                <!-- Header -->
                <div class="text-center mb-8">
                    <img
                        v-if="siteConfig.logo"
                        :src="`/storage/${siteConfig.logo}`"
                        :alt="siteConfig.name"
                        class="h-16 w-auto mx-auto mb-4 object-contain"
                    />
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100 mb-2">
                        {{ $t('Create Your Account') }}
                    </h1>
                    <p class="text-gray-600 dark:text-slate-400">
                        {{ $t('Join us and start shopping today') }}
                    </p>
                </div>

                <!-- Card -->
                <div class="bg-white dark:bg-slate-900 rounded-lg shadow-lg p-8">
                    <!-- Register Form -->
                    <Form
                        v-bind="RegisteredUserController.store.form()"
                        :reset-on-success="['password', 'password_confirmation']"
                        v-slot="{ errors, processing }"
                        class="space-y-6"
                    >
                        <!-- Who sent this visitor here -->
                        <div
                            v-if="referrerName"
                            data-test="register-referrer-banner"
                            class="mb-6 rounded-md border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 px-4 py-3 text-sm text-gray-700 dark:text-slate-300"
                            :style="{ borderInlineStartColor: primaryColor }"
                        >
                            {{ $t('Invited by') }}
                            <span class="font-semibold text-gray-900 dark:text-slate-100">{{ referrerName }}</span>
                        </div>

                        <!-- Name Field -->
                        <div class="space-y-2">
                            <Label for="name" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('Full Name') }}
                            </Label>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                required
                                autofocus
                                autocomplete="name"
                                class="w-full"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <!-- Email Field -->
                        <div class="space-y-2">
                            <Label for="email" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('Email Address') }}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                :tabindex="2"
                                autocomplete="email"
                                placeholder="your.email@example.com"
                                class="w-full"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <!-- Password Field -->
                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('Password') }}
                            </Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    required
                                    :tabindex="3"
                                    autocomplete="new-password"
                                    :placeholder="$t('Create a strong password')"
                                    class="w-full pr-10"
                                />
                                <button
                                    type="button"
                                    tabindex="-1"
                                    :aria-label="showPassword ? $t('Hide password') : $t('Show password')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-slate-500 hover:text-gray-600"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $t('Must be at least 8 characters') }}</p>
                            <InputError :message="errors.password" />
                        </div>

                        <!-- Confirm Password Field -->
                        <div class="space-y-2">
                            <Label for="password_confirmation" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('Confirm Password') }}
                            </Label>
                            <div class="relative">
                                <Input
                                    id="password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    name="password_confirmation"
                                    required
                                    :tabindex="4"
                                    autocomplete="new-password"
                                    :placeholder="$t('Confirm your password')"
                                    class="w-full pr-10"
                                />
                                <button
                                    type="button"
                                    tabindex="-1"
                                    :aria-label="showConfirmPassword ? $t('Hide password') : $t('Show password')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-slate-500 hover:text-gray-600"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                >
                                    <EyeOff v-if="showConfirmPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <!--
                            Only for someone whose link did not survive being
                            pasted into a message app. Optional, and ignored if it
                            is not a real code.
                        -->
                        <div class="space-y-2">
                            <Label for="referral_code" class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ $t('Referral code') }}
                                <span class="font-normal text-gray-500 dark:text-slate-400">({{ $t('optional') }})</span>
                            </Label>
                            <Input
                                id="referral_code"
                                type="text"
                                name="referral_code"
                                :tabindex="5"
                                placeholder="AHMAD-7K2QX"
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                class="w-full"
                                data-test="register-referral-code"
                            />
                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                {{ $t("Have a friend's code? Enter it here.") }}
                            </p>
                            <InputError :message="errors.referral_code" />
                        </div>

                        <!-- Terms Checkbox (Optional - can be removed if not needed) -->
                        <div class="flex items-start">
                            <Label for="terms" class="flex items-start space-x-2 cursor-pointer text-sm text-gray-600 dark:text-slate-400">
                                <Checkbox id="terms" name="terms" :tabindex="6" class="mt-0.5" />
                                <span>
                                    {{ $t('I agree to the') }}
                                    <TextLink href="/terms" class="hover:underline" :style="{ color: primaryColor }">
                                        {{ $t('Terms of Service') }}
                                    </TextLink>
                                    {{ $t('and') }}
                                    <TextLink href="/privacy" class="hover:underline" :style="{ color: primaryColor }">
                                        {{ $t('Privacy Policy') }}
                                    </TextLink>
                                </span>
                            </Label>
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="w-full text-white font-medium py-2.5 rounded-md hover:opacity-90 transition-opacity"
                            :style="{ backgroundColor: primaryColor }"
                            :tabindex="7"
                            :disabled="processing"
                            data-test="register-user-button"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="h-5 w-5 animate-spin mr-2"
                            />
                            {{ processing ? $t('Creating Account...') : $t('Create Account') }}
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

                    <!-- Login Link -->
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-slate-400">
                            {{ $t('Already have an account?') }}
                            <TextLink
                                href="/login"
                                class="font-medium hover:underline"
                                :style="{ color: primaryColor }"
                                :tabindex="8"
                            >
                                {{ $t('Log in') }}
                            </TextLink>
                        </p>
                    </div>
                </div>

                <!-- Back to Shop -->
                <div class="text-center mt-6">
                    <TextLink
                        href="/"
                        class="text-sm text-gray-600 dark:text-slate-400 hover:underline inline-flex items-center"
                        :tabindex="9"
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
