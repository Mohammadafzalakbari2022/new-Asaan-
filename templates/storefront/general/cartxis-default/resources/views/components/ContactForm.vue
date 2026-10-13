<script setup lang="ts">
/**
 * Contact form for the storefront.
 *
 * Posts to the public API (POST /api/v1/contact) which stores the message for
 * the dashboard inbox. Error messages are shown under the field they belong to,
 * never in a page banner where a long form would scroll them out of sight.
 */
import { useI18nStore } from '@/Stores/i18n';
import { CheckCircle2, Loader2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

const i18n = useI18nStore();
const t = (key: string) => i18n.t(key);

const form = reactive({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
});

const errors = reactive<Record<string, string>>({});
const sending = ref(false);
const sent = ref(false);
const generalError = ref('');

const clearErrors = () => {
    Object.keys(errors).forEach((key) => delete errors[key]);
    generalError.value = '';
};

const validate = (): boolean => {
    clearErrors();
    if (!form.name.trim()) errors.name = t('Please enter your name.');
    if (!form.email.trim() && !form.phone.trim()) {
        errors.email = t(
            'Please leave either an email address or a phone number so we can reply.',
        );
    }
    if (
        form.email.trim() &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())
    ) {
        errors.email = t('Please enter a valid email address.');
    }
    if (form.message.trim().length < 5) {
        errors.message = t('Please write a slightly longer message.');
    }
    return Object.keys(errors).length === 0;
};

const submit = async () => {
    if (!validate()) return;

    sending.value = true;
    try {
        const response = await fetch('/api/v1/contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                name: form.name.trim(),
                email: form.email.trim() || null,
                phone: form.phone.trim() || null,
                subject: form.subject.trim() || null,
                message: form.message.trim(),
            }),
        });

        if (response.ok) {
            sent.value = true;
            return;
        }

        const data = await response.json().catch(() => null);
        if (data?.errors) {
            Object.entries(data.errors).forEach(([field, messages]) => {
                errors[field] = Array.isArray(messages)
                    ? messages[0]
                    : String(messages);
            });
        } else {
            generalError.value =
                data?.message ||
                t('Your message could not be sent. Please try again.');
        }
    } catch {
        generalError.value = t(
            'Your message could not be sent. Please check your connection and try again.',
        );
    } finally {
        sending.value = false;
    }
};

const fieldClass = (field: string) =>
    [
        'block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm transition-shadow focus:outline-none focus:ring-2',
        errors[field]
            ? 'border-red-400 focus:ring-red-400'
            : 'border-gray-300 focus:border-transparent focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100',
    ].join(' ');
</script>

<template>
    <div class="mt-6 rounded-lg bg-white p-8 shadow-sm dark:bg-slate-900">
        <h2 class="mb-1 text-2xl font-bold text-gray-900 dark:text-slate-100">
            {{ $t('Send us a message') }}
        </h2>
        <p class="mb-6 text-sm text-gray-500 dark:text-slate-400">
            {{
                $t(
                    'Fill in the form and we will get back to you as soon as we can.',
                )
            }}
        </p>

        <div
            v-if="sent"
            class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200"
        >
            <CheckCircle2 class="h-5 w-5 shrink-0" />
            <p>
                {{
                    $t(
                        'Thank you — your message has been received. We will get back to you soon.',
                    )
                }}
            </p>
        </div>

        <form v-else class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                    >
                        {{ $t('Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :class="fieldClass('name')"
                    />
                    <p v-if="errors.name" class="mt-1 text-sm text-red-600">
                        {{ errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                    >
                        {{ $t('Email') }}
                    </label>
                    <input
                        v-model="form.email"
                        type="email"
                        :class="fieldClass('email')"
                    />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-600">
                        {{ errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                    >
                        {{ $t('Phone') }}
                    </label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        :class="fieldClass('phone')"
                    />
                    <p v-if="errors.phone" class="mt-1 text-sm text-red-600">
                        {{ errors.phone }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                    >
                        {{ $t('Subject') }}
                    </label>
                    <input
                        v-model="form.subject"
                        type="text"
                        :class="fieldClass('subject')"
                    />
                    <p v-if="errors.subject" class="mt-1 text-sm text-red-600">
                        {{ errors.subject }}
                    </p>
                </div>
            </div>

            <div>
                <label
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                >
                    {{ $t('Message') }} <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="form.message"
                    rows="5"
                    :class="fieldClass('message')"
                ></textarea>
                <p v-if="errors.message" class="mt-1 text-sm text-red-600">
                    {{ errors.message }}
                </p>
            </div>

            <p v-if="generalError" class="text-sm text-red-600">
                {{ generalError }}
            </p>

            <button
                type="submit"
                :disabled="sending"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                {{ $t('Send message') }}
            </button>
        </form>
    </div>
</template>
