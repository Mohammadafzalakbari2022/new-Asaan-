<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import DeliveryLayout from '@/layouts/DeliveryLayout.vue'
import {
  Banknote,
  Calendar,
  CheckCircle2,
  ChevronRight,
  ClipboardList,
  Clock,
  MapPin,
  Phone,
  Wrench,
} from 'lucide-vue-next'

interface Job {
  reference: string
  status: string
  status_label: string
  service_name: string
  customer_name: string
  customer_phone: string
  scheduled_date: string | null
  scheduled_slot: string
  price_display: string
  address: string | null
  city: string | null
}

interface JobGroup {
  date: string
  label: string
  jobs: Job[]
}

const props = defineProps<{
  groups: JobGroup[]
  counts: { open: number; in_progress: number; completed_today: number }
}>()

const isToday = (date: string | null) => date === new Date().toISOString().slice(0, 10)
</script>

<template>
  <Head title="My Jobs" />

  <DeliveryLayout>
    <div class="space-y-8">
      <header>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">My jobs</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ props.counts.open }} job{{ props.counts.open === 1 ? '' : 's' }} assigned to you
        </p>
      </header>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-background p-4">
          <p class="text-xs text-muted-foreground">Still to do</p>
          <p class="mt-1 text-2xl font-bold text-foreground">{{ props.counts.open }}</p>
        </div>
        <div class="rounded-xl border border-border bg-background p-4">
          <p class="text-xs text-muted-foreground">On the job now</p>
          <p class="mt-1 text-2xl font-bold text-foreground">{{ props.counts.in_progress }}</p>
        </div>
        <div class="rounded-xl border border-border bg-background p-4">
          <p class="text-xs text-muted-foreground">Finished today</p>
          <p class="mt-1 text-2xl font-bold text-foreground">{{ props.counts.completed_today }}</p>
        </div>
      </div>

      <div v-if="props.groups.length === 0" class="rounded-xl border border-dashed border-border bg-background p-10 text-center">
        <Wrench class="mx-auto mb-3 h-10 w-10 text-muted-foreground/50" />
        <p class="text-sm text-muted-foreground">No jobs assigned to you yet. Check back soon.</p>
      </div>

      <!-- One group per day, in the order the work happens. -->
      <section v-for="group in props.groups" :key="group.date">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-muted-foreground uppercase tracking-wide">
          <Calendar class="h-4 w-4" />
          <span>{{ group.label }}</span>
          <span
            v-if="isToday(group.date)"
            class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium normal-case text-primary"
          >
            Today
          </span>
        </h2>

        <div class="space-y-3">
          <Link
            v-for="job in group.jobs"
            :key="job.reference"
            :href="`/delivery/jobs/${job.reference}`"
            class="block rounded-xl border border-border bg-background p-4 transition-shadow hover:shadow-md"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ job.service_name }}</p>
                <p class="truncate font-mono text-xs text-muted-foreground">{{ job.reference }}</p>
              </div>
              <span
                class="inline-flex flex-none items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="
                  job.status === 'in_progress'
                    ? 'bg-warning/10 text-warning'
                    : 'bg-primary/10 text-primary'
                "
              >
                <Clock v-if="job.status === 'in_progress'" class="h-3 w-3" />
                <ClipboardList v-else class="h-3 w-3" />
                {{ job.status_label }}
              </span>
            </div>

            <div class="mt-3 space-y-1.5 text-sm text-muted-foreground">
              <p class="flex items-center gap-2">
                <MapPin class="h-4 w-4 flex-none" />
                <span class="truncate">
                  {{ job.address || 'No address given' }}<span v-if="job.city">, {{ job.city }}</span>
                </span>
              </p>
              <p class="flex items-center gap-2">
                <Phone class="h-4 w-4 flex-none" /> {{ job.customer_phone }}
              </p>
              <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
                <p class="flex items-center gap-2">
                  <Clock class="h-4 w-4 flex-none" /> {{ job.scheduled_slot }}
                </p>
                <p class="flex items-center gap-2 font-medium text-foreground">
                  <Banknote class="h-4 w-4 flex-none" /> ${{ job.price_display }}
                </p>
              </div>
            </div>

            <p class="mt-3 flex items-center gap-0.5 text-xs font-medium text-primary">
              Open <ChevronRight class="h-3.5 w-3.5" />
            </p>
          </Link>
        </div>
      </section>

      <p v-if="props.counts.completed_today > 0" class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
        <CheckCircle2 class="h-4 w-4 text-success" />
        {{ props.counts.completed_today }} finished today. Thank you.
      </p>
    </div>
  </DeliveryLayout>
</template>
