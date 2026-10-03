<script setup lang="ts">
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Database,
    Eye,
    Italic,
    Link,
    List,
    ListOrdered,
    Pilcrow,
    Quote,
    Redo2,
    Settings,
    Signpost,
    Underline,
    Undo2,
    Unlink,
    Zap,
} from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { computed, type Component } from 'vue';

interface Props {
    name: string;
    class?: string;
    size?: number | string;
    color?: string;
    strokeWidth?: number | string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
    size: 16,
    strokeWidth: 2,
});

/**
 * Only the icons this app asks for by name.
 *
 * `import * as icons from 'lucide-vue-next'` pulled the whole icon library into
 * every page that showed one, producing a ~720 kB chunk. Naming the handful
 * that are used lets the bundler drop the rest.
 *
 * Keys are the kebab-case names the callers use, so adjacent-word icons like
 * "align-left" resolve. The old capitalise-the-first-letter lookup turned that
 * into "Align-left" and quietly drew nothing.
 */
const registry: Record<string, Component> = {
    'align-center': AlignCenter,
    'align-left': AlignLeft,
    'align-right': AlignRight,
    'bold': Bold,
    'database': Database,
    'eye': Eye,
    'italic': Italic,
    'link': Link,
    'list': List,
    'list-ordered': ListOrdered,
    'pilcrow': Pilcrow,
    'quote': Quote,
    'redo': Redo2,
    'settings': Settings,
    'signpost': Signpost,
    'underline': Underline,
    'undo': Undo2,
    'unlink': Unlink,
    'zap': Zap,
};

const className = computed(() => cn('h-4 w-4', props.class));

const icon = computed(() => registry[props.name]);
</script>

<template>
    <component
        :is="icon"
        :class="className"
        :size="size"
        :stroke-width="strokeWidth"
        :color="color"
    />
</template>
