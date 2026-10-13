<script setup lang="ts">
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Gift } from 'lucide-vue-next';
import { computed } from 'vue';
import AppDownloadBlock from '../../components/AppDownloadBlock.vue';
import WhatsAppButton from '../../components/WhatsAppButton.vue';
import ThemeLayout from '../../layouts/ThemeLayout.vue';

interface OrderItem {
    id: number;
    order_number: string;
    status: string;
    total: number;
    created_at: string;
    items_count: number;
}

interface Props {
    recentOrders: OrderItem[];
}

const props = defineProps<Props>();
const page = usePage();
const user = (page.props.auth as any)?.user;
const referralEnabled = computed(() => page.props.referralEnabled === true);

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        shipped: 'bg-purple-100 text-purple-800',
        delivered: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
    }).format(price);
};
</script>

<template>
    <ThemeLayout>
        <Head :title="$t('My Account Dashboard')" />

        <div class="container mx-auto px-4 py-8">
            <div class="mx-auto max-w-7xl">
                <!-- Welcome Header -->
                <div
                    class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="mb-2 text-3xl font-bold">
                            {{
                                $t('Welcome back, {name}!', {
                                    name: user?.name || $t('Customer'),
                                })
                            }}
                        </h1>
                        <p class="text-gray-600 dark:text-slate-400">
                            {{
                                $t(
                                    'Manage your orders, profile, and account settings',
                                )
                            }}
                        </p>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="inline-flex items-center rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-950/40"
                    >
                        {{ $t('Logout') }}
                    </Link>
                </div>

                <!-- Quick Stats Cards -->
                <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-4">
                    <!-- Total Orders -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="mb-1 text-sm text-gray-600 dark:text-slate-400"
                                >
                                    {{ $t('Total Orders') }}
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ recentOrders.length }}
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-950/40"
                            >
                                <svg
                                    class="h-6 w-6 text-blue-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Orders -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="mb-1 text-sm text-gray-600 dark:text-slate-400"
                                >
                                    {{ $t('Pending') }}
                                </p>
                                <p class="text-2xl font-bold">
                                    {{
                                        recentOrders.filter(
                                            (o) =>
                                                o.status === 'pending' ||
                                                o.status === 'processing',
                                        ).length
                                    }}
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-950/40"
                            >
                                <svg
                                    class="h-6 w-6 text-yellow-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Delivered Orders -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="mb-1 text-sm text-gray-600 dark:text-slate-400"
                                >
                                    {{ $t('Delivered') }}
                                </p>
                                <p class="text-2xl font-bold">
                                    {{
                                        recentOrders.filter(
                                            (o) => o.status === 'delivered',
                                        ).length
                                    }}
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-950/40"
                            >
                                <svg
                                    class="h-6 w-6 text-green-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="mb-1 text-sm text-gray-600 dark:text-slate-400"
                                >
                                    {{ $t('Account') }}
                                </p>
                                <p class="text-sm font-semibold text-green-600">
                                    {{ $t('Active') }}
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-950/40"
                            >
                                <svg
                                    class="h-6 w-6 text-green-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Recent Orders Section -->
                    <div class="lg:col-span-2">
                        <div
                            class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div
                                class="border-b border-gray-200 p-6 dark:border-slate-700"
                            >
                                <div class="flex items-center justify-between">
                                    <h2 class="text-xl font-semibold">
                                        {{ $t('Recent Orders') }}
                                    </h2>
                                    <Link
                                        href="/account/orders"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300"
                                    >
                                        {{ $t('View All') }}
                                    </Link>
                                </div>
                            </div>

                            <div
                                v-if="recentOrders.length > 0"
                                class="divide-y divide-gray-200 dark:divide-slate-700"
                            >
                                <div
                                    v-for="order in recentOrders"
                                    :key="order.id"
                                    class="p-6 transition-colors hover:bg-gray-50 dark:hover:bg-slate-800"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span class="font-semibold">{{
                                                $t('Order #{number}', {
                                                    number: order.order_number,
                                                })
                                            }}</span>
                                            <span
                                                :class="[
                                                    'rounded-full px-2 py-1 text-xs font-medium',
                                                    getStatusColor(
                                                        order.status,
                                                    ),
                                                ]"
                                            >
                                                {{
                                                    $t(
                                                        order.status
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                            order.status.slice(
                                                                1,
                                                            ),
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <DateDisplay
                                            :value="order.created_at"
                                            class="text-sm text-gray-600 dark:text-slate-400"
                                        />
                                    </div>
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            class="text-sm text-gray-600 dark:text-slate-400"
                                        >
                                            {{
                                                $t(
                                                    order.items_count === 1
                                                        ? '{count} item'
                                                        : '{count} items',
                                                    {
                                                        count: order.items_count,
                                                    },
                                                )
                                            }}
                                            • {{ formatPrice(order.total) }}
                                        </div>
                                        <div class="flex gap-2">
                                            <Link
                                                :href="`/account/orders/${order.id}`"
                                                class="rounded bg-blue-600 px-3 py-1.5 text-sm text-white transition-colors hover:bg-blue-700"
                                            >
                                                {{ $t('View Details') }}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="p-12 text-center">
                                <div
                                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-slate-800"
                                >
                                    <svg
                                        class="h-8 w-8 text-gray-400 dark:text-slate-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                                        />
                                    </svg>
                                </div>
                                <h3 class="mb-2 text-lg font-semibold">
                                    {{ $t('No orders yet') }}
                                </h3>
                                <p
                                    class="mb-4 text-gray-600 dark:text-slate-400"
                                >
                                    {{
                                        $t(
                                            'Start shopping to see your orders here',
                                        )
                                    }}
                                </p>
                                <Link
                                    href="/"
                                    class="inline-block rounded-lg bg-blue-600 px-6 py-2 text-white transition-colors hover:bg-blue-700"
                                >
                                    {{ $t('Start Shopping') }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Sidebar -->
                    <div class="space-y-6">
                        <!-- Account Quick Actions -->
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <h3 class="mb-4 text-lg font-semibold">
                                {{ $t('Quick Actions') }}
                            </h3>
                            <div class="space-y-3">
                                <Link
                                    href="/account/orders"
                                    class="group flex items-center gap-3 rounded-lg p-3 transition-colors hover:bg-gray-50 dark:hover:bg-slate-800"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 transition-colors group-hover:bg-blue-200 dark:bg-blue-950/40"
                                    >
                                        <svg
                                            class="h-5 w-5 text-blue-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">
                                            {{ $t('My Orders') }}
                                        </div>
                                        <div
                                            class="text-xs text-gray-600 dark:text-slate-400"
                                        >
                                            {{ $t('Track & manage orders') }}
                                        </div>
                                    </div>
                                </Link>

                                <Link
                                    href="/"
                                    class="group flex items-center gap-3 rounded-lg p-3 transition-colors hover:bg-gray-50 dark:hover:bg-slate-800"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 transition-colors group-hover:bg-green-200 dark:bg-green-950/40"
                                    >
                                        <svg
                                            class="h-5 w-5 text-green-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">
                                            {{ $t('Continue Shopping') }}
                                        </div>
                                        <div
                                            class="text-xs text-gray-600 dark:text-slate-400"
                                        >
                                            {{ $t('Browse Products') }}
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>

                        <!-- Account Info Card -->
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <h3 class="mb-4 text-lg font-semibold">
                                {{ $t('Account Information') }}
                            </h3>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <p
                                        class="text-gray-600 dark:text-slate-400"
                                    >
                                        {{ $t('Name') }}
                                    </p>
                                    <p class="font-medium">
                                        {{ user?.name || 'N/A' }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-gray-600 dark:text-slate-400"
                                    >
                                        {{ $t('Email') }}
                                    </p>
                                    <p class="font-medium">
                                        {{ user?.email || 'N/A' }}
                                    </p>
                                </div>
                                <div
                                    class="border-t border-gray-200 pt-3 dark:border-slate-700"
                                >
                                    <Link
                                        href="/account/profile"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300"
                                    >
                                        {{ $t('Edit Profile →') }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Referral Programme -->
                        <div
                            v-if="referralEnabled"
                            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <h3
                                class="mb-2 flex items-center gap-2 text-lg font-semibold"
                            >
                                <Gift class="h-5 w-5 text-green-600" />
                                {{ $t('Refer & Earn') }}
                            </h3>
                            <p
                                class="mb-4 text-sm text-gray-600 dark:text-slate-400"
                            >
                                {{
                                    $t(
                                        'Invite a friend and earn store credit when they shop with us.',
                                    )
                                }}
                            </p>
                            <Link
                                href="/account/referrals"
                                class="inline-block w-full rounded-lg bg-green-600 px-4 py-2 text-center text-sm font-medium text-white transition-colors hover:bg-green-700"
                            >
                                {{ $t('Get my referral link') }}
                            </Link>
                        </div>

                        <!-- Help & Support -->
                        <div
                            class="rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-950/40"
                        >
                            <h3
                                class="mb-2 text-lg font-semibold text-blue-900 dark:text-blue-100"
                            >
                                {{ $t('Need Help?') }}
                            </h3>
                            <p
                                class="mb-4 text-sm text-blue-800 dark:text-blue-200"
                            >
                                {{
                                    $t(
                                        'Our support team is here to assist you with any questions.',
                                    )
                                }}
                            </p>
                            <a
                                href="/contact"
                                class="inline-block w-full rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white transition-colors hover:bg-blue-700"
                            >
                                {{ $t('Contact Support') }}
                            </a>
                            <p
                                class="mt-4 mb-2 text-sm text-blue-800 dark:text-blue-200"
                            >
                                {{ $t('Need help? Chat on WhatsApp') }}
                            </p>
                            <WhatsAppButton variant="button" block />
                        </div>

                        <!-- Download the app -->
                        <AppDownloadBlock />
                    </div>
                </div>
            </div>
        </div>
    </ThemeLayout>
</template>
