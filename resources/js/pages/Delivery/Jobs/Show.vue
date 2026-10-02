<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DeliveryLayout from '@/layouts/DeliveryLayout.vue'
import DateDisplay from '@/components/Calendar/DateDisplay.vue'
import {
  ArrowLeft,
  Banknote,
  Calendar,
  Check,
  ClipboardCheck,
  Clock,
  Flag,
  MapPin,
  Phone,
  Play,
  StickyNote,
  User,
  X,
} from 'lucide-vue-next'

interface Booking {
  reference: string
  status: string
  service_name: string
  price_snapshot: string
  price_display: string
  amount_collected: string | null
  scheduled_date: string | null
  scheduled_slot: string
  customer_name: string
  customer_phone: string
  customer_email: string | null
  address: string | null
  city: string | null
  notes: string | null
  service: {
    name: string
    short_description: string | null
    includes: string[] | null
    excludes: string[] | null
  } | null
  events: {
    id: number
    from_status: string | null
    to_status: string | null
    note: string | null
    created_at: string
    actor: { id: number; name: string } | null
  }[]
}

const props = defineProps<{
  booking: Booking
  statusLabels: Record<string, string>
}>()

const booking = props.booking

const canStart = booking.status === 'assigned'
const canComplete = booking.status === 'in_progress'
const isDone = ['completed', 'cancelled'].includes(booking.status)

const showComplete = ref(false)
const showCancel = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})

const completeForm = ref({
  amount_collected: booking.amount_collected ?? booking.price_snapshot,
  note: '',
})

const cancelForm = ref({ reason: '' })

function forgetError(field: string) {
  if (errors.value[field]) {
    const { [field]: _removed, ...rest } = errors.value
    errors.value = rest
  }
}

function start() {
  busy.value = true
  errors.value = {}
  router.post(`/delivery/jobs/${booking.reference}/start`, {}, {
    onFinish: () => (busy.value = false),
  })
}

function complete() {
  busy.value = true
  errors.value = {}
  router.post(`/delivery/jobs/${booking.reference}/complete`, completeForm.value, {
    onSuccess: () => {
      showComplete.value = false
      completeForm.value.note = ''
    },
    onError: (bag) => (errors.value = bag as Record<string, string>),
    onFinish: () => (busy.value = false),
  })
}

function cancel() {
  busy.value = true
  errors.value = {}
  router.post(`/delivery/jobs/${booking.reference}/cancel`, cancelForm.value, {
    onSuccess: () => {
      showCancel.value = false
      cancelForm.value.reason = ''
    },
    onError: (bag) => (errors.value = bag as Record<string, string>),
    onFinish: () => (busy.value = false),
  })
}
</script>

<template>
  <Head :title="booking.service_name" />

  <DeliveryLayout>
    <div class="space-y-6">
      <Link
        href="/delivery/jobs"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft class="h-4 w-4" /> My jobs
      </Link>

      <header>
        <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ booking.service_name }}</h1>
        <p class="mt-1 font-mono text-sm text-muted-foreground">{{ booking.reference }}</p>
      </header>

      <p
        v-if="errors.error"
        class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm font-medium text-destructive"
      >
        {{ errors.error }}
      </p>

      <!-- What the customer is expecting, so the worker can see the whole job
           before knocking on the door. -->
      <section
        v-if="booking.service?.includes?.length || booking.service?.excludes?.length || booking.service?.short_description"
        class="rounded-xl border border-border bg-background p-4"
      >
        <h2 class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">The job</h2>
        <p v-if="booking.service?.short_description" class="text-sm text-foreground">
          {{ booking.service.short_description }}
        </p>
        <div v-if="booking.service?.includes?.length" class="mt-3">
          <p class="text-xs font-medium text-muted-foreground">Included</p>
          <ul class="mt-1 space-y-1 text-sm text-foreground">
            <li v-for="item in booking.service.includes" :key="item" class="flex items-start gap-2">
              <Check class="mt-0.5 h-3.5 w-3.5 flex-none text-success" /> {{ item }}
            </li>
          </ul>
        </div>
        <div v-if="booking.service?.excludes?.length" class="mt-3">
          <p class="text-xs font-medium text-muted-foreground">Not included</p>
          <ul class="mt-1 space-y-1 text-sm text-muted-foreground">
            <li v-for="item in booking.service.excludes" :key="item" class="flex items-start gap-2">
              <X class="mt-0.5 h-3.5 w-3.5 flex-none" /> {{ item }}
            </li>
          </ul>
        </div>
      </section>

      <section class="rounded-xl border border-border bg-background p-4">
        <h2 class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          Where to go
        </h2>
        <div class="space-y-2.5 text-sm">
          <p class="flex items-center gap-2.5">
            <User class="h-4 w-4 flex-none text-muted-foreground" />
            {{ booking.customer_name }}
          </p>
          <p class="flex items-start gap-2.5">
            <MapPin class="mt-0.5 h-4 w-4 flex-none text-muted-foreground" />
            <span>
              {{ booking.address || 'No address given' }}<span v-if="booking.city">, {{ booking.city }}</span>
            </span>
          </p>
          <p class="flex items-center gap-2.5">
            <Phone class="h-4 w-4 flex-none text-muted-foreground" />
            <a :href="`tel:${booking.customer_phone}`" class="font-medium text-primary">
              {{ booking.customer_phone }}
            </a>
          </p>
        </div>

        <div class="mt-4 border-t border-border pt-4">
          <a
            :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
              [booking.address, booking.city].filter(Boolean).join(', '),
            )}`"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-background px-4 py-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
          >
            <MapPin class="h-4 w-4" /> Open in maps
          </a>
        </div>
      </section>

      <section class="rounded-xl border border-border bg-background p-4">
        <h2 class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          When and how much
        </h2>
        <div class="space-y-2.5 text-sm">
          <p class="flex items-center gap-2.5">
            <Calendar class="h-4 w-4 flex-none text-muted-foreground" />
            <DateDisplay v-if="booking.scheduled_date" :value="booking.scheduled_date" />
            <span v-else>No date set</span>
          </p>
          <p class="flex items-center gap-2.5">
            <Clock class="h-4 w-4 flex-none text-muted-foreground" /> {{ booking.scheduled_slot }}
          </p>
          <p class="flex items-center gap-2.5 font-medium text-foreground">
            <Banknote class="h-4 w-4 flex-none text-muted-foreground" />
            Collect ${{ booking.price_snapshot }} on the day
          </p>
        </div>

        <p
          v-if="booking.notes"
          class="mt-4 flex items-start gap-2.5 rounded-lg bg-muted p-3 text-sm text-foreground"
        >
          <StickyNote class="mt-0.5 h-4 w-4 flex-none text-muted-foreground" />
          <span>Customer said: {{ booking.notes }}</span>
        </p>
      </section>

      <!-- One clear next step, never two, so a worker is never left choosing. -->
      <section v-if="!isDone" class="space-y-2">
        <button
          v-if="canStart"
          type="button"
          :disabled="busy"
          class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
          @click="start"
        >
          <Play class="h-5 w-5" /> I have started this job
        </button>

        <button
          v-if="canComplete"
          type="button"
          class="bg-success hover:bg-success/90 inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-4 text-base font-semibold text-white transition-colors"
          @click="showComplete = true"
        >
          <Check class="h-5 w-5" /> I have finished this job
        </button>

        <button
          v-if="canStart || canComplete"
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-destructive/30 bg-background px-4 py-3.5 text-sm font-semibold text-destructive transition-colors hover:bg-destructive/5"
          @click="showCancel = true"
        >
          <X class="h-4 w-4" /> I cannot do this job
        </button>
      </section>

      <!-- Finishing always asks for the cash, so money is never left blank. -->
      <section v-if="showComplete" class="rounded-xl border-2 border-success/40 bg-background p-4">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-foreground">
          <ClipboardCheck class="h-4 w-4 text-success" /> How much did you collect?
        </h2>
        <p class="mb-3 text-xs text-muted-foreground">
          The customer was told {{ booking.price_display }}. If you collected a different amount, put the real
          one here.
        </p>

        <form class="space-y-3" novalidate @submit.prevent="complete">
          <div>
            <label for="amount_collected" class="mb-1 block text-xs font-medium text-muted-foreground">
              Cash collected
            </label>
            <div class="relative">
              <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">$</span>
              <input
                id="amount_collected"
                v-model="completeForm.amount_collected"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                class="w-full rounded-lg border bg-background py-3 pr-3 pl-7 text-lg font-medium text-foreground focus:ring-2 focus:outline-none"
                :class="errors.amount_collected ? 'border-destructive' : 'border-border focus:border-success focus:ring-success/20'"
                @input="forgetError('amount_collected')"
              />
            </div>
            <p v-if="errors.amount_collected" class="mt-1 text-sm font-medium text-destructive">
              {{ errors.amount_collected }}
            </p>
          </div>

          <div>
            <label for="complete_note" class="mb-1 block text-xs font-medium text-muted-foreground">
              Note (optional)
            </label>
            <textarea
              id="complete_note"
              v-model="completeForm.note"
              rows="2"
              class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-success focus:ring-2 focus:ring-success/20 focus:outline-none"
              placeholder="Anything worth recording, e.g. extra part fitted."
            ></textarea>
          </div>

          <div class="flex gap-2">
            <button
              type="submit"
              :disabled="busy"
              class="bg-success hover:bg-success/90 inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold text-white transition-colors disabled:opacity-50"
            >
              <Check class="h-4 w-4" /> {{ busy ? 'Saving...' : 'Yes, job is finished' }}
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-3 text-sm font-medium text-muted-foreground hover:text-foreground"
              @click="showComplete = false"
            >
              Back
            </button>
          </div>
        </form>
      </section>

      <section v-if="showCancel" class="rounded-xl border-2 border-destructive/40 bg-background p-4">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Flag class="h-4 w-4 text-destructive" /> What went wrong?
        </h2>
        <p class="mb-3 text-xs text-muted-foreground">The office is told straight away, so somebody can sort it out.</p>

        <form class="space-y-3" novalidate @submit.prevent="cancel">
          <div>
            <label for="reason" class="mb-1 block text-xs font-medium text-muted-foreground">Reason</label>
            <input
              id="reason"
              v-model="cancelForm.reason"
              type="text"
              class="w-full rounded-lg border bg-background px-3 py-3 text-sm text-foreground focus:ring-2 focus:outline-none"
              :class="errors.reason ? 'border-destructive' : 'border-border focus:border-destructive focus:ring-destructive/20'"
              placeholder="Customer not home, part not available..."
              @input="forgetError('reason')"
            />
            <p v-if="errors.reason" class="mt-1 text-sm font-medium text-destructive">{{ errors.reason }}</p>
          </div>

          <div class="flex gap-2">
            <button
              type="submit"
              :disabled="busy"
              class="bg-destructive hover:bg-destructive/90 inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold text-white transition-colors disabled:opacity-50"
            >
              <X class="h-4 w-4" /> {{ busy ? 'Sending...' : 'Tell the office' }}
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-3 text-sm font-medium text-muted-foreground hover:text-foreground"
              @click="showCancel = false"
            >
              Back
            </button>
          </div>
        </form>
      </section>

      <section v-if="isDone" class="rounded-xl border border-border bg-background p-4">
        <h2 class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">This job is closed</h2>
        <p class="text-sm text-foreground">
          <span v-if="booking.status === 'completed'">Finished. The money has been recorded.</span>
          <span v-else>Marked as not done. The office has been told.</span>
        </p>
        <p v-if="booking.amount_collected" class="mt-2 flex items-center gap-2 text-sm font-medium text-foreground">
          <Banknote class="h-4 w-4 text-muted-foreground" /> Collected ${{ booking.amount_collected }}
        </p>
      </section>

      <section v-if="booking.events.length" class="rounded-xl border border-border bg-background p-4">
        <h2 class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">History</h2>
        <ol class="space-y-3">
          <li v-for="event in booking.events" :key="event.id" class="text-sm">
            <p class="text-foreground">
              <span v-if="event.from_status && event.to_status">
                {{ statusLabels[event.from_status] }} to {{ statusLabels[event.to_status] }}
              </span>
              <span v-else-if="event.to_status">{{ statusLabels[event.to_status] ?? event.to_status }}</span>
              <span v-else>Note added</span>
            </p>
            <p class="text-xs text-muted-foreground"><DateDisplay :value="event.created_at" time /></p>
            <p v-if="event.note" class="mt-0.5 text-sm text-muted-foreground">{{ event.note }}</p>
          </li>
        </ol>
      </section>
    </div>
  </DeliveryLayout>
</template>
