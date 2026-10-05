import { ref, onMounted } from 'vue'
import axios from 'axios'

export interface GridCategory {
    id: number
    name: string
    slug: string
    image_url: string | null
    products_count: number
}

/**
 * Shared logic composable for CategoriesGrid blocks.
 * All themes share the same data-fetching logic.
 * Only the presentation (template) differs per theme.
 */
export function useCategoriesGrid(settings: Record<string, unknown>) {
    const categories = ref<GridCategory[]>([])
    const loading    = ref(false)
    const error      = ref<string | null>(null)

    onMounted(async () => {
        loading.value = true
        try {
            const res = await axios.get('/api/shop/categories', {
                params: { limit: settings.limit ?? 8 },
            })
            categories.value = res.data.data ?? []
        } catch (e) {
            categories.value = []
            error.value = 'Failed to load categories'
            if (import.meta.env.DEV) console.error('[useCategoriesGrid]', e)
        } finally {
            loading.value = false
        }
    })

    /**
     * Column classes for the chosen column count.
     *
     * The class names are written out in full on purpose: Tailwind only ships the
     * classes it finds literally in the source, so building the name from a number
     * at runtime would leave phones with no small-screen rule and the tiles would
     * stay squeezed into the desktop column count. Same pattern as
     * BlogPostsGridBlock.
     */
    const COLS: Record<number, string> = {
        1: 'grid-cols-1',
        2: 'grid-cols-1 sm:grid-cols-2',
        3: 'grid-cols-2 sm:grid-cols-3',
        4: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
        5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        6: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
    }

    const colsClass = (cols: unknown) => {
        const n = Math.min(Math.max(Number(cols) || 4, 1), 6)
        return COLS[n]
    }

    return { categories, loading, error, colsClass }
}
