<script setup lang="ts">
import { useStorefrontMenu } from '@/composables/useStorefrontMenu';
import { useThemeSettings } from '@/composables/useThemeSettings';
import { Link } from '@inertiajs/vue3';
import { CreditCard, Facebook, Instagram, Twitter } from 'lucide-vue-next';
import { computed } from 'vue';
import AppDownloadBlock from './AppDownloadBlock.vue';
import WhatsAppButton from './WhatsAppButton.vue';

interface Props {
    theme?: any;
    siteConfig?: {
        name: string;
        url: string;
        description: string;
    };
    containerStyle?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    theme: null,
    siteConfig: () => ({
        name: 'Akbari Development Group',
        url: '/',
        description: 'E-commerce Platform',
    }),
    containerStyle: () => ({}),
});

const currentYear = new Date().getFullYear();
const { menus, loading, getMenuUrl } = useStorefrontMenu();
const { showPlatformBranding, accent } = useThemeSettings();

const footerSections = computed(() => {
    if (!menus.value.footer || menus.value.footer.length === 0) {
        return [];
    }

    return menus.value.footer.filter(
        (item) => item.children && item.children.length > 0,
    );
});
</script>

<template>
    <footer class="bg-slate-900 py-12 text-slate-300">
        <div class="mx-auto px-4 sm:px-6 lg:px-8" :style="containerStyle">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-4">
                <div>
                    <h3 class="mb-4 text-xl font-bold text-white">
                        {{ siteConfig.name }}
                    </h3>
                    <p class="mb-4 text-sm leading-relaxed">
                        {{ siteConfig.description }}
                    </p>
                    <div class="flex items-center gap-3">
                        <a
                            href="#"
                            class="rounded-full bg-slate-800 p-2 transition-colors hover:bg-blue-600"
                            aria-label="Facebook"
                        >
                            <Facebook class="h-4 w-4" />
                        </a>
                        <a
                            href="#"
                            class="rounded-full bg-slate-800 p-2 transition-colors hover:bg-blue-600"
                            aria-label="Instagram"
                        >
                            <Instagram class="h-4 w-4" />
                        </a>
                        <a
                            href="#"
                            class="rounded-full bg-slate-800 p-2 transition-colors hover:bg-blue-600"
                            aria-label="Twitter"
                        >
                            <Twitter class="h-4 w-4" />
                        </a>
                        <WhatsAppButton variant="icon" />
                    </div>

                    <AppDownloadBlock compact tone="dark" class="mt-6" />
                </div>

                <template v-if="!loading && footerSections.length > 0">
                    <div v-for="section in footerSections" :key="section.id">
                        <h4 class="mb-4 font-semibold text-white">
                            {{ $t(section.title) }}
                        </h4>
                        <ul class="space-y-2 text-sm">
                            <li
                                v-for="child in section.children"
                                :key="child.id"
                            >
                                <Link
                                    :href="getMenuUrl(child)"
                                    class="transition-colors hover:text-white"
                                >
                                    {{ $t(child.title) }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>

                <template v-else>
                    <div>
                        <h4 class="mb-4 font-semibold text-white">
                            {{ $t('Company') }}
                        </h4>
                        <ul class="space-y-2 text-sm">
                            <li>
                                <Link
                                    href="/about-us"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('About Us') }}</Link
                                >
                            </li>
                            <li>
                                <Link
                                    href="/careers"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('Careers') }}</Link
                                >
                            </li>
                            <li>
                                <Link
                                    href="/contact-us"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('Contact') }}</Link
                                >
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="mb-4 font-semibold text-white">
                            {{ $t('Customer Service') }}
                        </h4>
                        <ul class="space-y-2 text-sm">
                            <li>
                                <Link
                                    href="/help"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('Help Center') }}</Link
                                >
                            </li>
                            <li>
                                <Link
                                    href="/shipping-and-returns"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('Shipping & Returns') }}</Link
                                >
                            </li>
                            <li>
                                <Link
                                    href="/checkout/track-order"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('Track Order') }}</Link
                                >
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="mb-4 font-semibold text-white">
                            {{ $t('Legal') }}
                        </h4>
                        <ul class="space-y-2 text-sm">
                            <li>
                                <Link
                                    href="/privacy-policy"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('Privacy Policy') }}</Link
                                >
                            </li>
                            <li>
                                <Link
                                    href="/terms-and-conditions"
                                    class="transition-colors hover:text-white"
                                    >{{ $t('Terms of Service') }}</Link
                                >
                            </li>
                        </ul>
                    </div>
                </template>
            </div>

            <div
                class="mt-8 flex flex-col items-center justify-between gap-4 border-t border-slate-800 pt-6 md:flex-row"
            >
                <div class="flex items-center gap-2 text-slate-500">
                    <CreditCard class="h-4 w-4" :style="{ color: accent }" />
                    <span class="text-xs"
                        >Visa · Mastercard · PayPal · Stripe</span
                    >
                </div>
                <div class="text-center text-sm">
                    <p>
                        &copy;
                        {{
                            $t('{year} {site}. All rights reserved.', {
                                year: currentYear,
                                site: siteConfig.name,
                            })
                        }}
                    </p>
                    <p v-if="showPlatformBranding" class="mt-1 text-slate-500">
                        {{ $t('Powered by') }}
                        <span class="font-medium text-slate-400"
                            >Akbari Development Group</span
                        >
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>
