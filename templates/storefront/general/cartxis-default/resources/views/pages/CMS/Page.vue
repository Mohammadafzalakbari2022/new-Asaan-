<script setup lang="ts">
import UIBlockRenderer from '@/components/UIEditor/UIBlockRenderer.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ContactForm from '../../components/ContactForm.vue';
import ThemeLayout from '../../layouts/ThemeLayout.vue';

interface Props {
    page: {
        title: string;
        content: string;
        meta_title?: string;
        meta_description?: string;
        meta_keywords?: string;
        url_key: string;
        created_at?: string;
        updated_at?: string;
    };
    theme: {
        name: string;
        slug: string;
        settings: {
            primary_color: string;
            secondary_color: string;
            features: Record<string, boolean>;
        };
    };
    siteConfig: {
        name: string;
        url: string;
        description: string;
    };
    layoutData?: Record<string, unknown> | null;
}

const props = defineProps<Props>();
const hasLayout = computed(
    () =>
        !!(
            props.layoutData?.sections &&
            (props.layoutData.sections as unknown[]).length
        ),
);
// Only the contact page gets the message form. "Contact" is matched in the url
// key so a store that names it "contact" or "contact-us" both work.
const isContactPage = computed(() =>
    (props.page.url_key || '').toLowerCase().includes('contact'),
);
</script>

<template>
    <ThemeLayout>
        <Head>
            <title>{{ page.meta_title || page.title }}</title>
            <meta
                v-if="page.meta_description"
                name="description"
                :content="page.meta_description"
            />
            <meta
                v-if="page.meta_keywords"
                name="keywords"
                :content="page.meta_keywords"
            />
        </Head>

        <div class="min-h-screen bg-gray-50 dark:bg-slate-900">
            <!-- Page Content -->
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <!-- Visual Editor layout takes priority when published -->
                <template v-if="hasLayout">
                    <UIBlockRenderer
                        :layout="layoutData"
                        :editor-mode="false"
                    />
                </template>
                <template v-else>
                    <div
                        class="rounded-lg bg-white p-8 shadow-sm dark:bg-slate-900"
                    >
                        <div
                            v-html="page.content"
                            class="prose prose-lg prose-headings:text-gray-900 prose-h1:text-3xl prose-h1:font-bold prose-h1:mb-4 prose-h2:text-2xl prose-h2:font-bold prose-h2:mt-8 prose-h2:mb-4 prose-h3:text-xl prose-h3:font-semibold prose-h3:mt-6 prose-h3:mb-3 prose-p:text-gray-700 prose-p:leading-relaxed prose-p:mb-4 prose-a:text-indigo-600 prose-a:no-underline hover:prose-a:underline prose-ul:list-disc prose-ul:pl-6 prose-ul:mb-4 prose-ol:list-decimal prose-ol:pl-6 prose-ol:mb-4 prose-li:text-gray-700 prose-li:mb-2 prose-strong:text-gray-900 prose-strong:font-semibold prose-blockquote:border-l-4 prose-blockquote:border-indigo-500 prose-blockquote:pl-4 prose-blockquote:italic prose-blockquote:text-gray-600 max-w-none"
                        ></div>
                    </div>
                </template>

                <!-- Contact messages go to the dashboard inbox (free). -->
                <ContactForm v-if="isContactPage" />
            </div>
        </div>
    </ThemeLayout>
</template>

<style scoped>
/* Additional custom styles for CMS content if needed */
</style>
