<script setup lang="ts">
/**
 * What a visitor sees when the page they asked for could not be delivered.
 *
 * Shown for a missing page, an expired session, too many requests, and for the
 * shop being closed for maintenance. The server decides which; this page just
 * says it in plain language and gets them somewhere useful.
 *
 * Deliberately self-contained. It reads nothing from the shared page data --
 * no account, no cart, no currency, no settings -- because the most common
 * reason for landing here is that one of those could not be loaded. A page
 * that needed the thing that just broke would be no use at all.
 */
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps<{
    status: number
    title: string
    message: string
    maintenance: boolean
}>()

/** Something short enough to sit above the message without shouting. */
const headline = computed(() => (props.maintenance ? props.title : String(props.status)))

/** Where to send someone who wants to keep shopping. */
const backLabel = computed(() => (props.maintenance ? 'Try again' : 'Back to the shop'))
</script>

<template>
    <Head :title="title" />

    <main
        dir="auto"
        class="flex min-h-screen w-full items-center justify-center bg-white px-6 py-16 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <div class="w-full max-w-lg text-center">
            <p
                class="text-sm font-semibold tracking-[0.2em] text-neutral-400 uppercase dark:text-neutral-500"
            >
                {{ headline }}
            </p>

            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                {{ title }}
            </h1>

            <p class="mt-4 text-base leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ message }}
            </p>

            <div class="mt-8 flex items-center justify-center gap-3">
                <Link
                    href="/"
                    class="rounded-md bg-neutral-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                >
                    {{ backLabel }}
                </Link>

                <a
                    href="/products"
                    class="rounded-md border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-800 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-900"
                >
                    Browse products
                </a>
            </div>
        </div>
    </main>
</template>
