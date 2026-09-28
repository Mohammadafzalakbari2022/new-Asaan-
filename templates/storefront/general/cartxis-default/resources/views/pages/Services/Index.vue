<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';
import ServiceCard from '../../components/ServiceCard.vue';


interface Service {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    price: string | number;
    price_unit: string;
    price_display: string;
    duration_display: string | null;
    image_url: string | null;
    featured: boolean;
    category?: { id: number; name: string; slug: string } | null;
}

interface ServiceCategory {
    id: number;
    name: string;
    slug: string;
    services_count: number;
}

interface Settings {
    coverage_note: string | null;
    contact_phone: string | null;
    contact_whatsapp: string | null;
}

const props = defineProps<{
    services: {
        data: Service[];
        links?: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: ServiceCategory[];
    featured: Service[];
    filters: { search: string | null; category: string | null; sort: string | null };
    settings: Settings;
}>();

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');
const sort = ref(props.filters.sort ?? '');

let debounce: ReturnType<typeof setTimeout> | undefined;

watch([search, category, sort], () => {
    clearTimeout(debounce);

    // The list is already the answer, so the URL is updated as they type rather
    // than making them press anything.
    debounce = setTimeout(() => {
        router.get(
            '/services',
            {
                search: search.value || undefined,
                category: category.value || undefined,
                sort: sort.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
});

const heading = computed(() => {
    const active = props.categories.find((c) => c.slug === category.value);

    return active ? active.name : 'Services';
});
</script>

<template>
    <Head :title="heading" />

    <ThemeLayout>
        <div class="bg-gray-50 border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <nav class="flex items-center space-x-2 text-sm text-gray-500">
                    <Link href="/" class="hover:text-gray-700">{{ 'Home' }}</Link>
                    <span>/</span>
                    <span class="text-gray-700">{{ heading }}</span>
                </nav>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="max-w-2xl">
                <h1 class="text-3xl font-bold text-gray-900">{{ heading }}</h1>
                <p v-if="settings.coverage_note" class="mt-2 text-gray-600">
                    {{ settings.coverage_note }}
                </p>
            </div>

            <div v-if="featured.length" class="mt-10">
                <h2 class="text-lg font-semibold text-gray-900">{{ 'Popular' }}</h2>
                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <ServiceCard v-for="service in featured" :key="service.id" :service="service" />
                </div>
            </div>

            <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-4">
                <aside class="lg:col-span-1">
                    <form class="space-y-5" @submit.prevent>
                        <div>
                            <label for="service-search" class="block text-sm font-medium text-gray-700">
                                {{ 'Search' }}
                            </label>
                            <input
                                id="service-search"
                                v-model="search"
                                type="search"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                placeholder="Search services"
                            />
                        </div>

                        <div v-if="categories.length">
                            <p class="text-sm font-medium text-gray-700">{{ 'Categories' }}</p>
                            <ul class="mt-2 space-y-1">
                                <li>
                                    <button
                                        type="button"
                                        class="text-left text-sm hover:underline"
                                        :class="category === '' ? 'font-semibold text-gray-900' : 'text-gray-600'"
                                        @click="category = ''"
                                    >
                                        {{ 'All' }}
                                    </button>
                                </li>
                                <li v-for="item in categories" :key="item.id">
                                    <button
                                        type="button"
                                        class="text-left text-sm hover:underline"
                                        :class="category === item.slug ? 'font-semibold text-gray-900' : 'text-gray-600'"
                                        @click="category = item.slug"
                                    >
                                        {{ item.name }}
                                        <span class="text-gray-400">({{ item.services_count }})</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </form>
                </aside>

                <div class="lg:col-span-3">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm text-gray-600">
                            {{ services.total }} {{ services.total === 1 ? 'service' : 'services' }}
                        </p>

                        <div>
                            <label for="service-sort" class="sr-only">{{ 'Sort' }}</label>
                            <select
                                id="service-sort"
                                v-model="sort"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                            >
                                <option value="">{{ 'Sort' }}</option>
                                <option value="price">{{ 'Price: low to high' }}</option>
                            </select>
                        </div>
                    </div>

                    <div
                        v-if="services.data.length"
                        class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <ServiceCard v-for="service in services.data" :key="service.id" :service="service" />
                    </div>

                    <div v-else class="mt-5 rounded-lg border border-dashed border-gray-300 p-10 text-center">
                        <p class="text-gray-600">{{ 'No services found.' }}</p>
                        <button type="button" class="mt-3 text-sm text-gray-900 underline" @click="search = ''; category = ''">
                            {{ 'Clear filters' }}
                        </button>
                    </div>

                    <nav v-if="services.last_page > 1" class="mt-8 flex flex-wrap gap-2">
                        <Link
                            v-for="link in services.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            class="rounded-md border px-3 py-1.5 text-sm"
                            :class="link.active ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </ThemeLayout>
</template>
