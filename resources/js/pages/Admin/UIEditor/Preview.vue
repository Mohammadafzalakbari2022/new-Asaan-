<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import UIBlockRenderer from '@/components/UIEditor/UIBlockRenderer.vue'
import { useI18nStore } from '@/Stores/i18n'

const props = defineProps<{
  page: { id: number | null; title: string } | null
  pageType?: 'cms_page' | 'homepage'
  layoutData: Record<string, unknown> | null
}>()

const { t } = useI18nStore()
</script>

<template>
  <Head :title="t('Preview — {title}', { title: page?.title ?? t('Page') })" />
  <div class="uie-preview-root">
    <UIBlockRenderer :layout="layoutData" :editor-mode="false" />
  </div>
</template>

<style>
html, body { margin: 0; padding: 0; }
.uie-preview-root { min-height: 100vh; }
</style>
