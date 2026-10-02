<script setup lang="ts">
import { computed } from 'vue';

import { useCalendar } from '@/composables/useCalendar';

const props = withDefaults(
    defineProps<{
        /** A Gregorian date from the server: 'YYYY-MM-DD' or a timestamp. */
        value: string | number | Date | null | undefined;
        /** Put the weekday in front of both halves. */
        weekday?: boolean;
        /** Numerals only: '۱۴۰۴/۰۶/۱۵ (2025/09/06)'. */
        short?: boolean;
        /** Append the clock time for a stored timestamp: '· ۱۴:۳۰'. */
        time?: boolean;
        /** Include seconds with the clock. Off by default. */
        seconds?: boolean;
        placeholder?: string;
    }>(),
    { weekday: false, short: false, time: false, seconds: false, placeholder: '—' },
);

const {
    formatDate,
    formatSolar,
    formatGregorian,
    formatTime,
    settings,
    direction,
} = useCalendar();

const dateValue = computed<Date | string | number | null>(() => {
    const value = props.value;

    return value === null || value === undefined || value === '' ? null : value;
});

const solar = computed(() =>
    dateValue.value === null
        ? ''
        : formatSolar(dateValue.value, { short: props.short }),
);

const gregorian = computed(() =>
    dateValue.value === null || settings.value.secondary === 'none'
        ? ''
        : formatGregorian(dateValue.value, { short: props.short }),
);

/** With a weekday the engine's single long string reads better as one run. */
const long = computed(() =>
    dateValue.value === null
        ? ''
        : formatDate(dateValue.value, { weekday: true, short: props.short }),
);

/** The clock on a timestamp, one run shared by both calendar halves. */
const clock = computed(() =>
    dateValue.value === null || !props.time
        ? ''
        : formatTime(dateValue.value, { seconds: props.seconds }),
);
</script>

<template>
    <!--
        One root element, so a caller's class (text size, colour) lands on the
        whole date rather than being dropped from a fragment.
    -->
    <span
        class="whitespace-nowrap"
        :dir="dateValue === null ? undefined : direction"
    >
        <span v-if="dateValue === null" class="text-gray-400 dark:text-gray-500">
            {{ placeholder }}
        </span>

        <template v-else-if="weekday">{{ long }}</template>

        <template v-else>
            <span>{{ solar }}</span>
            <!--
                The Gregorian reference is isolated and forced left-to-right so
                its brackets and digit order survive an RTL page, and it stays a
                run that can be copied into a form.
            -->
            <span
                v-if="gregorian"
                dir="ltr"
                class="text-gray-400 dark:text-gray-500"
            >
                ({{ gregorian }})
            </span>
        </template>

        <!--
            The clock trails the whole stamp so it reads the same whether or not
            the weekday was asked for. Kept out of the Gregorian run: a time is
            not Gregorian, it is the same instant on either calendar.
        -->
        <span
            v-if="clock"
            class="tabular-nums text-gray-500 dark:text-gray-400"
            dir="ltr"
        >
            · {{ clock }}
        </span>
    </span>
</template>
