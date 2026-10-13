<script setup lang="ts">
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useCurrency } from '@/composables/useCurrency';
import { useStorefrontMenu } from '@/composables/useStorefrontMenu';
import { useThemeSettings } from '@/composables/useThemeSettings';
import { useWishlist } from '@/composables/useWishlist';
import { useI18nStore } from '@/Stores/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BadgeCheck,
    ClipboardList,
    Heart,
    Home,
    LogOut,
    MapPin,
    Menu,
    Search,
    User,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CartIcon from './CartIcon.vue';
import CurrencySelector from './CurrencySelector.vue';
import WhatsAppButton from './WhatsAppButton.vue';

const i18n = useI18nStore();
const t = i18n.t;

interface SearchSuggestion {
    id: number;
    name: string;
    slug: string;
    price: number;
    image: string;
}

interface Props {
    theme?: any;
    siteConfig?: {
        name: string;
        url: string;
        description: string;
        logo?: string | null;
    };
    categories?: any[];
    containerStyle?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    theme: null,
    siteConfig: () => ({
        name: 'Akbari Development Group',
        url: '/',
        description: 'E-commerce Platform',
    }),
    categories: () => [],
    containerStyle: () => ({}),
});

const page = usePage();
const auth = computed(() => page.props.auth as any);
const user = computed(() => auth.value?.user);
const referralEnabled = computed(() => page.props.referralEnabled === true);
const identityEnabled = computed(() => page.props.identityEnabled === true);

const { wishlistCount, fetchWishlist } = useWishlist();
const { formatPrice } = useCurrency();
const { primary, stickyHeader, wishlistEnabled } = useThemeSettings();

/**
 * The pages behind "My Account", declared once and reused by the desktop
 * dropdown and the mobile drawer so the two cannot drift apart.
 */
const accountLinks = computed(() => {
    const links = [
        { href: '/account', label: 'Dashboard', icon: Home },
        { href: '/account/orders', label: 'My Orders', icon: ClipboardList },
        { href: '/account/profile', label: 'Profile', icon: User },
        { href: '/account/addresses', label: 'Addresses', icon: MapPin },
        { href: '/services', label: 'Services', icon: Users },
    ];

    if (wishlistEnabled.value) {
        links.push({
            href: '/account/wishlist',
            label: 'Wishlist',
            icon: Heart,
        });
    }

    if (referralEnabled.value) {
        links.push({
            href: '/account/referrals',
            label: 'Refer & Earn',
            icon: Users,
        });
    }

    return links;
});

const mobileMenuOpen = ref(false);
const mobileSearchOpen = ref(false);

const searchQuery = ref('');
const suggestions = ref<SearchSuggestion[]>([]);
const showSuggestions = ref(false);
const selectedIndex = ref(-1);
const searchInputRef = ref<HTMLInputElement | null>(null);
const isSearching = ref(false);
let debounceTimeout: ReturnType<typeof setTimeout> | null = null;

const { menus, loading, getMenuUrl, hasChildren } = useStorefrontMenu();
const activeDropdown = ref<number | null>(null);
const showUserMenu = ref(false);
const showCategoriesDropdown = ref(false);
let closeTimeout: ReturnType<typeof setTimeout> | null = null;
let userMenuTimeout: ReturnType<typeof setTimeout> | null = null;
let categoriesTimeout: ReturnType<typeof setTimeout> | null = null;

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
    mobileSearchOpen.value = false;
};

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

const toggleDropdown = (itemId: number) => {
    if (closeTimeout) {
        clearTimeout(closeTimeout);
        closeTimeout = null;
    }
    activeDropdown.value = activeDropdown.value === itemId ? null : itemId;
};

const openDropdown = (itemId: number) => {
    if (closeTimeout) {
        clearTimeout(closeTimeout);
        closeTimeout = null;
    }
    activeDropdown.value = itemId;
};

const closeDropdown = () => {
    if (closeTimeout) {
        clearTimeout(closeTimeout);
    }
    closeTimeout = setTimeout(() => {
        activeDropdown.value = null;
        closeTimeout = null;
    }, 150);
};

const toggleUserMenu = () => {
    if (userMenuTimeout) {
        clearTimeout(userMenuTimeout);
        userMenuTimeout = null;
    }
    showUserMenu.value = !showUserMenu.value;
};

const closeUserMenu = () => {
    if (userMenuTimeout) {
        clearTimeout(userMenuTimeout);
    }
    userMenuTimeout = setTimeout(() => {
        showUserMenu.value = false;
        userMenuTimeout = null;
    }, 150);
};

const openUserMenu = () => {
    if (userMenuTimeout) {
        clearTimeout(userMenuTimeout);
        userMenuTimeout = null;
    }
    showUserMenu.value = true;
};

const toggleCategoriesDropdown = () => {
    if (categoriesTimeout) {
        clearTimeout(categoriesTimeout);
        categoriesTimeout = null;
    }
    showCategoriesDropdown.value = !showCategoriesDropdown.value;
};

const closeCategoriesDropdown = () => {
    if (categoriesTimeout) {
        clearTimeout(categoriesTimeout);
    }
    categoriesTimeout = setTimeout(() => {
        showCategoriesDropdown.value = false;
        categoriesTimeout = null;
    }, 150);
};

const openCategoriesDropdown = () => {
    if (categoriesTimeout) {
        clearTimeout(categoriesTimeout);
        categoriesTimeout = null;
    }
    showCategoriesDropdown.value = true;
};

const fetchSuggestions = async () => {
    if (searchQuery.value.trim().length < 2) {
        suggestions.value = [];
        showSuggestions.value = false;
        isSearching.value = false;
        return;
    }

    try {
        isSearching.value = true;
        const response = await axios.get(
            `/search/suggestions?q=${encodeURIComponent(searchQuery.value.trim())}&limit=8`,
        );
        suggestions.value = response.data.suggestions || [];
        showSuggestions.value = suggestions.value.length > 0;
        selectedIndex.value = -1;
    } catch (error) {
        console.error('Error fetching search suggestions:', error);
        suggestions.value = [];
        showSuggestions.value = false;
    } finally {
        isSearching.value = false;
    }
};

const onSearchInput = () => {
    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }

    debounceTimeout = setTimeout(() => {
        fetchSuggestions();
    }, 300);
};

const handleSearch = (suggestion?: SearchSuggestion) => {
    if (suggestion) {
        window.location.href = `/product/${suggestion.slug}`;
    } else if (searchQuery.value.trim().length > 0) {
        window.location.href = `/search?q=${encodeURIComponent(searchQuery.value.trim())}`;
    }
    showSuggestions.value = false;
};

const handleKeyDown = (event: KeyboardEvent) => {
    if (!showSuggestions.value || suggestions.value.length === 0) return;

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            selectedIndex.value = Math.min(
                selectedIndex.value + 1,
                suggestions.value.length - 1,
            );
            break;
        case 'ArrowUp':
            event.preventDefault();
            selectedIndex.value = Math.max(selectedIndex.value - 1, -1);
            break;
        case 'Enter':
            event.preventDefault();
            if (
                selectedIndex.value >= 0 &&
                selectedIndex.value < suggestions.value.length
            ) {
                handleSearch(suggestions.value[selectedIndex.value]);
            } else {
                handleSearch();
            }
            break;
        case 'Escape':
            showSuggestions.value = false;
            selectedIndex.value = -1;
            break;
    }
};

const closeSuggestions = () => {
    setTimeout(() => {
        showSuggestions.value = false;
        selectedIndex.value = -1;
    }, 200);
};

const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (
        searchInputRef.value &&
        !searchInputRef.value.contains(target) &&
        !target.closest('.search-suggestions')
    ) {
        showSuggestions.value = false;
        selectedIndex.value = -1;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    if (user.value) {
        fetchWishlist();
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.body.style.overflow = '';
    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }
});
</script>

<template>
    <header
        class="z-50 bg-white shadow-sm dark:bg-slate-900"
        :class="{ 'sticky top-0': stickyHeader }"
    >
        <div class="mx-auto px-4 sm:px-6 lg:px-8" :style="containerStyle">
            <div class="flex h-16 items-center justify-between gap-3">
                <!-- Mobile menu -->
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-700 hover:bg-slate-100 md:hidden dark:hover:bg-slate-800"
                    :aria-label="$t('Open menu')"
                    @click="mobileMenuOpen = true"
                >
                    <Menu class="h-6 w-6" />
                </button>

                <!-- Logo -->
                <div class="flex flex-1 items-center md:flex-none">
                    <Link
                        href="/"
                        class="flex items-center"
                        @click="closeMobileMenu"
                    >
                        <img
                            v-if="siteConfig.logo"
                            :src="`/storage/${siteConfig.logo}`"
                            :alt="siteConfig.name"
                            class="h-10 object-contain"
                            :style="{ maxWidth: '200px' }"
                        />
                        <h1
                            v-else
                            class="text-xl font-bold md:text-2xl"
                            :style="{ color: primary }"
                        >
                            {{ siteConfig.name }}
                        </h1>
                    </Link>
                </div>

                <!-- Navigation -->
                <nav class="hidden items-center space-x-8 md:flex">
                    <template v-if="!loading && menus.header.length > 0">
                        <div
                            v-for="item in menus.header"
                            :key="item.id"
                            class="relative"
                            @mouseenter="
                                hasChildren(item) ? openDropdown(item.id) : null
                            "
                            @mouseleave="closeDropdown"
                        >
                            <!-- Menu Item -->
                            <Link
                                v-if="!hasChildren(item)"
                                :href="getMenuUrl(item)"
                                class="text-gray-700 transition-colors hover:text-gray-900 dark:text-slate-300 dark:hover:text-slate-100"
                            >
                                {{ $t(item.title) }}
                            </Link>

                            <!-- Menu Item with Dropdown -->
                            <button
                                v-else
                                @click="toggleDropdown(item.id)"
                                class="flex items-center space-x-1 text-gray-700 transition-colors hover:text-gray-900 dark:text-slate-300 dark:hover:text-slate-100"
                            >
                                <span>{{ $t(item.title) }}</span>
                                <svg
                                    class="h-4 w-4 transition-transform"
                                    :class="{
                                        'rotate-180':
                                            activeDropdown === item.id,
                                    }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div
                                v-if="
                                    hasChildren(item) &&
                                    activeDropdown === item.id
                                "
                                class="ring-opacity-5 absolute left-0 z-50 mt-2 w-48 rounded-md bg-white shadow-lg ring-1 ring-black dark:bg-slate-900"
                                @mouseenter="openDropdown(item.id)"
                                @mouseleave="closeDropdown"
                            >
                                <div class="py-1">
                                    <!-- Use categories prop for "Categories" menu item, otherwise use children -->
                                    <template
                                        v-if="
                                            item.title === 'Categories' &&
                                            categories &&
                                            categories.length > 0
                                        "
                                    >
                                        <Link
                                            v-for="category in categories"
                                            :key="category.id"
                                            :href="`/category/${category.slug}`"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                                        >
                                            {{ category.name }}
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.id"
                                            :href="getMenuUrl(child)"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                                        >
                                            {{ $t(child.title) }}
                                        </Link>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Fallback to hardcoded menu while loading -->
                    <template v-else>
                        <Link
                            href="/products"
                            class="text-gray-700 transition-colors hover:text-gray-900 dark:text-slate-300 dark:hover:text-slate-100"
                        >
                            {{ $t('Shop') }}
                        </Link>

                        <!-- Categories Dropdown -->
                        <div
                            class="relative"
                            @mouseenter="openCategoriesDropdown"
                            @mouseleave="closeCategoriesDropdown"
                        >
                            <button
                                @click="toggleCategoriesDropdown"
                                class="flex items-center space-x-1 text-gray-700 transition-colors hover:text-gray-900 dark:text-slate-300 dark:hover:text-slate-100"
                            >
                                <span>{{ $t('Categories') }}</span>
                                <svg
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="{
                                        'rotate-180': showCategoriesDropdown,
                                    }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>

                            <!-- Categories Dropdown Menu -->
                            <div
                                v-show="showCategoriesDropdown"
                                class="absolute left-0 z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div class="py-2">
                                    <template
                                        v-if="
                                            categories && categories.length > 0
                                        "
                                    >
                                        <Link
                                            v-for="category in categories"
                                            :key="category.id"
                                            :href="`/category/${category.slug}`"
                                            class="block px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <span>{{ category.name }}</span>
                                                <span
                                                    v-if="
                                                        category.children &&
                                                        category.children
                                                            .length > 0
                                                    "
                                                    class="text-xs text-gray-400 dark:text-slate-500"
                                                >
                                                    ({{
                                                        category.children
                                                            .length
                                                    }})
                                                </span>
                                            </div>
                                        </Link>
                                        <hr class="my-2" />
                                        <Link
                                            href="/products"
                                            class="block px-4 py-2 text-sm font-medium transition-colors hover:bg-gray-100 dark:hover:bg-slate-800"
                                            :style="{ color: primary }"
                                        >
                                            {{ $t('View All Categories') }} →
                                        </Link>
                                    </template>
                                    <div
                                        v-else
                                        class="px-4 py-3 text-sm text-gray-500 dark:text-slate-400"
                                    >
                                        {{ $t('No categories available') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Link
                            href="/products?on_sale=1"
                            class="text-gray-700 transition-colors hover:text-gray-900 dark:text-slate-300 dark:hover:text-slate-100"
                        >
                            {{ $t('Deals') }}
                        </Link>
                        <Link
                            href="/blog"
                            class="text-gray-700 transition-colors hover:text-gray-900 dark:text-slate-300 dark:hover:text-slate-100"
                        >
                            {{ $t('Blog') }}
                        </Link>
                        <Link
                            href="/about-us"
                            class="text-gray-700 transition-colors hover:text-gray-900 dark:text-slate-300 dark:hover:text-slate-100"
                        >
                            {{ $t('About') }}
                        </Link>
                    </template>
                </nav>

                <!-- Right Section -->
                <div class="flex items-center space-x-2 md:space-x-4">
                    <!-- Mobile search toggle -->
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-700 hover:bg-slate-100 md:hidden dark:hover:bg-slate-800"
                        :aria-label="$t('Search')"
                        @click="mobileSearchOpen = !mobileSearchOpen"
                    >
                        <Search class="h-5 w-5" />
                    </button>

                    <!-- Search -->
                    <div class="relative hidden md:block">
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="$t('Search products...')"
                            class="w-64 rounded-lg border border-gray-300 px-4 py-2 pr-10 pl-10 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-slate-600"
                            @input="onSearchInput"
                            @keydown="handleKeyDown"
                            @focus="
                                searchQuery.length >= 2 && fetchSuggestions()
                            "
                            @blur="closeSuggestions"
                            autocomplete="off"
                        />
                        <!-- Loading Spinner -->
                        <svg
                            v-if="isSearching"
                            class="absolute top-2.5 left-3 h-5 w-5 animate-spin"
                            :style="{ color: primary }"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        <!-- Search Icon -->
                        <svg
                            v-else
                            class="absolute top-2.5 left-3 h-5 w-5 text-gray-400 dark:text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                        <button
                            v-if="searchQuery"
                            @click="handleSearch()"
                            class="absolute top-2.5 right-3 cursor-pointer text-gray-400 hover:text-gray-600 dark:text-slate-500"
                            type="button"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"
                                />
                            </svg>
                        </button>

                        <!-- Search Suggestions Dropdown -->
                        <div
                            v-if="showSuggestions && suggestions.length > 0"
                            class="search-suggestions absolute top-full right-0 left-0 z-50 mt-2 max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div
                                v-for="(suggestion, index) in suggestions"
                                :key="suggestion.id"
                                @click="handleSearch(suggestion)"
                                :class="[
                                    'flex cursor-pointer items-center gap-3 px-4 py-3 transition-colors',
                                    selectedIndex === index
                                        ? 'bg-blue-50'
                                        : 'hover:bg-gray-50',
                                ]"
                            >
                                <!-- Product Image -->
                                <div
                                    class="h-12 w-12 flex-shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-slate-800"
                                >
                                    <img
                                        v-if="suggestion.image"
                                        :src="suggestion.image"
                                        :alt="suggestion.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-gray-400 dark:text-slate-500"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.5"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                            />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Product Info -->
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium text-gray-900 dark:text-slate-100"
                                    >
                                        {{ suggestion.name }}
                                    </p>
                                    <p
                                        class="text-sm font-semibold"
                                        :style="{ color: primary }"
                                    >
                                        {{ formatPrice(suggestion.price) }}
                                    </p>
                                </div>

                                <!-- Arrow Icon -->
                                <svg
                                    class="h-4 w-4 flex-shrink-0 text-gray-400 dark:text-slate-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Appearance, currency and language. Hidden on a phone, where the drawer
                             carries the same three controls. -->
                    <div class="hidden shrink-0 items-center gap-2 md:flex">
                        <CurrencySelector />
                        <ThemeToggle />
                        <LanguageSwitcher />
                    </div>

                    <!-- Cart Icon (Reusable) -->
                    <CartIcon />

                    <!-- Wishlist Icon -->
                    <Link
                        v-if="user && wishlistEnabled"
                        href="/account/wishlist"
                        class="relative p-2 text-gray-700 transition-colors hover:text-red-500 dark:text-slate-300"
                        :title="$t('Wishlist')"
                    >
                        <Heart class="h-6 w-6" />
                        <span
                            v-if="wishlistCount > 0"
                            class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white"
                        >
                            {{ wishlistCount }}
                        </span>
                    </Link>

                    <!-- Auth Links - Show if NOT logged in -->
                    <template v-if="!user">
                        <Link
                            href="/login"
                            class="hidden rounded-lg px-4 py-2 text-sm font-medium text-white transition-opacity hover:opacity-90 sm:inline-flex"
                            :style="{ backgroundColor: primary }"
                        >
                            {{ $t('Login') }}
                        </Link>
                        <Link
                            href="/register"
                            class="hidden rounded-lg border-2 px-4 py-2 text-sm font-medium transition-colors hover:bg-gray-50 sm:inline-flex dark:hover:bg-slate-800"
                            :style="{ borderColor: primary, color: primary }"
                        >
                            {{ $t('Register') }}
                        </Link>
                    </template>

                    <!-- User Menu - Show if logged in -->
                    <!--
                        Desktop and up only. On a phone the same account lives in
                        the drawer below, and showing both meant two ways into the
                        profile on one screen, with the desktop dropdown floating
                        over page content it was never meant to sit on.
                    -->
                    <div
                        v-else
                        class="relative hidden md:block"
                        @mouseenter="openUserMenu"
                        @mouseleave="closeUserMenu"
                    >
                        <button
                            @click="toggleUserMenu"
                            class="flex items-center space-x-2 rounded-lg px-3 py-2 transition-colors hover:bg-gray-100 dark:hover:bg-slate-800"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full font-medium text-white"
                                :style="{ backgroundColor: primary }"
                            >
                                {{ user.name?.charAt(0).toUpperCase() }}
                            </div>
                            <span
                                class="text-sm font-medium text-gray-700 dark:text-slate-300"
                                >{{ user.name }}</span
                            >
                            <svg
                                class="h-4 w-4 text-gray-500 transition-transform dark:text-slate-400"
                                :class="{ 'rotate-180': showUserMenu }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            v-if="showUserMenu"
                            class="ring-opacity-5 absolute right-0 z-[100] mt-2 w-56 rounded-md bg-white shadow-lg ring-1 ring-black dark:bg-slate-900"
                            @mouseenter="openUserMenu"
                            @mouseleave="closeUserMenu"
                        >
                            <div class="py-1">
                                <Link
                                    href="/account"
                                    class="block flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                                        />
                                    </svg>
                                    <span>{{ $t('Dashboard') }}</span>
                                </Link>
                                <Link
                                    href="/account/orders"
                                    class="block flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    <svg
                                        class="h-4 w-4"
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
                                    <span>{{ $t('My Orders') }}</span>
                                </Link>
                                <Link
                                    href="/account/profile"
                                    class="block flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                        />
                                    </svg>
                                    <span>{{ $t('Profile') }}</span>
                                </Link>
                                <Link
                                    href="/account/addresses"
                                    class="block flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                    <span>{{ $t('Addresses') }}</span>
                                </Link>
                                <Link
                                    href="/services"
                                    class="block flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <span>{{ $t('Services') }}</span>
                                </Link>
                                <Link
                                    v-if="referralEnabled"
                                    href="/account/referrals"
                                    class="block flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <span>{{ $t('Refer & Earn') }}</span>
                                </Link>
                                <Link
                                    v-if="identityEnabled"
                                    href="/account/identity"
                                    class="block flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                                        />
                                    </svg>
                                    <span>{{
                                        $t('Identity Verification')
                                    }}</span>
                                </Link>
                                <div class="px-2 py-1">
                                    <WhatsAppButton variant="button" block />
                                </div>
                                <div
                                    class="my-1 border-t border-gray-100 dark:border-slate-700"
                                ></div>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    class="block flex w-full items-center space-x-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                        />
                                    </svg>
                                    <span>{{ $t('Logout') }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile search bar -->
            <div v-if="mobileSearchOpen" class="pb-4 md:hidden">
                <div class="relative">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="$t('Search products...')"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 pr-4 pl-10 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-slate-600"
                        @input="onSearchInput"
                        @keydown="handleKeyDown"
                        autocomplete="off"
                    />
                    <Search
                        class="absolute top-2.5 left-3 h-5 w-5 text-gray-400 dark:text-slate-500"
                    />
                </div>
            </div>
        </div>

        <!-- Mobile drawer -->
        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="mobileMenuOpen"
                    class="fixed inset-0 z-[100] md:hidden"
                >
                    <div
                        class="absolute inset-0 bg-black/40"
                        @click="closeMobileMenu"
                    />
                    <aside
                        class="absolute top-0 left-0 flex h-full w-[min(320px,88vw)] flex-col bg-white shadow-xl dark:bg-slate-900"
                    >
                        <div
                            class="flex h-16 items-center justify-between border-b border-slate-200 px-4 dark:border-slate-700"
                        >
                            <span
                                class="font-bold text-slate-900 dark:text-slate-100"
                                >{{ siteConfig.name }}</span
                            >
                            <button
                                type="button"
                                class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
                                :aria-label="$t('Close menu')"
                                @click="closeMobileMenu"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <nav class="flex-1 space-y-1 overflow-y-auto p-4">
                            <template
                                v-if="!loading && menus.header.length > 0"
                            >
                                <template
                                    v-for="item in menus.header"
                                    :key="item.id"
                                >
                                    <Link
                                        v-if="!hasChildren(item)"
                                        :href="getMenuUrl(item)"
                                        class="block rounded-lg px-3 py-3 font-medium text-slate-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                        @click="closeMobileMenu"
                                    >
                                        {{ $t(item.title) }}
                                    </Link>
                                    <div v-else class="py-1">
                                        <p
                                            class="px-3 py-2 text-xs font-semibold tracking-wide text-slate-400 uppercase"
                                        >
                                            {{ $t(item.title) }}
                                        </p>
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.id"
                                            :href="getMenuUrl(child)"
                                            class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                            @click="closeMobileMenu"
                                        >
                                            {{ $t(child.title) }}
                                        </Link>
                                    </div>
                                </template>
                            </template>
                            <template v-else>
                                <Link
                                    href="/products"
                                    class="block rounded-lg px-3 py-3 font-medium text-slate-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                    @click="closeMobileMenu"
                                    >{{ $t('Shop') }}</Link
                                >
                                <Link
                                    href="/products?on_sale=1"
                                    class="block rounded-lg px-3 py-3 font-medium text-slate-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                    @click="closeMobileMenu"
                                    >{{ $t('Deals') }}</Link
                                >
                                <Link
                                    href="/blog"
                                    class="block rounded-lg px-3 py-3 font-medium text-slate-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                    @click="closeMobileMenu"
                                    >{{ $t('Blog') }}</Link
                                >
                                <Link
                                    href="/about-us"
                                    class="block rounded-lg px-3 py-3 font-medium text-slate-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                    @click="closeMobileMenu"
                                    >{{ $t('About') }}</Link
                                >
                                <div v-if="categories?.length" class="pt-2">
                                    <p
                                        class="px-3 py-2 text-xs font-semibold tracking-wide text-slate-400 uppercase"
                                    >
                                        {{ $t('Categories') }}
                                    </p>
                                    <Link
                                        v-for="category in categories"
                                        :key="category.id"
                                        :href="`/category/${category.slug}`"
                                        class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                        @click="closeMobileMenu"
                                    >
                                        {{ category.name }}
                                    </Link>
                                </div>
                            </template>
                        </nav>

                        <div
                            class="space-y-3 border-t border-slate-200 p-4 dark:border-slate-700"
                        >
                            <!--
                                Appearance, currency and language, on one row. Each
                                control sizes to its own content and the row wraps,
                                rather than stretching three buttons to fill the
                                drawer width.
                            -->
                            <div class="flex flex-wrap items-center gap-2">
                                <ThemeToggle variant="segmented" compact />
                                <CurrencySelector />
                                <LanguageSwitcher />
                            </div>

                            <template v-if="!user">
                                <Link
                                    href="/login"
                                    class="block w-full rounded-lg px-4 py-3 text-center text-sm font-medium text-white"
                                    :style="{ backgroundColor: primary }"
                                    @click="closeMobileMenu"
                                >
                                    {{ $t('Login') }}
                                </Link>
                                <Link
                                    href="/register"
                                    class="block w-full rounded-lg border-2 px-4 py-3 text-center text-sm font-medium"
                                    :style="{
                                        borderColor: primary,
                                        color: primary,
                                    }"
                                    @click="closeMobileMenu"
                                >
                                    {{ $t('Create Account') }}
                                </Link>
                            </template>

                            <!--
                                Logged in: the profile, then its pages indented
                                underneath so a phone visitor is never one tap away
                                from orders, addresses or verification.
                            -->
                            <div v-else class="space-y-2">
                                <div
                                    class="flex items-center gap-3 rounded-lg bg-slate-100 px-3 py-2.5 dark:bg-slate-800"
                                >
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
                                        :style="{ backgroundColor: primary }"
                                    >
                                        {{ user.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ user.name }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ user.email }}
                                        </p>
                                    </div>
                                </div>

                                <Link
                                    href="/account"
                                    class="block w-full rounded-lg px-4 py-3 text-center text-sm font-medium text-slate-800 dark:text-slate-200"
                                    :style="{ backgroundColor: primary }"
                                    @click="closeMobileMenu"
                                >
                                    {{ $t('My Account') }}
                                </Link>

                                <nav
                                    class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700"
                                >
                                    <Link
                                        v-for="link in accountLinks"
                                        :key="link.href"
                                        :href="link.href"
                                        class="flex items-center gap-3 px-3 py-2.5 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50"
                                        @click="closeMobileMenu"
                                    >
                                        <component
                                            :is="link.icon"
                                            class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500"
                                        />
                                        {{ $t(link.label) }}
                                    </Link>

                                    <div
                                        class="px-2 py-2"
                                        @click="closeMobileMenu"
                                    >
                                        <WhatsAppButton
                                            variant="button"
                                            block
                                        />
                                    </div>

                                    <Link
                                        v-if="identityEnabled"
                                        href="/account/identity"
                                        class="flex items-center gap-3 px-3 py-2.5 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50"
                                        @click="closeMobileMenu"
                                    >
                                        <BadgeCheck
                                            class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500"
                                        />
                                        {{ $t('Identity Verification') }}
                                    </Link>

                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                        @click="closeMobileMenu"
                                    >
                                        <LogOut class="h-4 w-4 shrink-0" />
                                        {{ $t('Logout') }}
                                    </Link>
                                </nav>
                            </div>
                        </div>
                    </aside>
                </div>
            </Transition>
        </Teleport>
    </header>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
