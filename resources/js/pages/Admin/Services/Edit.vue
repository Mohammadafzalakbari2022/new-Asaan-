<script setup lang="ts">
import ServiceEditor from './Create.vue';

/*
 * The form for a service is the same whether it is new or being changed, so it
 * is written once and used twice. The editor works out which it is from whether
 * a service was passed to it.
 */
defineProps<{
  service: {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    service_category_id: number | null;
    price: string;
    price_unit: string;
    price_note: string | null;
    duration_minutes: number | null;
    duration_label: string | null;
    service_area: string | null;
    includes: string[] | null;
    excludes: string[] | null;
    icon: string | null;
    image: string | null;
    image_url: string | null;
    icon_only: boolean;
    status: string;
    featured: boolean;
    booking_enabled: boolean;
    sort_order: number;
    meta_title: string | null;
    meta_description: string | null;
  };
  categories: { id: number; name: string }[];
  units: Record<string, string>;
}>();
</script>

<template>
  <ServiceEditor :service="service" :categories="categories" :units="units" />
</template>
