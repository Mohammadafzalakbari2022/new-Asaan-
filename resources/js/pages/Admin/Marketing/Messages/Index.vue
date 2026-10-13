<script setup lang="ts">
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import axios from '@/lib/axios';
import { Head, router } from '@inertiajs/vue3';
import {
    Inbox,
    Loader2,
    MailOpen,
    MessageSquare,
    Search,
    Send,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ContactMessage {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    subject: string | null;
    message: string;
    status: 'new' | 'read' | 'replied';
    created_at: string;
    replied_at: string | null;
}

interface PaginatedMessages {
    data: ContactMessage[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Stats {
    total: number;
    new: number;
    read: number;
    replied: number;
}

const props = defineProps<{
    messages: PaginatedMessages;
    filters: { search?: string; status?: string };
    stats: Stats;
}>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');

// The counters shown at the top. Kept as a local copy so opening a message can
// nudge the "new" count down without writing back into the prop.
const stats = ref({ ...props.stats });
watch(
    () => props.stats,
    (val) => {
        stats.value = { ...val };
    },
    { deep: true },
);

const selected = ref<ContactMessage | null>(null);
const replyText = ref('');
const sending = ref(false);
const replyError = ref('');
const replySent = ref(false);

const applyFilters = () => {
    router.get(
        '/admin/marketing/messages',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

watch(search, (val, old) => {
    if (val === '' && old !== '') applyFilters();
});

const clearFilters = () => {
    search.value = '';
    statusFilter.value = '';
    applyFilters();
};

const hasActiveFilters = computed(() => !!(search.value || statusFilter.value));

const openMessage = async (message: ContactMessage) => {
    replyError.value = '';
    replySent.value = false;
    replyText.value = '';
    try {
        const { data } = await axios.get(
            `/admin/marketing/messages/${message.id}`,
        );
        selected.value = data.data as ContactMessage;
    } catch {
        selected.value = { ...message };
    }
    if (message.status === 'new') {
        message.status = 'read';
        stats.value.new = Math.max(0, stats.value.new - 1);
        stats.value.read += 1;
    }
};

const closeMessage = () => {
    selected.value = null;
    replyText.value = '';
    replyError.value = '';
    replySent.value = false;
};

const sendReply = async () => {
    if (!selected.value || replyText.value.trim().length < 2) return;
    sending.value = true;
    replyError.value = '';
    try {
        await axios.post(
            `/admin/marketing/messages/${selected.value.id}/reply`,
            {
                reply: replyText.value,
            },
        );
        selected.value.status = 'replied';
        replySent.value = true;
        replyText.value = '';
        router.reload({ only: ['messages', 'stats'] });
    } catch (error: any) {
        replyError.value =
            error?.response?.data?.message ||
            'The reply could not be sent. Please try again.';
    } finally {
        sending.value = false;
    }
};

const removeMessage = (message: ContactMessage) => {
    if (!confirm('Delete this message? This cannot be undone.')) return;
    router.delete(`/admin/marketing/messages/${message.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (selected.value?.id === message.id) closeMessage();
        },
    });
};

const statusClass = (status: string) => {
    switch (status) {
        case 'new':
            return 'bg-blue-100 text-blue-700';
        case 'replied':
            return 'bg-green-100 text-green-700';
        default:
            return 'bg-gray-100 text-gray-600';
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="$t('Messages')" />

        <div class="space-y-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ $t('Messages') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Messages sent through the website contact form. Reply by email from here.',
                        )
                    }}
                </p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div
                    class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="rounded-lg bg-blue-50 p-3">
                        <Inbox class="h-5 w-5 text-blue-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('New') }}
                        </p>
                        <p
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ stats.new.toLocaleString() }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="rounded-lg bg-gray-100 p-3">
                        <MailOpen class="h-5 w-5 text-gray-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('Read') }}
                        </p>
                        <p
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ stats.read.toLocaleString() }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="rounded-lg bg-green-50 p-3">
                        <Send class="h-5 w-5 text-green-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('Replied') }}
                        </p>
                        <p
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ stats.replied.toLocaleString() }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="rounded-lg bg-purple-50 p-3">
                        <MessageSquare class="h-5 w-5 text-purple-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('Total') }}
                        </p>
                        <p
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ stats.total.toLocaleString() }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Toolbar + Table -->
            <div
                class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex flex-col gap-3 p-4 sm:flex-row">
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('Search messages…')"
                            class="w-full rounded-lg border border-gray-300 py-2 pr-4 pl-9 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100"
                        @change="applyFilters"
                    >
                        <option value="">{{ $t('All statuses') }}</option>
                        <option value="new">{{ $t('New') }}</option>
                        <option value="read">{{ $t('Read') }}</option>
                        <option value="replied">{{ $t('Replied') }}</option>
                    </select>
                    <button
                        v-if="hasActiveFilters"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400"
                        @click="clearFilters"
                    >
                        <X class="h-3 w-3" />
                        {{ $t('Clear') }}
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-t border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-700/50"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t('From') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t('Subject') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t('Status') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t('Received') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!messages.data.length">
                                <td colspan="5" class="px-4 py-16 text-center">
                                    <Inbox
                                        class="mx-auto h-8 w-8 text-gray-300"
                                    />
                                    <p
                                        class="mt-2 text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $t('No messages yet.') }}
                                    </p>
                                </td>
                            </tr>
                            <tr
                                v-for="message in messages.data"
                                :key="message.id"
                                class="cursor-pointer border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                                @click="openMessage(message)"
                            >
                                <td class="px-4 py-3">
                                    <p
                                        class="font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{ message.name }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ message.email || message.phone }}
                                    </p>
                                </td>
                                <td
                                    class="max-w-xs truncate px-4 py-3 text-gray-600 dark:text-gray-400"
                                >
                                    {{ message.subject || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                        :class="statusClass(message.status)"
                                    >
                                        {{ message.status }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-gray-500 dark:text-gray-400"
                                >
                                    <DateDisplay :value="message.created_at" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20"
                                        @click.stop="removeMessage(message)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                        {{ $t('Delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="messages.last_page > 1"
                    class="flex items-center justify-between border-t border-gray-200 px-4 py-4 dark:border-gray-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{
                            $t('Showing {from}–{to} of {total}', {
                                from: messages.from,
                                to: messages.to,
                                total: messages.total,
                            })
                        }}
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="link in messages.links"
                            :key="link.label"
                            :disabled="!link.url"
                            class="rounded-lg border px-3 py-1 text-sm transition-colors"
                            :class="
                                link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : link.url
                                      ? 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300'
                                      : 'cursor-not-allowed border-gray-200 bg-white text-gray-300 dark:border-gray-700 dark:bg-gray-800'
                            "
                            @click="
                                link.url &&
                                router.get(
                                    link.url,
                                    {},
                                    { preserveScroll: true },
                                )
                            "
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Message detail -->
        <div
            v-if="selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeMessage"
        >
            <div
                class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800"
            >
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ selected.subject || $t('No subject') }}
                        </h2>
                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ selected.name }}
                            <span v-if="selected.email"
                                >· {{ selected.email }}</span
                            >
                            <span v-if="selected.phone"
                                >· {{ selected.phone }}</span
                            >
                        </p>
                    </div>
                    <button
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                        @click="closeMessage"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div
                    class="rounded-lg bg-gray-50 p-4 text-sm whitespace-pre-line text-gray-700 dark:bg-gray-900/50 dark:text-gray-200"
                >
                    {{ selected.message }}
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    <DateDisplay :value="selected.created_at" />
                </p>

                <div
                    class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700"
                >
                    <label
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ $t('Reply by email') }}
                    </label>
                    <textarea
                        v-model="replyText"
                        rows="4"
                        :disabled="!selected.email || sending"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-100 dark:disabled:bg-gray-900"
                        :placeholder="
                            selected.email
                                ? $t('Type your reply…')
                                : $t(
                                      'This message has no email address to reply to.',
                                  )
                        "
                    ></textarea>

                    <p v-if="replyError" class="mt-2 text-sm text-red-600">
                        {{ replyError }}
                    </p>
                    <p v-if="replySent" class="mt-2 text-sm text-green-600">
                        {{ $t('Reply sent.') }}
                    </p>

                    <div class="mt-3 flex justify-end gap-2">
                        <button
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="closeMessage"
                        >
                            {{ $t('Close') }}
                        </button>
                        <button
                            :disabled="
                                !selected.email ||
                                sending ||
                                replyText.trim().length < 2
                            "
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="sendReply"
                        >
                            <Loader2
                                v-if="sending"
                                class="h-4 w-4 animate-spin"
                            />
                            <Send v-else class="h-4 w-4" />
                            {{ $t('Send reply') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
