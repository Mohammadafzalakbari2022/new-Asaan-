<script setup lang="ts">
import CategoryEditor from './Create.vue';

/*
 * Same form as a new category; the editor works out which it is from whether a
 * category was passed to it.
 */
defineProps<{
  category: {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    status: string;
    show_in_menu: boolean;
    sort_order: number;
    image: string | null;
    image_url: string | null;
  };
  categories: { id: number; name: string }[];
}>();
</script>

<template>
  <CategoryEditor :category="category" :categories="categories" />
</template>
