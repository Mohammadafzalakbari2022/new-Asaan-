<script setup lang="ts">
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    ImageOff,
    ShieldCheck,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Verification {
    id: number;
    status: string;
    document_type: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    reviewer: string | null;
    rejection_reason: string | null;
    review_note: string | null;
    has_image: boolean;
    image_url: string;
    national_id: string;
    full_name: string;
    father_name: string | null;
    date_of_birth: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        verified_at: string | null;
    } | null;
    attempts: number;
}

const props = defineProps<{ verification: Verification }>();

const approveForm = useForm({ note: '' });
const rejectForm = useForm({ reason: '', note: '' });

const confirmingDelete = ref(false);

function approve() {
    approveForm.post(
        `/admin/customers/identity/${props.verification.id}/approve`,
        {
            preserveScroll: true,
        },
    );
}

function reject() {
    rejectForm.post(
        `/admin/customers/identity/${props.verification.id}/reject`,
        {
            preserveScroll: true,
        },
    );
}

function deleteDocument() {
    router.delete(
        `/admin/customers/identity/${props.verification.id}/document`,
        {
            preserveScroll: true,
            onSuccess: () => {
                confirmingDelete.value = false;
            },
        },
    );
}

function statusLabel(value: string): string {
    if (value === 'approved') return 'Approved';
    if (value === 'rejected') return 'Rejected';

    return 'Pending';
}

function statusClasses(value: string): string {
    if (value === 'approved') {
        return 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800';
    }
    if (value === 'rejected') {
        return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800';
    }

    return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800';
}
</script>

<template>
    <Head :title="verification.user?.name ?? $t('Identity Verification')" />

    <AdminLayout :title="$t('Identity Verification')">
        <div class="max-w-5xl space-y-6 p-6">
            <!-- Header -->
            <div>
                <Link
                    href="/admin/customers/identity"
                    class="inline-flex items-center text-xs font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <ArrowLeft class="mr-1 h-3.5 w-3.5" />
                    {{ $t('Back to the queue') }}
                </Link>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ verification.user?.name ?? $t('No name given') }}
                    </h1>
                    <span
                        :class="[
                            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                            statusClasses(verification.status),
                        ]"
                    >
                        {{ $t(statusLabel(verification.status)) }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ verification.user?.email }}
                    <span
                        v-if="verification.attempts"
                        class="text-gray-400 dark:text-gray-500"
                    >
                        · {{ $t('Attempts') }}: {{ verification.attempts }}
                    </span>
                </p>
                <p
                    class="mt-2 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"
                >
                    <ShieldCheck class="h-3.5 w-3.5" />
                    {{
                        $t(
                            'Only the review team can see the number and the photo. They are not kept in the list, a link or a log.',
                        )
                    }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Document image -->
                <div
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2
                        class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $t('Document') }}
                    </h2>
                    <div
                        v-if="verification.has_image"
                        class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <img
                            :src="verification.image_url"
                            :alt="$t('Document')"
                            class="h-auto w-full"
                        />
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-14 text-gray-400 dark:text-gray-500"
                    >
                        <ImageOff class="mb-2 h-8 w-8" />
                        <p class="text-sm">{{ $t('No document image') }}</p>
                    </div>
                </div>

                <!-- Typed details -->
                <div
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2
                        class="mb-4 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $t('What is on the document') }}
                    </h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $t('Name on document') }}
                            </dt>
                            <dd
                                class="text-right font-medium text-gray-900 dark:text-white"
                            >
                                {{ verification.full_name }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $t('National ID') }}
                            </dt>
                            <dd
                                class="text-right font-mono font-medium text-gray-900 dark:text-white"
                            >
                                {{ verification.national_id }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $t("Father's name") }}
                            </dt>
                            <dd
                                class="text-right font-medium text-gray-900 dark:text-white"
                            >
                                {{ verification.father_name ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $t('Date of Birth') }}
                            </dt>
                            <dd
                                class="text-right font-medium text-gray-900 dark:text-white"
                            >
                                {{ verification.date_of_birth ?? '—' }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-4 border-t border-gray-100 pt-3 dark:border-gray-700"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $t('Submitted') }}
                            </dt>
                            <dd
                                class="text-right text-gray-700 dark:text-gray-200"
                            >
                                <DateDisplay
                                    :value="verification.submitted_at"
                                />
                            </dd>
                        </div>
                        <div
                            v-if="verification.reviewed_at"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $t('Reviewed') }}
                            </dt>
                            <dd
                                class="text-right text-gray-700 dark:text-gray-200"
                            >
                                <DateDisplay
                                    :value="verification.reviewed_at"
                                />
                            </dd>
                        </div>
                        <div
                            v-if="verification.reviewer"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ $t('Reviewer') }}
                            </dt>
                            <dd
                                class="text-right text-gray-700 dark:text-gray-200"
                            >
                                {{ verification.reviewer }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Decision -->
            <div
                v-if="verification.status === 'pending'"
                class="grid grid-cols-1 gap-6 lg:grid-cols-2"
            >
                <!-- Approve -->
                <div
                    class="rounded-xl border border-green-200 bg-white p-5 shadow-sm dark:border-green-800 dark:bg-gray-800"
                >
                    <h2
                        class="flex items-center gap-2 text-sm font-semibold text-green-800 dark:text-green-300"
                    >
                        <CheckCircle class="h-4 w-4" />
                        {{ $t('Approve this identity') }}
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{
                            $t(
                                'Approving marks this account as verified and lets the customer earn referral rewards.',
                            )
                        }}
                    </p>
                    <form @submit.prevent="approve" class="mt-4 space-y-3">
                        <textarea
                            v-model="approveForm.note"
                            rows="2"
                            :placeholder="$t('Optional note for the record.')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 transition-all placeholder:text-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500/20 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                        ></textarea>
                        <p
                            v-if="approveForm.errors.note"
                            class="text-xs text-red-600 dark:text-red-400"
                        >
                            {{ approveForm.errors.note }}
                        </p>
                        <button
                            type="submit"
                            :disabled="approveForm.processing"
                            class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2.5 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-green-700 disabled:opacity-50"
                        >
                            <CheckCircle class="mr-2 h-4 w-4" />
                            {{ $t('Approve') }}
                        </button>
                    </form>
                </div>

                <!-- Reject -->
                <div
                    class="rounded-xl border border-red-200 bg-white p-5 shadow-sm dark:border-red-800 dark:bg-gray-800"
                >
                    <h2
                        class="flex items-center gap-2 text-sm font-semibold text-red-800 dark:text-red-300"
                    >
                        <XCircle class="h-4 w-4" />
                        {{ $t('Reject this identity') }}
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{
                            $t(
                                'Why was the document not accepted? The customer sees this.',
                            )
                        }}
                    </p>
                    <form @submit.prevent="reject" class="mt-4 space-y-3">
                        <textarea
                            v-model="rejectForm.reason"
                            rows="2"
                            :placeholder="
                                $t(
                                    'Tell the customer why the document was not accepted.',
                                )
                            "
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 transition-all placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                        ></textarea>
                        <p
                            v-if="rejectForm.errors.reason"
                            class="text-xs text-red-600 dark:text-red-400"
                        >
                            {{ rejectForm.errors.reason }}
                        </p>
                        <textarea
                            v-model="rejectForm.note"
                            rows="2"
                            :placeholder="$t('Optional note for the record.')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 transition-all placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                        ></textarea>
                        <p
                            v-if="rejectForm.errors.note"
                            class="text-xs text-red-600 dark:text-red-400"
                        >
                            {{ rejectForm.errors.note }}
                        </p>
                        <button
                            type="submit"
                            :disabled="rejectForm.processing"
                            class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2.5 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-red-700 disabled:opacity-50"
                        >
                            <XCircle class="mr-2 h-4 w-4" />
                            {{ $t('Reject') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Already reviewed -->
            <div
                v-else
                class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h2
                    class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    <ShieldCheck class="h-4 w-4 text-gray-400" />
                    {{ $t('This submission has already been reviewed.') }}
                </h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div
                        v-if="verification.rejection_reason"
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ $t('Rejection reason') }}
                        </dt>
                        <dd class="text-right text-gray-900 dark:text-white">
                            {{ verification.rejection_reason }}
                        </dd>
                    </div>
                    <div
                        v-if="verification.review_note"
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ $t('Reviewer note') }}
                        </dt>
                        <dd class="text-right text-gray-900 dark:text-white">
                            {{ verification.review_note }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Danger zone -->
            <div
                v-if="verification.has_image"
                class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h2
                    class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    <AlertTriangle class="h-4 w-4 text-red-500" />
                    {{ $t('Delete the document image') }}
                </h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Deleting the picture keeps the record but removes the scan. It cannot be undone.',
                        )
                    }}
                </p>
                <div class="mt-3">
                    <button
                        v-if="!confirmingDelete"
                        type="button"
                        @click="confirmingDelete = true"
                        class="inline-flex items-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                    >
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                        {{ $t('Delete') }}
                    </button>
                    <div v-else class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="deleteDocument"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"
                        >
                            {{ $t('Yes') }}
                        </button>
                        <button
                            type="button"
                            @click="confirmingDelete = false"
                            class="rounded-lg bg-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            {{ $t('Cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
