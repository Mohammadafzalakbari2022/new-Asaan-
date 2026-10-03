<script setup lang="ts">
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Clock,
    Eye,
    Inbox,
    Search,
    ShieldCheck,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Row {
    id: number;
    status: string;
    user: { id: number; name: string; email: string } | null;
    name_on_document: string;
    national_id_masked: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    reviewer: string | null;
    rejection_reason: string | null;
}

interface Props {
    verifications: {
        data: Row[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { status: string | null; search: string | null };
    counts: { pending: number; approved: number; rejected: number };
}

const props = defineProps<Props>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

const tabs = [
    { value: '', label: 'All', icon: Inbox },
    { value: 'pending', label: 'Pending', icon: Clock },
    { value: 'approved', label: 'Approved', icon: ShieldCheck },
    { value: 'rejected', label: 'Rejected', icon: XCircle },
];

function apply(next?: string) {
    if (next !== undefined) status.value = next;

    router.get(
        '/admin/customers/identity',
        {
            status: status.value || undefined,
            search: search.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function countFor(value: string): number {
    if (value === 'pending') return props.counts.pending;
    if (value === 'approved') return props.counts.approved;
    if (value === 'rejected') return props.counts.rejected;

    return props.counts.pending + props.counts.approved + props.counts.rejected;
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
    <Head :title="$t('Identity Verification')" />

    <AdminLayout :title="$t('Identity Verification')">
        <div class="space-y-6 p-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Identity Verification') }}
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{
                        $t(
                            "Check each customer's Tazkira against the details they typed before approving their account.",
                        )
                    }}
                </p>
            </div>

            <!-- Filter tabs -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    @click="apply(tab.value)"
                    :class="[
                        'inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors',
                        status === tab.value
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700/50',
                    ]"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ $t(tab.label) }}
                    <span
                        :class="[
                            'inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                            status === tab.value
                                ? 'bg-white/25 text-white'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                        ]"
                    >
                        {{ countFor(tab.value) }}
                    </span>
                </button>
            </div>

            <!-- Search -->
            <div
                class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        v-model="search"
                        @keyup.enter="apply()"
                        type="text"
                        :placeholder="$t('Name or email')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pr-24 pl-10 text-sm text-gray-900 transition-all placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                    />
                    <button
                        type="button"
                        @click="apply()"
                        class="absolute top-1/2 right-1.5 -translate-y-1/2 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-blue-700"
                    >
                        {{ $t('Search') }}
                    </button>
                </div>
            </div>

            <!-- List -->
            <div
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50/80 dark:bg-gray-700/50">
                            <tr>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    {{ $t('Customer') }}
                                </th>
                                <th
                                    class="hidden px-6 py-4 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase md:table-cell dark:text-gray-400"
                                >
                                    {{ $t('Name on document') }}
                                </th>
                                <th
                                    class="hidden px-6 py-4 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase lg:table-cell dark:text-gray-400"
                                >
                                    {{ $t('National ID') }}
                                </th>
                                <th
                                    class="hidden px-6 py-4 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase lg:table-cell dark:text-gray-400"
                                >
                                    {{ $t('Submitted') }}
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    {{ $t('Status') }}
                                </th>
                                <th
                                    class="px-6 py-4 text-right text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    {{ $t('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-800"
                        >
                            <tr v-if="verifications.data.length === 0">
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div
                                        class="flex flex-col items-center justify-center"
                                    >
                                        <div
                                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-50 text-gray-400 dark:bg-gray-700"
                                        >
                                            <Inbox class="h-8 w-8" />
                                        </div>
                                        <h3
                                            class="mb-1 text-lg font-bold text-gray-900 dark:text-white"
                                        >
                                            {{ $t('Nothing waiting') }}
                                        </h3>
                                        <p
                                            class="max-w-sm text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    'There are no identity submissions to review right now.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="row in verifications.data"
                                :key="row.id"
                                class="transition-colors hover:bg-blue-50/50 dark:hover:bg-blue-900/10"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            row.user?.name ??
                                            $t('No name given')
                                        }}
                                    </div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ row.user?.email }}
                                    </div>
                                </td>
                                <td
                                    class="hidden px-6 py-4 text-sm whitespace-nowrap text-gray-700 md:table-cell dark:text-gray-200"
                                >
                                    {{ row.name_on_document }}
                                </td>
                                <td
                                    class="hidden px-6 py-4 font-mono text-sm whitespace-nowrap text-gray-600 lg:table-cell dark:text-gray-300"
                                >
                                    {{ row.national_id_masked ?? '—' }}
                                </td>
                                <td
                                    class="hidden px-6 py-4 text-sm whitespace-nowrap text-gray-500 lg:table-cell dark:text-gray-400"
                                >
                                    <DateDisplay :value="row.submitted_at" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                            statusClasses(row.status),
                                        ]"
                                    >
                                        {{ $t(statusLabel(row.status)) }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-right whitespace-nowrap"
                                >
                                    <Link
                                        :href="`/admin/customers/identity/${row.id}`"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                        {{ $t('Review') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    class="flex items-center justify-between border-t border-gray-100 bg-gray-50/50 px-6 py-4 dark:border-gray-700 dark:bg-gray-700/50"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $t('Showing') }}
                        <span class="font-medium">{{
                            verifications.from || 0
                        }}</span>
                        {{ $t('to') }}
                        <span class="font-medium">{{
                            verifications.to || 0
                        }}</span>
                        {{ $t('of') }}
                        <span class="font-medium">{{
                            verifications.total
                        }}</span>
                        {{ $t('results') }}
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="verifications.prev_page_url"
                            :href="verifications.prev_page_url"
                            class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            {{ $t('Previous') }}
                        </Link>
                        <Link
                            v-if="verifications.next_page_url"
                            :href="verifications.next_page_url"
                            class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            {{ $t('Next') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
