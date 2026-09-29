import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import axios from 'axios'
import en from '../../../lang/en.json'
import fa from '../../../lang/fa.json'
import ps from '../../../lang/ps.json'
import enStorefront from '../../../lang/en/storefront.json'
import faStorefront from '../../../lang/fa/storefront.json'
import psStorefront from '../../../lang/ps/storefront.json'
import enAdmin from '../../../lang/en/admin.json'
import faAdmin from '../../../lang/fa/admin.json'
import psAdmin from '../../../lang/ps/admin.json'

export interface LocaleOption {
    code: string
    name: string
    native_name: string | null
    direction: 'ltr' | 'rtl'
    is_default: boolean
}

const dictionaries: Record<string, Record<string, string>> = {
    en: { ...(en as Record<string, string>), ...(enStorefront as Record<string, string>), ...(enAdmin as Record<string, string>) },
    fa: { ...(fa as Record<string, string>), ...(faStorefront as Record<string, string>), ...(faAdmin as Record<string, string>) },
    ps: { ...(ps as Record<string, string>), ...(psStorefront as Record<string, string>), ...(psAdmin as Record<string, string>) },
}

export const SUPPORTED_LOCALES = Object.keys(dictionaries)

const RTL_LOCALES = ['fa', 'ps']

function interpolate(text: string, params?: Record<string, string | number>): string {
    if (!params) return text
    return text.replace(/\{(\w+)\}/g, (match, key: string) =>
        Object.prototype.hasOwnProperty.call(params, key) ? String(params[key]) : match,
    )
}

export const useI18nStore = defineStore('i18n', () => {
    const page = usePage()

    // Inertia assigns the page from inside the InertiaApp component's setup,
    // which runs when the app is mounted. This store is created earlier than
    // that -- app.ts calls it to install $t before mount -- so on the very
    // first read the page props are not there yet. Default to an empty object
    // rather than reading straight off undefined, and pick the real values up
    // via the watcher below as soon as the page arrives.
    const props = computed<Record<string, unknown>>(() => (page.props ?? {}) as Record<string, unknown>)

    const locale = ref<string>('fa')
    const locales = ref<LocaleOption[]>([])

    // Set once the visitor picks a language themselves, so a later page load
    // does not drag them back to the server's default.
    const chosenByVisitor = ref(false)

    const activeDict = computed(() => dictionaries[locale.value] ?? dictionaries.en ?? {})
    const fallbackDict = dictionaries.en ?? {}

    function applyDocument(lang: string, localeList: LocaleOption[]): void {
        if (typeof document === 'undefined') return
        const info = localeList.find((l: LocaleOption) => l.code === lang)
        const dir = info?.direction ?? (RTL_LOCALES.includes(lang) ? 'rtl' : 'ltr')
        document.documentElement.setAttribute('lang', lang)
        document.documentElement.setAttribute('dir', dir)
    }

    function syncFromPage(pageProps: Record<string, unknown>): void {
        const code = pageProps?.locale as string | undefined
        if (code && SUPPORTED_LOCALES.includes(code) && !chosenByVisitor.value) {
            locale.value = code
        }

        const list = pageProps?.locales as LocaleOption[] | undefined
        if (Array.isArray(list) && list.length > 0) {
            locales.value = list
        }

        applyDocument(locale.value, locales.value)
    }

    // immediate so the seeded 'fa' default is applied when the page never
    // carries a locale, and so a real locale is picked up the moment it lands.
    watch(props, syncFromPage, { immediate: true })

    function t(key: string, params?: Record<string, string | number>): string {
        const source = activeDict.value[key] ?? (fallbackDict as Record<string, string>)[key] ?? key
        return interpolate(source, params)
    }

    /**
     * Switch the active language. The preference is stored by the backend in a
     * long-lived cookie, then the page is reloaded so every server-rendered
     * string, the document direction and the locale state all line up.
     */
    async function setLocale(code: string): Promise<void> {
        if (!SUPPORTED_LOCALES.includes(code) || code === locale.value) return

        chosenByVisitor.value = true

        try {
            await axios.post(`/locale/${code}`)
        } catch {
            // Apply the language on the client even if the preference save fails.
        }

        locale.value = code
        applyDocument(code, locales.value)
        router.reload()
    }

    function label(code: string): string {
        const info = locales.value.find((l: LocaleOption) => l.code === code)
        return info?.native_name || info?.name || code
    }

    return { locale, locales, t, setLocale, label }
})