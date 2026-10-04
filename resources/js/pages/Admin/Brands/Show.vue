<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import DateDisplay from '@/components/Calendar/DateDisplay.vue';
import * as brandRoutes from '@/routes/admin/catalog/brands';
import { useCurrency } from '@/composables/useCurrency';

const { formatPrice } = useCurrency();

interface Product {
  id: number;
  name: string;
  sku: string;
  price: string;
  status: string;
}

interface Brand {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  logo: string | null;
  website: string | null;
  status: boolean;
  featured: boolean;
  meta_title: string | null;
  meta_description: string | null;
  meta_keywords: string | null;
  products_count: number;
  created_at: string;
  updated_at: string;
}

interface Props {
  brand: Brand;
  products: Product[];
}

const props = defineProps<Props>();
</script>

<template>
  <Head :title="$t('Brand: {name}', { name: brand.name })" />
  <AdminLayout :title="$t('Brand: {name}', { name: brand.name })">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-200 font-bold">{{ brand.name }}</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $t('Brand details and products') }}</p>
          </div>
          <div class="flex items-center space-x-3">
            <Link 
              :href="brandRoutes.edit({ brand: brand.id }).url"
              class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              {{ $t('Edit') }}
            </Link>
            <Link 
              :href="brandRoutes.index().url"
              class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              {{ $t('Back to Brands') }}
            </Link>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Brand Details -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ $t('Brand Information') }}</h2>
            
            <div class="space-y-4">
              <!-- Logo -->
              <div v-if="brand.logo" class="flex items-center space-x-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex-shrink-0">
                  <img :src="`/storage/${brand.logo}`" :alt="brand.name" class="w-24 h-24 object-contain border border-gray-200 dark:border-gray-700 rounded-md" />
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $t('Brand Logo') }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ brand.logo }}</p>
                </div>
              </div>

              <!-- Description -->
              <div v-if="brand.description" class="border-b border-gray-200 dark:border-gray-700 pb-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Description') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ brand.description }}</p>
              </div>

              <!-- Details Grid -->
              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Slug') }}</dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ brand.slug }}</dd>
                </div>

                <div v-if="brand.website">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Website') }}</dt>
                  <dd class="mt-1 text-sm">
                    <a :href="brand.website" target="_blank" class="text-blue-600 hover:text-blue-800">
                      {{ brand.website }}
                      <svg class="inline w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                      </svg>
                    </a>
                  </dd>
                </div>

                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Status') }}</dt>
                  <dd class="mt-1">
                    <span v-if="brand.status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">
                      {{ $t('Active') }}
                    </span>
                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                      {{ $t('Inactive') }}
                    </span>
                  </dd>
                </div>

                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Featured') }}</dt>
                  <dd class="mt-1">
                    <span v-if="brand.featured" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300">
                      {{ $t('Yes') }}
                    </span>
                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                      {{ $t('No') }}
                    </span>
                  </dd>
                </div>

                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Products Count') }}</dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ brand.products_count }}</dd>
                </div>

                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Created') }}</dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100"><DateDisplay :value="brand.created_at" /></dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- SEO Information -->
          <div v-if="brand.meta_title || brand.meta_description || brand.meta_keywords" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ $t('SEO Information') }}</h2>
            
            <dl class="space-y-4">
              <div v-if="brand.meta_title">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Meta Title') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ brand.meta_title }}</dd>
              </div>

              <div v-if="brand.meta_description">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Meta Description') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ brand.meta_description }}</dd>
              </div>

              <div v-if="brand.meta_keywords">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Meta Keywords') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ brand.meta_keywords }}</dd>
              </div>
            </dl>
          </div>

          <!-- Products -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ $t('Products ({count})', { count: products.length }) }}</h2>
            
            <div v-if="products.length > 0" class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('SKU') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Price') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Status') }}</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="product in products" :key="product.id">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ product.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                      {{ product.sku }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                      {{ formatPrice(product.price) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span v-if="product.status === 'enabled'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">
                        {{ $t('Enabled') }}
                      </span>
                      <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                        {{ $t('Disabled') }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
              </svg>
              <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('No products found for this brand') }}</p>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Quick Stats -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Quick Stats') }}</h3>
            <dl class="space-y-3">
              <div class="flex justify-between items-center">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('Total Products') }}</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ brand.products_count }}</dd>
              </div>
            </dl>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('Quick Actions') }}</h3>
            <div class="space-y-2">
              <Link 
                :href="brandRoutes.edit({ brand: brand.id }).url"
                class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                {{ $t('Edit Brand') }}
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
