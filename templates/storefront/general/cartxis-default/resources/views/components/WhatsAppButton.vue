<script setup lang="ts">
/**
 * Opens the store's WhatsApp chat in a new tab.
 *
 * The number is whatever the dashboard has set as the business number. It is
 * never written into this file. If the dashboard has no number we render
 * nothing at all, because a chat link that goes nowhere is worse than no link.
 */
import { useI18nStore } from '@/Stores/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type Variant = 'button' | 'icon' | 'link';

interface Props {
    label?: string;
    variant?: Variant;
    message?: string;
    block?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    variant: 'button',
    message: 'Hello Asaan Store, ',
    block: false,
});

const i18n = useI18nStore();
const page = usePage();

/** Only the digits survive: wa.me wants them plain, with no + or spaces. */
const digits = computed(() => {
    const contact = (page.props as Record<string, any> | undefined)
        ?.storeContact;
    const number = contact?.whatsapp;
    if (!number) return '';
    return String(number).replace(/\D/g, '');
});

const available = computed(() => digits.value.length > 0);

const href = computed(
    () =>
        `https://wa.me/${digits.value}?text=${encodeURIComponent(props.message)}`,
);

const resolvedLabel = computed(() => props.label ?? i18n.t('Chat on WhatsApp'));

const variantClass = computed(() => {
    if (props.variant === 'icon') {
        return 'inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#25D366] text-white transition-colors hover:bg-[#1ebe5d]';
    }

    if (props.variant === 'link') {
        return 'inline-flex items-center gap-1.5 text-sm font-medium text-[#128C7E] transition-colors hover:underline dark:text-[#25D366]';
    }

    return [
        'inline-flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#1ebe5d]',
        props.block ? 'w-full' : '',
    ].join(' ');
});

const iconClass = computed(() =>
    props.variant === 'link' ? 'h-4 w-4' : 'h-5 w-5',
);
</script>

<template>
    <a
        v-if="available"
        :href="href"
        target="_blank"
        rel="noopener"
        :class="variantClass"
        :aria-label="resolvedLabel"
        :title="resolvedLabel"
    >
        <svg
            :class="iconClass"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
            />
        </svg>
        <span v-if="variant !== 'icon'">{{ resolvedLabel }}</span>
    </a>
</template>
