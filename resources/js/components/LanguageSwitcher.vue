<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18nStore } from '@/Stores/i18n'
import { hasUnsavedChanges } from '@/lib/unsavedGuard'
import { Check, ChevronDown, Languages } from 'lucide-vue-next'

const i18n = useI18nStore()
const t = i18n.t

const open = ref(false)

function toggle(): void {
    open.value = !open.value
}

function choose(code: string): void {
    if (code === i18n.locale) {
        open.value = false
        return
    }

    if (hasUnsavedChanges() && !window.confirm(t('You have unsaved changes. Change language anyway?'))) {
        return
    }

    open.value = false
    void i18n.setLocale(code)
}

function onClickOutside(event: MouseEvent): void {
    const target = event.target as HTMLElement
    if (!target.closest('.language-switcher')) {
        open.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside)
})
</script>

<template>
    <div class="language-switcher relative">
        <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 transition-colors"
            :aria-label="t('Change Language')"
            :title="t('Change Language')"
            @click="toggle"
        >
            <Languages class="h-4 w-4 flex-shrink-0" />
            <span class="hidden min-[400px]:inline">{{ i18n.label(i18n.locale) }}</span>
            <ChevronDown class="h-3.5 w-3.5 text-gray-400" :class="{ 'rotate-180': open }" />
        </button>

        <Transition name="language-menu-fade">
            <div
                v-if="open"
                class="language-menu absolute end-0 z-[300] mt-2 min-w-[10rem] overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
            >
                <button
                    v-for="localeOption in i18n.locales"
                    :key="localeOption.code"
                    type="button"
                    class="flex w-full items-center justify-between px-3.5 py-2 text-start text-sm hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    :class="localeOption.code === i18n.locale
                        ? 'font-semibold text-gray-900 dark:text-gray-100'
                        : 'text-gray-600 dark:text-gray-300'"
                    @click="choose(localeOption.code)"
                >
                    <span>{{ localeOption.native_name || localeOption.name }}</span>
                    <Check v-if="localeOption.code === i18n.locale" class="h-4 w-4 text-green-600" />
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.language-menu-fade-enter-active,
.language-menu-fade-leave-active {
    transition: opacity 0.15s ease;
}
.language-menu-fade-enter-from,
.language-menu-fade-leave-to {
    opacity: 0;
}
</style>