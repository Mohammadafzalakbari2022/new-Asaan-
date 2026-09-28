<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';

const { formatPrice } = useCurrency();

interface ServiceCategory {
    id: number;
    name: string;
    slug: string;
}

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
    category?: ServiceCategory | null;
}

const props = defineProps<{
    service: Service;
}>();

const price = computed(() =>
    typeof props.service.price === 'string'
        ? parseFloat(props.service.price)
        : props.service.price,
);
</script>

<template>
    <Link
        :href="`/services/${service.slug}`"
        class="group flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-gray-300 hover:shadow-md"
    >
        <div class="relative aspect-[4/3] w-full overflow-hidden bg-gray-100">
            <img
                v-if="service.image_url"
                :src="service.image_url"
                :alt="service.name"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                loading="lazy"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center text-gray-400"
                aria-hidden="true"
            >
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Zm0 0H7.5m-2.625 0h5.25m-9.75 0h9.75" />
                </svg>
            </div>

            <span
                v-if="service.featured"
                class="absolute left-3 top-3 rounded-full bg-gray-900 px-2.5 py-1 text-xs font-medium text-white"
            >
                Popular
            </span>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <p
                v-if="service.category"
                class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500"
            >
                {{ service.category.name }}
            </p>

            <h3 class="font-semibold text-gray-900 group-hover:text-gray-700">
                {{ service.name }}
            </h3>

            <p
                v-if="service.short_description"
                class="mt-1 line-clamp-2 text-sm text-gray-600"
            >
                {{ service.short_description }}
            </p>

            <p v-if="service.duration_display" class="mt-2 text-xs text-gray-500">
                Takes about {{ service.duration_display }}
            </p>

            <div class="mt-auto flex items-baseline gap-1 pt-3">
                <span class="text-lg font-semibold text-gray-900">
                    {{ formatPrice(price) }}
                </span>
                <span class="text-sm text-gray-500">{{ service.price_display.split(' ').slice(1).join(' ') }}</span>
            </div>
        </div>
    </Link>
</template>
