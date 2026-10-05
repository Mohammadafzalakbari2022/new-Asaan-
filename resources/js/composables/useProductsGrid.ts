import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useCurrency } from './useCurrency'
import { useCartStore } from '@/Stores/cartStore'

export interface GridProduct {
    id: number
    name: string
    slug: string
    price: number
    thumbnail: string | null
    formatted_price?: string
}

/**
 * Shared logic composable for ProductsGrid blocks.
 * All themes share the same data-fetching and cart interaction logic.
 * Only the presentation (template) differs per theme.
 */
export function useProductsGrid(
    settings: Record<string, unknown>,
    editorMode?: boolean,
) {
    const products     = ref<GridProduct[]>([])
    const loading      = ref(false)
    const error        = ref<string | null>(null)
    const addingToCart = ref<Record<number, boolean>>({})
    const addedToCart  = ref<Record<number, boolean>>({})

    const { formatPrice } = useCurrency()
    const cartStore = useCartStore()

    onMounted(async () => {
        const source = (settings.source as string) || 'manual'
        const limit  = settings.limit ?? 8
        loading.value = true
        try {
            if (source === 'featured') {
                const res = await axios.get('/api/shop/products/featured', { params: { limit } })
                products.value = res.data.data ?? []
            } else if (source === 'latest') {
                const res = await axios.get('/api/shop/products/latest', { params: { limit } })
                products.value = res.data.data ?? []
            } else {
                // manual: explicit product IDs
                const ids = settings.product_ids as number[]
                if (!ids?.length) return
                const url = editorMode
                    ? route('admin.uieditor.products.search')
                    : '/api/shop/products/by-ids'
                const res = await axios.get(url, {
                    params: { ids: ids.join(','), per_page: limit },
                })
                products.value = res.data.data ?? []
            }
        } catch (e) {
            products.value = []
            error.value = 'Failed to load products'
            if (import.meta.env.DEV) console.error('[useProductsGrid]', e)
        } finally {
            loading.value = false
        }
    })

    const handleAddToCart = async (product: GridProduct) => {
        if (editorMode || addingToCart.value[product.id]) return
        addingToCart.value[product.id] = true
        const result = await cartStore.addToCart(product.id, 1)
        addingToCart.value[product.id] = false
        if (result?.success) {
            addedToCart.value[product.id] = true
            setTimeout(() => { addedToCart.value[product.id] = false }, 2000)
        }
    }

    /**
     * Column classes for the chosen column count.
     *
     * The class names are written out in full on purpose: Tailwind only ships
     * the classes it finds literally in the source, so building the name from a
     * number at runtime would leave phones with no small-screen rule and the
     * grid would stay at its desktop column count. Same pattern as
     * BlogPostsGridBlock.
     */
    const COLS: Record<number, string> = {
        1: 'grid-cols-1',
        2: 'grid-cols-1 sm:grid-cols-2',
        3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        4: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
        5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        6: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
    }

    const colsClass = (cols: unknown) => {
        const n = Math.min(Math.max(Number(cols) || 4, 1), 6)
        return COLS[n]
    }

    return {
        products,
        loading,
        error,
        addingToCart,
        addedToCart,
        formatPrice,
        handleAddToCart,
        colsClass,
    }
}
