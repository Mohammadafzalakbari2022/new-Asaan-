<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Clock, Lock, ShieldAlert, ShieldCheck, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';

interface Identity {
    verified: boolean;
    verified_at: string | null;
    status: string;
    submitted_at: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    can_submit: boolean;
}

interface Requirements {
    max_upload_kb: number;
    accepted_types: string;
    required_document: string;
}

const props = defineProps<{
    identity: Identity;
    requirements: Requirements;
    available: boolean;
}>();

const form = useForm({
    national_id: '',
    full_name: '',
    father_name: '',
    date_of_birth: '',
    image: null as File | null,
});

const fileName = ref('');

const onFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.image = input.files?.[0] ?? null;
    fileName.value = form.image?.name ?? '';
};

const submit = () => {
    form.post('/account/identity', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            fileName.value = '';
        },
    });
};
</script>

<template>
    <Head :title="$t('Verify your identity')" />

    <ThemeLayout>
        <div class="container mx-auto max-w-3xl px-4 py-10">
            <Link
                href="/account"
                class="inline-flex items-center text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700"
            >
                &larr; {{ $t('Account') }}
            </Link>

            <div class="mt-4 mb-8">
                <h1 class="mb-2 text-3xl font-bold">
                    {{ $t('Verify your identity') }}
                </h1>
                <p class="text-gray-600 dark:text-slate-400">
                    {{
                        $t(
                            'Add a photo of your Tazkira so our team can check it.',
                        )
                    }}
                </p>
            </div>

            <!-- Closed -->
            <div
                v-if="!available"
                class="rounded-lg border border-amber-200 bg-amber-50 p-5"
            >
                <p class="text-sm text-amber-900">
                    {{
                        $t(
                            'Identity verification is not available right now. Please try again later.',
                        )
                    }}
                </p>
            </div>

            <template v-else>
                <!-- Verified -->
                <div
                    v-if="identity.verified"
                    class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-5"
                >
                    <ShieldCheck
                        class="mt-0.5 h-6 w-6 flex-shrink-0 text-green-600"
                    />
                    <div>
                        <h2 class="font-semibold text-green-900">
                            {{ $t('Your identity is already verified.') }}
                        </h2>
                        <p
                            v-if="identity.verified_at"
                            class="mt-1 text-sm text-green-800"
                        >
                            {{ identity.verified_at }}
                        </p>
                    </div>
                </div>

                <!-- Waiting -->
                <div
                    v-else-if="
                        identity.submitted_at && identity.status === 'pending'
                    "
                    class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-5"
                >
                    <Clock class="mt-0.5 h-6 w-6 flex-shrink-0 text-blue-600" />
                    <div>
                        <h2 class="font-semibold text-blue-900">
                            {{
                                $t(
                                    'You already have a document waiting to be reviewed. We will let you know as soon as it has been looked at.',
                                )
                            }}
                        </h2>
                        <p
                            v-if="identity.submitted_at"
                            class="mt-1 text-sm text-blue-800"
                        >
                            {{ identity.submitted_at }}
                        </p>
                    </div>
                </div>

                <!-- Rejected -->
                <div
                    v-else-if="identity.status === 'rejected'"
                    class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-5"
                >
                    <ShieldAlert
                        class="mt-0.5 h-6 w-6 flex-shrink-0 text-red-600"
                    />
                    <div>
                        <h2 class="font-semibold text-red-900">
                            {{ $t('We could not accept your last document.') }}
                        </h2>
                        <p
                            v-if="identity.rejection_reason"
                            class="mt-1 text-sm text-red-800"
                        >
                            {{ identity.rejection_reason }}
                        </p>
                        <p class="mt-1 text-sm text-red-800">
                            {{ $t('You can send a new one below.') }}
                        </p>
                    </div>
                </div>

                <!-- Form -->
                <form
                    v-if="identity.can_submit"
                    @submit.prevent="submit"
                    class="mt-6 space-y-5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-6 shadow-sm"
                >
                    <div>
                        <label
                            for="national_id"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                        >
                            {{ $t('National ID') }}
                        </label>
                        <input
                            id="national_id"
                            v-model="form.national_id"
                            type="text"
                            class="w-full rounded-lg border bg-gray-50 dark:bg-slate-900 px-3 py-2.5 text-sm text-gray-900 dark:text-slate-100 transition-all focus:ring-2"
                            :class="
                                form.errors.national_id
                                    ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20'
                                    : 'border-gray-200 focus:border-blue-500 focus:ring-blue-500/20'
                            "
                        />
                        <p
                            v-if="form.errors.national_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.national_id }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                            {{
                                $t('Enter the number printed on your Tazkira.')
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="full_name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                        >
                            {{ $t('Full Name') }}
                        </label>
                        <input
                            id="full_name"
                            v-model="form.full_name"
                            type="text"
                            class="w-full rounded-lg border bg-gray-50 dark:bg-slate-900 px-3 py-2.5 text-sm text-gray-900 dark:text-slate-100 transition-all focus:ring-2"
                            :class="
                                form.errors.full_name
                                    ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20'
                                    : 'border-gray-200 focus:border-blue-500 focus:ring-blue-500/20'
                            "
                        />
                        <p
                            v-if="form.errors.full_name"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.full_name }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                            {{
                                $t(
                                    'Enter your full name exactly as it is written on the Tazkira.',
                                )
                            }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="father_name"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                            >
                                {{ $t("Father's name (optional)") }}
                            </label>
                            <input
                                id="father_name"
                                v-model="form.father_name"
                                type="text"
                                class="w-full rounded-lg border bg-gray-50 dark:bg-slate-900 px-3 py-2.5 text-sm text-gray-900 dark:text-slate-100 transition-all focus:ring-2"
                                :class="
                                    form.errors.father_name
                                        ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20'
                                        : 'border-gray-200 focus:border-blue-500 focus:ring-blue-500/20'
                                "
                            />
                            <p
                                v-if="form.errors.father_name"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.father_name }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="date_of_birth"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                            >
                                {{ $t('Date of birth (optional)') }}
                            </label>
                            <input
                                id="date_of_birth"
                                v-model="form.date_of_birth"
                                type="date"
                                class="w-full rounded-lg border bg-gray-50 dark:bg-slate-900 px-3 py-2.5 text-sm text-gray-900 dark:text-slate-100 transition-all focus:ring-2"
                                :class="
                                    form.errors.date_of_birth
                                        ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20'
                                        : 'border-gray-200 focus:border-blue-500 focus:ring-blue-500/20'
                                "
                            />
                            <p
                                v-if="form.errors.date_of_birth"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.date_of_birth }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-300"
                            >{{ $t('Photo of your Tazkira') }}</label
                        >
                        <label
                            class="flex w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed py-6 transition-colors"
                            :class="
                                form.errors.image
                                    ? 'border-red-300 bg-red-50'
                                    : 'border-gray-300 bg-gray-50 hover:bg-gray-100'
                            "
                        >
                            <Upload class="mb-2 h-6 w-6 text-gray-400 dark:text-slate-500" />
                            <span class="text-sm font-medium text-gray-700 dark:text-slate-300">
                                {{ fileName || $t('Choose a file') }}
                            </span>
                            <span class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                {{
                                    $t('JPG, PNG or WebP, up to {size} KB.', {
                                        size: requirements.max_upload_kb,
                                    })
                                }}
                            </span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onFile"
                            />
                        </label>
                        <p
                            v-if="form.errors.image"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.image }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            <ShieldCheck class="mr-2 h-4 w-4" />
                            {{
                                form.processing
                                    ? $t('Sending...')
                                    : $t('Send for review')
                            }}
                        </button>
                    </div>

                    <p
                        class="flex items-start gap-1.5 pt-1 text-xs text-gray-500 dark:text-slate-400"
                    >
                        <Lock class="mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                        {{
                            $t(
                                'Your document is private. Only the review team can see it.',
                            )
                        }}
                    </p>
                </form>
            </template>
        </div>
    </ThemeLayout>
</template>
