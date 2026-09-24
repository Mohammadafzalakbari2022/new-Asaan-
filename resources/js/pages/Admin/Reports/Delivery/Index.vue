<script setup lang="ts">
import AdminPagination from '@/components/Admin/Pagination.vue';
import { useCurrency } from '@/composables/useCurrency';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import {
    Banknote,
    Calendar,
    CheckCircle2,
    ChevronDown,
    Download,
    Filter,
    PhoneCall,
    TrendingUp,
    Truck,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Bar } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler,
);

interface StatusBadge {
    label: string;
    class: string;
}

interface Statistics {
    total: number;
    delivered: number;
    undelivered: number;
    cancelled: number;
    on_road: number;
    cod_expected: number;
    cod_collected: number;
    delivery_rate: number | null;
}

interface DriverStats {
    driver: { id: number; name: string; phone: string | null } | null;
    total: number;
    delivered: number;
    undelivered: number;
    cancelled: number;
    on_road: number;
    cod_expected: number;
    cod_collected: number;
    delivery_rate: number | null;
}

interface ChartPoint {
    date: string;
    created: number;
    delivered: number;
}

interface DeliveryRow {
    id: number;
    order_number: string | null;
    shipment_number: string | null;
    driver: { id: number; name: string } | null;
    status: string;
    status_badge: StatusBadge;
    priority: string;
    customer_phone: string | null;
    cod_amount: number | null;
    cod_received: number | null;
    recipient_name: string | null;
    assigned_at: string | null;
    scheduled_date: string | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface Filters {
    start_date: string;
    end_date: string;
    driver_id: number | null;
    status: string | null;
}

interface Props {
    statistics: Statistics;
    perDriver: DriverStats[];
    chart: ChartPoint[];
    deliveries: Paginator<DeliveryRow>;
    filters: Filters;
    drivers: { id: number; name: string; phone: string | null }[];
    statuses: { value: string; label: string }[];
}

const props = defineProps<Props>();

const { formatPrice } = useCurrency();

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const driverFilter = ref<number | ''>(props.filters.driver_id ?? '');
const statusFilter = ref<string>(props.filters.status ?? '');

const hasFilters = computed(
    () =>
        !!startDate.value ||
        !!endDate.value ||
        driverFilter.value !== '' ||
        !!statusFilter.value,
);
const unresolved = computed(
    () =>
        props.statistics.total -
        props.statistics.delivered -
        props.statistics.undelivered -
        props.statistics.cancelled,
);

const chartData = computed(() => ({
    labels: props.chart.map((d) => d.date.slice(5)),
    datasets: [
        {
            label: 'Created',
            data: props.chart.map((d) => d.created),
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderRadius: 4,
        },
        {
            label: 'Delivered',
            data: props.chart.map((d) => d.delivered),
            backgroundColor: 'rgba(22, 163, 74, 0.7)',
            borderRadius: 4,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top' as const },
        tooltip: { mode: 'index' as const, intersect: false },
    },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { ticks: { maxTicksLimit: 12 } },
    },
};

const applyFilters = () => {
    router.get(
        '/admin/reports/delivery',
        {
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
            driver_id:
                driverFilter.value !== '' ? driverFilter.value : undefined,
            status: statusFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    startDate.value = '';
    endDate.value = '';
    driverFilter.value = '';
    statusFilter.value = '';
    applyFilters();
};

const exportCsv = () => {
    const params = new URLSearchParams();
    if (startDate.value) params.set('start_date', startDate.value);
    if (endDate.value) params.set('end_date', endDate.value);
    if (driverFilter.value !== '')
        params.set('driver_id', String(driverFilter.value));
    if (statusFilter.value) params.set('status', statusFilter.value);

    const qs = params.toString();
    window.location.href = `/admin/reports/delivery/export${qs ? `?${qs}` : ''}`;
};

const rate = (value: number | null) =>
    value !== null ? `${value.toFixed(1)}%` : '—';
</script>

<template>
    <Head title="Delivery Reports" />
    <AdminLayout title="Delivery Reports">
        <div class="space-y-6">
            <!-- Header -->
            <div
                class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        Delivery Reports
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Track delivery performance, drivers, and COD collection
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="exportCsv"
                        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <Download :size="16" />
                        Export CSV
                    </button>
                    <button
                        @click="router.reload()"
                        class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        title="Refresh"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div
                class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5"
                >
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >Start Date</label
                        >
                        <div class="relative">
                            <Calendar
                                :size="16"
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400"
                            />
                            <input
                                v-model="startDate"
                                type="date"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pr-4 pl-10 text-sm transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700/50"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >End Date</label
                        >
                        <div class="relative">
                            <Calendar
                                :size="16"
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400"
                            />
                            <input
                                v-model="endDate"
                                type="date"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pr-4 pl-10 text-sm transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700/50"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >Driver</label
                        >
                        <div class="relative">
                            <Filter
                                :size="16"
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400"
                            />
                            <select
                                v-model="driverFilter"
                                class="w-full appearance-none rounded-lg border border-gray-200 bg-gray-50 py-2.5 pr-8 pl-10 text-sm transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700/50"
                            >
                                <option value="">All Drivers</option>
                                <option
                                    v-for="driver in drivers"
                                    :key="driver.id"
                                    :value="driver.id"
                                >
                                    {{ driver.name }}
                                </option>
                            </select>
                            <ChevronDown
                                :size="16"
                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-gray-400"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >Status</label
                        >
                        <div class="relative">
                            <Filter
                                :size="16"
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400"
                            />
                            <select
                                v-model="statusFilter"
                                class="w-full appearance-none rounded-lg border border-gray-200 bg-gray-50 py-2.5 pr-8 pl-10 text-sm transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700/50"
                            >
                                <option value="">All Statuses</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                            <ChevronDown
                                :size="16"
                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-gray-400"
                            />
                        </div>
                    </div>

                    <div class="flex items-end gap-2">
                        <button
                            @click="applyFilters"
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 font-medium text-white shadow-sm shadow-blue-600/20 transition-all hover:bg-blue-700 focus:ring-4 focus:ring-blue-500/20"
                        >
                            Apply Filters
                        </button>
                        <button
                            v-if="hasFilters"
                            @click="clearFilters"
                            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="mb-1 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Total Deliveries
                            </p>
                            <h3
                                class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                            >
                                {{ statistics.total }}
                            </h3>
                            <p class="mt-2.5 text-xs text-gray-400">
                                {{ unresolved }} still on the road
                            </p>
                        </div>
                        <div
                            class="rounded-xl bg-blue-50 p-3 dark:bg-blue-900/20"
                        >
                            <Truck
                                :size="22"
                                class="text-blue-600 dark:text-blue-400"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="mb-1 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Delivered
                            </p>
                            <h3
                                class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                            >
                                {{ statistics.delivered }}
                            </h3>
                            <div class="mt-2.5 flex items-center">
                                <span
                                    class="flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    <TrendingUp :size="12" />
                                    {{ rate(statistics.delivery_rate) }} rate
                                </span>
                            </div>
                        </div>
                        <div
                            class="rounded-xl bg-green-50 p-3 dark:bg-green-900/20"
                        >
                            <CheckCircle2
                                :size="22"
                                class="text-green-600 dark:text-green-400"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="mb-1 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Undelivered
                            </p>
                            <h3
                                class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                            >
                                {{ statistics.undelivered }}
                                <span class="text-sm font-medium text-gray-400"
                                    >+
                                    {{ statistics.cancelled }} cancelled</span
                                >
                            </h3>
                            <p class="mt-2.5 text-xs text-gray-400">
                                Could not reach the customer
                            </p>
                        </div>
                        <div
                            class="rounded-xl bg-red-50 p-3 dark:bg-red-900/20"
                        >
                            <XCircle
                                :size="22"
                                class="text-red-600 dark:text-red-400"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="mb-1 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                COD Handling
                            </p>
                            <h3
                                class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                            >
                                {{ formatPrice(statistics.cod_collected) }}
                            </h3>
                            <p class="mt-2.5 text-xs text-gray-400">
                                of
                                {{ formatPrice(statistics.cod_expected) }}
                                expected
                            </p>
                        </div>
                        <div
                            class="rounded-xl bg-amber-50 p-3 dark:bg-amber-900/20"
                        >
                            <Banknote
                                :size="22"
                                class="text-amber-600 dark:text-amber-400"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Chart -->
            <div
                class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h2
                    class="mb-6 text-base font-semibold text-gray-900 dark:text-white"
                >
                    Deliveries Over Time
                </h2>
                <div class="relative h-72 w-full">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Per-Driver Table -->
            <div
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-700/20"
                >
                    <h2
                        class="text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Per-Driver Performance
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Driver
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Delivered
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Undelivered
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Cancelled
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    On Road
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Delivery Rate
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <tr
                                v-for="row in perDriver"
                                :key="row.driver?.id ?? 'unassigned'"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-6 py-3.5 whitespace-nowrap">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ row.driver?.name ?? 'Unassigned' }}
                                    </div>
                                    <div
                                        v-if="row.driver?.phone"
                                        class="mt-0.5 flex items-center gap-1 text-xs text-gray-400"
                                    >
                                        <PhoneCall :size="12" />
                                        {{ row.driver.phone }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm whitespace-nowrap text-gray-700 dark:text-gray-300"
                                >
                                    {{ row.total }}
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm font-medium whitespace-nowrap text-green-600 dark:text-green-400"
                                >
                                    {{ row.delivered }}
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm font-medium whitespace-nowrap text-red-600 dark:text-red-400"
                                >
                                    {{ row.undelivered }}
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{ row.cancelled }}
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm font-medium whitespace-nowrap text-blue-600 dark:text-blue-400"
                                >
                                    {{ row.on_road }}
                                </td>
                                <td
                                    class="px-6 py-3.5 text-right whitespace-nowrap"
                                >
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            (row.delivery_rate ?? 0) >= 70
                                                ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        ]"
                                    >
                                        {{ rate(row.delivery_rate) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Deliveries Table -->
            <div
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-700/20"
                >
                    <h2
                        class="text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Deliveries
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Shipment
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Order
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Driver
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Recipient
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    COD
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Scheduled
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <tr
                                v-for="item in deliveries.data"
                                :key="item.id"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td
                                    class="px-6 py-3.5 text-sm font-semibold whitespace-nowrap text-blue-600 dark:text-blue-400"
                                >
                                    {{ item.shipment_number ?? '—' }}
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm whitespace-nowrap text-gray-700 dark:text-gray-300"
                                >
                                    {{ item.order_number ?? '—' }}
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm whitespace-nowrap text-gray-700 dark:text-gray-300"
                                >
                                    {{ item.driver?.name ?? 'Unassigned' }}
                                </td>
                                <td class="px-6 py-3.5 whitespace-nowrap">
                                    <div
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ item.recipient_name ?? '—' }}
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center gap-1 text-xs text-gray-400"
                                    >
                                        <PhoneCall :size="12" />
                                        {{ item.customer_phone ?? '—' }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-3.5 text-right whitespace-nowrap"
                                >
                                    <div
                                        class="text-sm font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{ formatPrice(item.cod_amount ?? 0) }}
                                    </div>
                                    <div
                                        v-if="item.status === 'delivered'"
                                        class="text-xs text-green-600 dark:text-green-400"
                                    >
                                        collected
                                        {{
                                            formatPrice(item.cod_received ?? 0)
                                        }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-3.5 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        item.scheduled_date
                                            ? String(item.scheduled_date).slice(
                                                  0,
                                                  16,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td class="px-6 py-3.5 whitespace-nowrap">
                                    <span
                                        v-if="item.status_badge"
                                        :class="item.status_badge.class"
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    >
                                        {{ item.status_badge.label }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="deliveries.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-6 py-14 text-center text-sm text-gray-400"
                                >
                                    No deliveries match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <AdminPagination
                    :data="deliveries"
                    resource-name="deliveries"
                />
            </div>
        </div>
    </AdminLayout>
</template>
