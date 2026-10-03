<script setup lang="ts">
import { onClickOutside, useEventListener } from '@vueuse/core';
import { CalendarDays, ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

import { useCalendar } from '@/composables/useCalendar';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import {
    fromGregorian,
    monthGrid,
    monthName,
    toNumerals,
    today,
    type GridDay,
} from '@/lib/solar-hijri';

/**
 * A Solar Hijri date picker.
 *
 * The browser's own <input type="date"> cannot show Solar Hijri -- it decides
 * its own calendar -- so this draws the Afghan month grid itself and hides the
 * native one.
 *
 * THE ONE RULE: `v-model` is the Gregorian date, 'YYYY-MM-DD'; when `withTime`
 * is set it is 'YYYY-MM-DDTHH:mm', the exact shape a native datetime-local
 * field used. The Solar Hijri figures are what the shopper reads and clicks;
 * the value that leaves is the Gregorian one the server already stores. Nothing
 * here changes a backend contract, so swapping a native input for this one is a
 * display change only.
 */
const props = withDefaults(
    defineProps<{
        /** Gregorian 'YYYY-MM-DD', or '' for empty. */
        modelValue?: string | null;
        /** Earliest selectable Gregorian 'YYYY-MM-DD'. */
        min?: string | null;
        /** Latest selectable Gregorian 'YYYY-MM-DD'. */
        max?: string | null;
        id?: string;
        name?: string;
        placeholder?: string;
        disabled?: boolean;
        clearable?: boolean;
        /**
         * Offer a clock as well as a day. The value then becomes a native
         * 'YYYY-MM-DDTHH:mm' string, which is what a datetime-local field
         * bound before, so swapping the input changes only the calendar shown.
         */
        withTime?: boolean;
    }>(),
    {
        modelValue: '',
        min: null,
        max: null,
        id: undefined,
        name: undefined,
        placeholder: '',
        disabled: false,
        clearable: true,
        withTime: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'change', value: string): void;
}>();

// Classes and listeners belong on the trigger button, which is what the
// caller is replacing: they styled their <input>, so they land here instead.
defineOptions({ inheritAttrs: false });

const { formatDate, settings, locale, direction } = useCalendar();

const PANEL_WIDTH = 320;
const GAP = 6;

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);

/**
 * The picked value is 'YYYY-MM-DD', or 'YYYY-MM-DDTHH:mm' when withTime.
 * Everywhere inside the picker only the day half is read.
 */
const selectedIso = computed(() => (props.modelValue ?? '').slice(0, 10));

/** The clock half of a withTime value, or midnight when unset. */
const selectedTime = computed(() => {
    const match = (props.modelValue ?? '').match(/[T ](\d{2}):(\d{2})/);

    return match ? `${match[1]}:${match[2]}` : '00:00';
});

const timeValue = ref(selectedTime.value);
const mode = ref<'days' | 'months'>('days');

/** A stable "today" for highlighting; the page is not expected to cross midnight. */
const todayIso = today().gregorian;

/**
 * How far the month header may travel. A field's own min/max win, so a birthday
 * field can reach back past the store's default window.
 */
const navMinYear = computed(() =>
    props.min ? fromGregorian(props.min).year : settings.value.minYear,
);
const navMaxYear = computed(() =>
    props.max ? fromGregorian(props.max).year : settings.value.maxYear,
);

/** Emit the picked day, with the clock when the field asked for one. */
function emitValue(date: string): void {
    const value = props.withTime ? `${date}T${timeValue.value}` : date;
    emit('update:modelValue', value);
    emit('change', value);
}

const view = ref<{ year: number; month: number }>({ year: 1400, month: 1 });

const grid = computed(() =>
    monthGrid(view.value.year, view.value.month, locale.value),
);

const yearLabel = computed(() =>
    toNumerals(String(view.value.year), settings.value.numerals),
);

const position = ref({ top: 0, left: 0 });

function seedView(): void {
    const solar = fromGregorian(selectedIso.value || todayIso);
    view.value = { year: solar.year, month: solar.month };
    mode.value = 'days';
    timeValue.value = selectedTime.value;
}

/** Changing the clock on an already-picked day should update the value too. */
watch(timeValue, (value) => {
    if (!props.withTime || !selectedIso.value) return;
    // Seeding on open sets the clock back to what is already stored; that is
    // not a user edit and must not dirty the form.
    if (value === selectedTime.value) return;

    const next = `${selectedIso.value}T${value}`;
    emit('update:modelValue', next);
    emit('change', next);
});

function updatePosition(): void {
    const el = root.value;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const panelHeight = panel.value?.offsetHeight || 348;

    let top = rect.bottom + GAP;
    if (
        top + panelHeight > window.innerHeight - 8 &&
        rect.top - panelHeight - GAP > 8
    ) {
        top = rect.top - panelHeight - GAP;
    }

    const preferred =
        direction.value === 'rtl' ? rect.right - PANEL_WIDTH : rect.left;
    const left = Math.max(
        8,
        Math.min(preferred, window.innerWidth - PANEL_WIDTH - 8),
    );

    position.value = { top, left };
}

function close(): void {
    open.value = false;
}

function toggle(): void {
    if (props.disabled) return;
    open.value = !open.value;
}

watch(open, (isOpen) => {
    if (!isOpen) return;
    seedView();
    nextTick(updatePosition);
});

useEventListener(window, 'resize', () => open.value && updatePosition());
useEventListener(window, 'scroll', () => open.value && updatePosition(), {
    capture: true,
});
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if (event.key === 'Escape' && open.value) close();
});

onClickOutside(panel, () => close(), { ignore: [root] });

function isDisabled(day: GridDay): boolean {
    if (props.min && day.gregorian < props.min) return true;
    if (props.max && day.gregorian > props.max) return true;

    return false;
}

function dayClasses(day: GridDay): Record<string, boolean> {
    const selected = day.gregorian === selectedIso.value;

    return {
        'bg-blue-600 text-white hover:bg-blue-600': selected,
        'text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700':
            !selected && day.inMonth,
        'text-gray-300 dark:text-gray-600': !selected && !day.inMonth,
        'ring-1 ring-blue-400 dark:ring-blue-500':
            !selected && day.gregorian === todayIso && day.inMonth,
        'opacity-40 cursor-not-allowed': isDisabled(day),
    };
}

function select(day: GridDay): void {
    if (isDisabled(day)) return;
    emitValue(day.gregorian);
    if (!props.withTime) close();
}

function moveMonth(delta: number): void {
    let month = view.value.month + delta;
    let year = view.value.year;

    if (month < 1) {
        month = 12;
        year -= 1;
    } else if (month > 12) {
        month = 1;
        year += 1;
    }

    if (year < navMinYear.value || year > navMaxYear.value) return;

    view.value = { year, month };
}

function moveYear(delta: number): void {
    const year = view.value.year + delta;
    if (year < navMinYear.value || year > navMaxYear.value) return;

    view.value = { ...view.value, year };
}

function openMonth(month: number): void {
    view.value = { ...view.value, month };
    mode.value = 'days';
}

const todayDisabled = computed(
    () =>
        (props.min !== null && todayIso < props.min) ||
        (props.max !== null && todayIso > props.max),
);

function selectToday(): void {
    if (todayDisabled.value) return;
    emitValue(todayIso);
    if (!props.withTime) close();
}

function clear(): void {
    emit('update:modelValue', '');
    emit('change', '');
    close();
}
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            v-bind="$attrs"
            :id="id"
            :name="name"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="dialog"
            class="w-full flex items-center gap-2 text-start disabled:opacity-50 disabled:cursor-not-allowed"
            @click="toggle"
        >
            <CalendarDays class="w-4 h-4 opacity-60 shrink-0" />
            <span v-if="selectedIso" class="truncate">
                <DateDisplay :value="modelValue" :time="withTime" />
            </span>
            <span v-else class="opacity-50 truncate">{{ placeholder }}</span>
            <span class="flex-1" />
            <span
                v-if="clearable && selectedIso && !disabled"
                role="button"
                tabindex="-1"
                class="shrink-0 rounded p-0.5 hover:bg-gray-200 dark:hover:bg-gray-600"
                @click.stop="clear"
            >
                <X class="w-3.5 h-3.5" />
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="panel"
                role="dialog"
                :dir="direction"
                class="fixed z-[100] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-xl"
                :style="{
                    top: `${position.top}px`,
                    left: `${position.left}px`,
                    width: `${PANEL_WIDTH}px`,
                }"
            >
                <!-- Month header -->
                <div class="mb-2 flex items-center justify-between gap-1">
                    <template v-if="mode === 'days'">
                        <button
                            type="button"
                            class="rounded-md p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="moveMonth(direction === 'rtl' ? 1 : -1)"
                        >
                            <ChevronRight v-if="direction === 'rtl'" class="h-4 w-4" />
                            <ChevronLeft v-else class="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-sm font-semibold text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700"
                            @click="mode = 'months'"
                        >
                            {{ grid.monthName }} {{ yearLabel }}
                        </button>

                        <button
                            type="button"
                            class="rounded-md p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="moveMonth(direction === 'rtl' ? -1 : 1)"
                        >
                            <ChevronLeft v-if="direction === 'rtl'" class="h-4 w-4" />
                            <ChevronRight v-else class="h-4 w-4" />
                        </button>
                    </template>

                    <template v-else>
                        <button
                            type="button"
                            class="rounded-md p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="moveYear(direction === 'rtl' ? 1 : -1)"
                        >
                            <ChevronRight v-if="direction === 'rtl'" class="h-4 w-4" />
                            <ChevronLeft v-else class="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-sm font-semibold text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700"
                            @click="mode = 'days'"
                        >
                            {{ yearLabel }}
                        </button>

                        <button
                            type="button"
                            class="rounded-md p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="moveYear(direction === 'rtl' ? -1 : 1)"
                        >
                            <ChevronLeft v-if="direction === 'rtl'" class="h-4 w-4" />
                            <ChevronRight v-else class="h-4 w-4" />
                        </button>
                    </template>
                </div>

                <!-- Month chooser: one tap jumps a whole year, so a birthday is
                     a few clicks rather than a few dozen. -->
                <div v-if="mode === 'months'" class="grid grid-cols-3 gap-1">
                    <button
                        v-for="m in 12"
                        :key="m"
                        type="button"
                        :class="
                            m === view.month
                                ? 'bg-blue-600 text-white hover:bg-blue-600'
                                : 'text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700'
                        "
                        class="h-9 rounded-md text-sm transition-colors"
                        @click="openMonth(m)"
                    >
                        {{ monthName(m, locale) }}
                    </button>
                </div>

                <template v-else>
                    <!-- Weekday row -->
                    <div class="grid grid-cols-7 gap-0.5">
                        <div
                            v-for="name in grid.weekdays"
                            :key="name"
                            class="h-8 flex items-center justify-center text-xs font-medium text-gray-400 dark:text-gray-500"
                        >
                            {{ name }}
                        </div>
                    </div>

                    <!-- Day grid -->
                    <div class="grid grid-cols-7 gap-0.5">
                        <button
                            v-for="day in grid.weeks.flat()"
                            :key="day.iso"
                            type="button"
                            :disabled="isDisabled(day)"
                            :class="dayClasses(day)"
                            class="h-9 rounded-md text-sm transition-colors"
                            @click="select(day)"
                        >
                            {{ toNumerals(String(day.day), settings.numerals) }}
                        </button>
                    </div>
                </template>

                <!-- Clock: only for a datetime-local field -->
                <div
                    v-if="withTime"
                    class="mt-2 flex items-center gap-2 border-t border-gray-100 dark:border-gray-700 pt-2"
                >
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ $t('Time') }}
                    </span>
                    <input
                        v-model="timeValue"
                        type="time"
                        dir="ltr"
                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <!-- Actions -->
                <div
                    v-if="!todayDisabled || clearable"
                    class="mt-2 flex items-center justify-between border-t border-gray-100 dark:border-gray-700 pt-2"
                >
                    <button
                        v-if="!todayDisabled"
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                        @click="selectToday"
                    >
                        {{ $t('Today') }}
                    </button>
                    <span class="flex-1" />
                    <button
                        v-if="clearable"
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                        @click="clear"
                    >
                        {{ $t('Clear') }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
