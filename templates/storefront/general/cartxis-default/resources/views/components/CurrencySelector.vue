<script setup lang="ts">
/**
 * Currency picker for the storefront.
 *
 * Offers AFN and USD and nothing else, because those are the only two the
 * store trades in. The list comes from the server rather than being written
 * out here, so if the store ever stops offering dollars the picker stops
 * offering them too.
 *
 * This component changes what the shopper READS. It does not change what they
 * are charged: the order is still placed in afghani, and a wallet payment is
 * still taken in afghani.
 *
 * The control is a real button with a real listbox, so it works with the
 * keyboard and with JavaScript half-loaded. The options are always in the tab
 * order; the one thing that waits for hydration is the post itself, and until
 * then choosing an option simply does nothing, which is why the button is not
 * hidden before mount.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useCurrency, type Currency } from '@/composables/useCurrency';

const { currencies, active, rateLabel, setCurrency } = useCurrency();

const isOpen = ref(false);
const isSaving = ref(false);
const root = ref<HTMLElement | null>(null);

/** Only ever the two supported currencies, in the order the server gave them. */
const options = computed<Currency[]>(() => currencies.value);

/** The afghani, if it is on offer -- the one people usually want to get back to. */
const isAfn = computed(() => active.value.code === 'AFN');

function choose(code: string) {
    isSaving.value = true;
    setCurrency(code, () => {
        isSaving.value = false;
        isOpen.value = false;
    });
}

function closeOnOutsideClick(event: MouseEvent) {
    if (!root.value) return;
    if (!root.value.contains(event.target as Node)) isOpen.value = false;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && isOpen.value) {
        isOpen.value = false;
    }
}

// Listen only while the menu is open, so a page with this header in it is not
// running a document-level click handler on every page load.
watch(isOpen, (open) => {
    if (open) {
        document.addEventListener('click', closeOnOutsideClick, true);
    } else {
        document.removeEventListener('click', closeOnOutsideClick, true);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', closeOnOutsideClick, true);
});

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        ref="root"
        class="relative"
        @keydown="onKeydown"
    >
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-gray-200 dark:hover:bg-gray-800"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            :aria-label="`Change currency, currently ${active.code}`"
            @click="isOpen = !isOpen"
        >
            <span aria-hidden="true">{{ active.symbol }}</span>
            <span>{{ active.code }}</span>
            <svg
                class="h-3.5 w-3.5 transition-transform"
                :class="isOpen ? 'rotate-180' : ''"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div
            v-if="isOpen"
            class="absolute end-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            role="listbox"
            :aria-label="'Currency'"
        >
            <ul class="py-1">
                <li
                    v-for="option in options"
                    :key="option.code"
                    role="option"
                    :aria-selected="option.code === active.code"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-start text-sm transition-colors hover:bg-gray-100 dark:hover:bg-slate-800 focus:outline-none focus-visible:bg-gray-100 dark:hover:bg-gray-700 dark:focus-visible:bg-gray-700"
                        :class="option.code === active.code
                            ? 'font-semibold text-gray-900 dark:text-white'
                            : 'text-gray-700 dark:text-gray-200'"
                        :disabled="isSaving"
                        @click="choose(option.code)"
                    >
                        <span class="flex items-center gap-2">
                            <span aria-hidden="true">{{ option.symbol }}</span>
                            <span>{{ option.code }}</span>
                            <span class="text-xs text-gray-500 dark:text-slate-400 dark:text-gray-400">{{ option.name }}</span>
                        </span>
                        <svg
                            v-if="option.code === active.code"
                            class="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </li>
            </ul>

            <!--
                The rate, stated in the same words the admin typed it into, so a
                shopper can see the arithmetic behind a converted price instead
                of having to trust it. Only shown when a conversion is actually
                in play -- in afghani it would just be noise.
            -->
            <p
                v-if="!isAfn"
                class="border-t border-gray-200 dark:border-slate-700 px-3 py-2 text-xs text-gray-500 dark:text-slate-400 dark:border-gray-700 dark:text-gray-400"
            >
                {{ rateLabel }}
            </p>
        </div>
    </div>
</template>
