<script setup lang="ts">
import { ref, watch } from 'vue';
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
    description: string | null;
    services_count?: number;
}

const props = defineProps<{
    category: ServiceCategory;
    services: {
        data: Service[];
        links?: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    categories: ServiceCategory[];
    settings: { coverage_note: string | null; contact_phone: string | null };
}>();

const search = ref('');

let debounce: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(debounce);

    debounce = setTimeout(() => {
        router.get(
            `/services/category/${props.category.slug}`,
            { search: search.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head :title="category.name" />

    <ThemeLayout>
        <div class="bg-gray-50 border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <nav class="flex items-center space-x-2 text-sm text-gray-500">
                    <Link href="/" class="hover:text-gray-700">{{ 'Home' }}</Link>
                    <span>/</span>
                    <Link href="/services" class="hover:text-gray-700">{{ 'Services' }}</Link>
                    <span>/</span>
                    <span class="text-gray-700">{{ category.name }}</span>
                </nav>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <h1 class="text-3xl font-bold text-gray-900">{{ category.name }}</h1>
            <p v-if="category.description" class="mt-2 max-w-2xl text-gray-600">
                {{ category.description }}
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-gray-600">{{ services.total }} {{ services.total === 1 ? 'service' : 'services' }}</p>

                <div>
                    <label for="category-search" class="sr-only">{{ 'Search' }}</label>
                    <input
                        id="category-search"
                        v-model="search"
                        type="search"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                        placeholder="Search"
                    />
                </div>
            </div>

            <div
                v-if="services.data.length"
                class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ServiceCard v-for="service in services.data" :key="service.id" :service="service" />
            </div>

            <div v-else class="mt-6 rounded-lg border border-dashed border-gray-300 p-10 text-center">
                <p class="text-gray-600">{{ 'No services in this category yet.' }}</p>
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
    </ThemeLayout>
</template>
