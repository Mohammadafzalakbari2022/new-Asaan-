<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DeliveryLayout from '@/layouts/DeliveryLayout.vue'
import {
  Package,
  Phone,
  MapPin,
  Calendar,
  Banknote,
  ChevronRight,
  Truck,
} from 'lucide-vue-next'

interface DeliveryItem {
  id: number
  status: string
  status_badge: { label: string; class: string }
  priority: string
  shipment_number: string
  order_number: string | null
  customer_phone: string | null
  address_line: string | null
  city: string | null
  scheduled_date: string | null
  cod_amount: string | null
}

const props = defineProps<{
  active: DeliveryItem[]
  history: DeliveryItem[]
}>()

const activeCount = computed(() => props.active.length)
const highPriorityCount = computed(() => props.active.filter((d) => d.priority === 'high').length)
const codTotal = computed(() =>
  props.active.reduce((sum, d) => sum + parseFloat(d.cod_amount || '0'), 0),
)
</script>

<template>
  <Head title="My Deliveries" />

  <DeliveryLayout>
    <div class="space-y-8">
      <header>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">My deliveries</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ activeCount }} package{{ activeCount === 1 ? '' : 's' }} to sort out today
        </p>
      </header>

      <div v-if="activeCount" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-background p-4">
          <p class="text-xs text-muted-foreground">Today's packages</p>
          <p class="mt-1 text-2xl font-bold text-foreground">{{ activeCount }}</p>
        </div>
        <div class="rounded-xl border border-border bg-background p-4">
          <p class="text-xs text-muted-foreground">High priority</p>
          <p class="mt-1 text-2xl font-bold text-foreground">{{ highPriorityCount }}</p>
        </div>
        <div class="rounded-xl border border-border bg-background p-4">
          <p class="text-xs text-muted-foreground">Cash to collect</p>
          <p class="mt-1 text-2xl font-bold text-foreground">${{ codTotal.toFixed(2) }}</p>
        </div>
      </div>

      <section>
        <h2 class="mb-3 text-sm font-semibold text-muted-foreground uppercase tracking-wide">
          Assigned to you
        </h2>

        <div v-if="activeCount" class="space-y-3">
          <Link
            v-for="d in props.active"
            :key="d.id"
            :href="`/delivery/deliveries/${d.id}`"
            class="block rounded-xl border border-border bg-background p-4 transition-shadow hover:shadow-md"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ d.shipment_number }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ d.order_number }}</p>
              </div>
              <span
                :class="d.status_badge.class"
                class="inline-flex flex-none rounded-full px-2.5 py-0.5 text-xs font-medium"
              >
                {{ d.status_badge.label }}
              </span>
            </div>

            <div class="mt-3 space-y-1.5 text-sm text-muted-foreground">
              <p v-if="d.address_line" class="flex items-center gap-2">
                <MapPin class="h-4 w-4 flex-none" />
                <span class="truncate">{{ d.address_line }}</span>
              </p>
              <p v-if="d.customer_phone" class="flex items-center gap-2">
                <Phone class="h-4 w-4 flex-none" /> {{ d.customer_phone }}
              </p>
              <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
                <p v-if="d.scheduled_date" class="flex items-center gap-2">
                  <Calendar class="h-4 w-4 flex-none" /> {{ new Date(d.scheduled_date).toLocaleDateString() }}
                </p>
                <p v-if="d.cod_amount" class="flex items-center gap-2 font-medium text-foreground">
                  <Banknote class="h-4 w-4 flex-none" /> ${{ d.cod_amount }}
                </p>
              </div>
            </div>

            <p class="mt-3 flex items-center gap-0.5 text-xs font-medium text-primary">
              Open <ChevronRight class="h-3.5 w-3.5" />
            </p>
          </Link>
        </div>

        <div v-else class="rounded-xl border border-dashed border-border bg-background p-10 text-center">
          <Package class="mx-auto mb-3 h-10 w-10 text-muted-foreground/50" />
          <p class="text-sm text-muted-foreground">No packages assigned yet. Check back soon.</p>
        </div>
      </section>

      <section v-if="props.history.length">
        <h2 class="mb-3 text-sm font-semibold text-muted-foreground uppercase tracking-wide">
          Recent
        </h2>
        <div class="space-y-2">
          <Link
            v-for="d in props.history"
            :key="d.id"
            :href="`/delivery/deliveries/${d.id}`"
            class="flex items-center justify-between gap-3 rounded-xl border border-border bg-background p-4"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-muted">
                <Truck class="h-4 w-4 text-muted-foreground" />
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-foreground">{{ d.shipment_number }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ d.order_number }}</p>
              </div>
            </div>
            <span
              :class="d.status_badge.class"
              class="inline-flex flex-none rounded-full px-2.5 py-0.5 text-xs font-medium"
            >
              {{ d.status_badge.label }}
            </span>
          </Link>
        </div>
      </section>
    </div>
  </DeliveryLayout>
</template>
