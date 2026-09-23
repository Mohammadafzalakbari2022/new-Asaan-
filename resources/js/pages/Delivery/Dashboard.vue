<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import DeliveryLayout from '@/layouts/DeliveryLayout.vue'
import { PackageCheck, Truck, Clock3 } from 'lucide-vue-next'

interface Props {
  driver: {
    id: number
    name: string
    email: string
    phone: string | null
  }
}

defineProps<Props>()
</script>

<template>
  <DeliveryLayout>
    <Head title="Delivery Dashboard" />

    <div class="space-y-6">
      <!-- Welcome -->
      <div class="rounded-xl border border-border bg-card p-6">
        <h1 class="text-xl font-bold text-foreground">
          Welcome, {{ driver.name.split(' ')[0] }}
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Your assigned packages will appear here once the store assigns them to you.
        </p>
      </div>

      <!-- Placeholder stats -->
      <div class="grid grid-cols-3 gap-3">
        <div class="rounded-xl border border-border bg-card p-4">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-muted-foreground">New Assignments</span>
            <Clock3 class="w-4 h-4 text-muted-foreground" />
          </div>
          <p class="mt-2 text-2xl font-bold text-foreground">0</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-muted-foreground">Out for Delivery</span>
            <Truck class="w-4 h-4 text-muted-foreground" />
          </div>
          <p class="mt-2 text-2xl font-bold text-foreground">0</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-muted-foreground">Delivered</span>
            <PackageCheck class="w-4 h-4 text-muted-foreground" />
          </div>
          <p class="mt-2 text-2xl font-bold text-foreground">0</p>
        </div>
      </div>
    </div>
  </DeliveryLayout>
</template>