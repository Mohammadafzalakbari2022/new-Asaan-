<?php

namespace Cartxis\Shop\Http\Controllers;

use Cartxis\Shop\Services\ProductService;
use Cartxis\Shop\Services\CategoryService;
use Cartxis\Core\Services\ThemeViewResolver;
use Cartxis\Product\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * @var CategoryService
     */
    protected $categoryService;
    
    /**
     * @var ThemeViewResolver
     */
    protected $themeResolver;

    /**
     * Create a new controller instance.
     *
     * @param ProductService $productService
     * @param CategoryService $categoryService
     * @param ThemeViewResolver $themeResolver
     */
    public function __construct(
        ProductService $productService, 
        CategoryService $categoryService,
        ThemeViewResolver $themeResolver
    ) {
        $this->productService = $productService;
        $this->categoryService = $categoryService;
        $this->themeResolver = $themeResolver;
    }

    /**
     * Display product listing.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $perPage = $this->resolvePerPage($request->input('per_page'));
        $sort = $request->input('sort', config('shop.listing.default_sort', 'newest'));

        // Build filters array
        $filters = [
            'category' => $request->input('category'),
            'brand' => $request->input('brand'),
            'search' => $request->input('search'),
            'price_min' => $this->numericFilter($request->input('price_min')),
            'price_max' => $this->numericFilter($request->input('price_max')),
            'rating' => $this->numericFilter($request->input('rating')),
            'in_stock' => $request->input('in_stock'),
            'on_sale' => $request->boolean('on_sale') ?: null,
        ];

        // Get products with filters applied
        $products = $this->productService->getAllProducts($perPage, $sort, $filters);
        
        // Get all categories with actual product count from database
            $categories = \Cartxis\Product\Models\Category::withCount(['products' => function($query) {
                $query->where('status', 'enabled');
            }])
            ->where('status', 'enabled')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'products_count' => $category->products_count,
                ];
            });
        
        // Get all brands with product count
        $brands = Brand::withCount(['products' => function($query) {
                $query->where('status', 'enabled');
            }])
            ->where('status', true)
            ->orderBy('name')
            ->get()
            ->filter(function ($brand) {
                // Filtering here rather than with ->having(): there is no GROUP BY
                // on this query, and PostgreSQL rejects a HAVING clause that refers
                // to a select-list alias when the query is not a grouped one.
                return $brand->products_count > 0;
            })
            ->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'products_count' => $brand->products_count,
                ];
            })
            ->values();
        
        return Inertia::render($this->themeResolver->resolve('Products/Index'), [
            'products' => $products,
            'filters' => [
                'categories' => $categories,
                'brands' => $brands,
                'priceRange' => [
                    'min' => 0,
                    'max' => 1000,
                ],
            ],
            'activeFilters' => [
                'category' => $request->input('category'),
                'brand' => $request->input('brand'),
                'search' => $request->input('search'),
                'price_min' => $request->input('price_min'),
                'price_max' => $request->input('price_max'),
                'rating' => $request->input('rating'),
                'in_stock' => $request->input('in_stock'),
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * How many products a page should hold.
     *
     * The address bar is not trusted. A link that was shared, bookmarked or
     * hand-edited can carry a page size the shop does not offer, or one that is
     * not a number at all, and a value that is not a number reaches the database
     * as-is and comes back as a server error. A wrong page size is never worth a
     * broken page, so an unknown size is snapped to the nearest size the shop
     * does offer, and the caller tells the visitor which size was used.
     *
     * @param  mixed  $requested
     * @return int
     */
    protected function resolvePerPage($requested)
    {
        $default = (int) config('shop.listing.products_per_page', 12);

        $limits = array_values(array_filter(array_map(
            'intval',
            (array) config('shop.listing.available_limits', [])
        ), fn ($limit) => $limit > 0));

        // Nothing configured to snap to, so the only thing left to check is that
        // the number is a number at all.
        if ($limits === []) {
            return is_numeric($requested) && (int) $requested > 0 ? (int) $requested : $default;
        }

        if (is_numeric($requested) && in_array((int) $requested, $limits, true)) {
            return (int) $requested;
        }

        $wanted = is_numeric($requested) ? (int) $requested : $default;
        $nearest = $limits[0];
        $smallestGap = PHP_INT_MAX;

        foreach ($limits as $limit) {
            $gap = abs($limit - $wanted);

            if ($gap < $smallestGap) {
                $smallestGap = $gap;
                $nearest = $limit;
            }
        }

        return $nearest;
    }

    /**
     * A filter that reaches the database as a number, or nothing at all.
     *
     * The database is strict about this: on PostgreSQL, comparing a price
     * column against the text "abc" is an error, not a filter that matches
     * nothing. Anything that is not a number is treated as if it had not been
     * asked for, which is what the shopper sees when they clear the filter.
     *
     * @param  mixed  $value
     * @return float|null
     */
    protected function numericFilter($value)
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Display product detail page.
     *
     * @param string $slug
     * @return \Inertia\Response
     */
    public function show($slug)
    {
        $product = $this->productService->getProductBySlug($slug);

        if (!$product) {
            abort(404, 'Product not found');
        }

        $relatedProducts = $this->productService->getRelatedProducts($product->id);

        // Get configurable attributes if product has them
        $configurableAttributes = [];
        
        if ($product->has_configurable_attributes) {
            // Get unique attributes from attributeValues
            $attributes = $product->attributeValues()
                ->with(['attribute.options'])
                ->get()
                ->groupBy('attribute_id')
                ->map(function ($values) {
                    $firstValue = $values->first();
                    $attribute = $firstValue->attribute;
                    
                    return [
                        'id' => $attribute->id,
                        'label' => $attribute->name,
                        'code' => $attribute->code ?? strtolower(str_replace(' ', '_', $attribute->name)),
                        'type' => $attribute->type ?? 'select',
                        'is_required' => $attribute->is_required ?? true,
                        'options' => $attribute->options->map(function ($option) {
                            return [
                                'id' => $option->id,
                                'label' => $option->label,
                                'value' => $option->value,
                                'swatch_value' => $option->swatch_value ?? null,
                            ];
                        })->values()->toArray(),
                    ];
                })
                ->values()
                ->toArray();
            
            $configurableAttributes = $attributes;
        }

        // Map approvedReviews to reviews for frontend
        $productData = $product->toArray();
        $productData['reviews'] = collect($productData['approved_reviews'] ?? [])->map(function ($r) {
            return [
                'id' => $r['id'],
                'author' => $r['reviewer_name'] ?? 'Anonymous',
                'rating' => $r['rating'],
                'title' => $r['title'] ?? '',
                'comment' => $r['comment'] ?? '',
                'created_at' => $r['created_at'],
            ];
        })->values()->toArray();
        $productData['reviews_count'] = count($productData['reviews']);
        $productData['rating'] = count($productData['reviews']) > 0
            ? round(collect($productData['reviews'])->avg('rating'), 1)
            : 0;
        unset($productData['approved_reviews']);

        return Inertia::render($this->themeResolver->resolve('Products/Show'), [
            'product' => $productData,
            'relatedProducts' => $relatedProducts,
            'configurableAttributes' => $configurableAttributes,
            'seo' => [
                'title' => $product->name,
                'description' => $product->meta_description ?? substr(strip_tags($product->description), 0, 160),
                'keywords' => $product->meta_keywords ?? '',
            ],
        ]);
    }
    
    /**
     * Get product data for quick view modal.
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function quickView($slug)
    {
        $product = $this->productService->getProductBySlug($slug);

        if (!$product) {
            return response()->json([
                'error' => 'Product not found'
            ], 404);
        }

        // Get configurable attributes if product has them
        $configurableAttributes = [];
        
        if ($product->has_configurable_attributes) {
            // Get unique attributes from attributeValues
            $attributes = $product->attributeValues()
                ->with(['attribute.options'])
                ->get()
                ->groupBy('attribute_id')
                ->map(function ($values) {
                    $firstValue = $values->first();
                    $attribute = $firstValue->attribute;
                    
                    return [
                        'id' => $attribute->id,
                        'name' => $attribute->name,
                        'type' => $attribute->type ?? 'select',
                        'options' => $attribute->options->map(function ($option) {
                            return [
                                'id' => $option->id,
                                'value' => $option->value,
                                'color_code' => $option->color_code ?? null,
                            ];
                        })->values()->toArray(),
                    ];
                })
                ->values()
                ->toArray();
            
            $configurableAttributes = $attributes;
        }

        return response()->json([
            'product' => $product,
            'configurableAttributes' => $configurableAttributes,
        ]);
    }
}
