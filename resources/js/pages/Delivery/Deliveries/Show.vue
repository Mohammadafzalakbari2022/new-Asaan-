<script setup lang="ts">
import DeliveryLiveMapPanel from '@/components/Delivery/DeliveryLiveMapPanel.vue';
import DeliveryLayout from '@/layouts/DeliveryLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Banknote,
    Calendar,
    Check,
    Flag,
    MapPin,
    Phone,
    Truck,
    User,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface EventItem {
    id: number;
    status_change: string;
    note: string | null;
    created_at: string;
}

interface DeliveryDetail {
    id: number;
    status: string;
    status_badge: { label: string; class: string };
    priority: string;
    shipment_number: string;
    order_number: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    cod_amount: string | null;
    cod_received: string | null;
    scheduled_date: string | null;
    notes: string | null;
    recipient_name: string | null;
    delivered_photo_path: string | null;
    failure_reason: string | null;
    failure_note: string | null;
    address: {
        full_name: string;
        full_address: string;
        phone: string;
        latitude: number | null;
        longitude: number | null;
    } | null;
    events: EventItem[];
}

interface FailureReason {
    value: string;
    label: string;
}

const props = defineProps<{
    delivery: DeliveryDetail;
    failureReasons: FailureReason[];
}>();

const showDeliver = ref(false);
const showUndelivered = ref(false);
const delivering = ref(false);

const deliverForm = ref({
    recipient_name: props.delivery.address?.full_name || '',
    cod_received: props.delivery.cod_amount || '',
    note: '',
});

const undeliveredForm = ref({
    failure_reason: '',
    failure_note: '',
});

const canStart =
    props.delivery.status === 'assigned' ||
    props.delivery.status === 'in_progress';
const canArriving = props.delivery.status === 'out_for_delivery';
const canDeliver =
    props.delivery.status === 'out_for_delivery' ||
    props.delivery.status === 'arriving';
const isDone =
    props.delivery.status === 'delivered' ||
    props.delivery.status === 'undelivered';

const submitDeliver = () => {
    delivering.value = true;
    router.post(
        `/delivery/deliveries/${props.delivery.id}/deliver`,
        deliverForm.value,
        {
            onFinish: () => {
                delivering.value = false;
            },
        },
    );
};

const submitUndelivered = () => {
    delivering.value = true;
    router.post(
        `/delivery/deliveries/${props.delivery.id}/undelivered`,
        undeliveredForm.value,
        {
            onFinish: () => {
                delivering.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Delivery detail" />

    <DeliveryLayout>
        <div class="space-y-6">
            <Link
                href="/delivery/deliveries"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4" /> All deliveries
            </Link>

            <header class="flex items-start justify-between gap-3">
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        {{ props.delivery.shipment_number }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ props.delivery.order_number }}
                    </p>
                </div>
                <span
                    :class="props.delivery.status_badge.class"
                    class="inline-flex flex-none rounded-full px-2.5 py-0.5 text-xs font-medium"
                >
                    {{ props.delivery.status_badge.label }}
                </span>
            </header>

            <section class="rounded-xl border border-border bg-background p-4">
                <h2
                    class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Customer
                </h2>
                <div class="space-y-2 text-sm">
                    <p
                        v-if="props.delivery.address?.full_name"
                        class="flex items-center gap-2.5"
                    >
                        <User class="h-4 w-4 flex-none text-muted-foreground" />
                        {{ props.delivery.address.full_name }}
                    </p>
                    <p
                        v-if="props.delivery.address?.full_address"
                        class="flex items-start gap-2.5"
                    >
                        <MapPin
                            class="mt-0.5 h-4 w-4 flex-none text-muted-foreground"
                        />
                        {{ props.delivery.address.full_address }}
                    </p>
                    <p
                        v-if="
                            props.delivery.address?.phone ||
                            props.delivery.customer_phone
                        "
                        class="flex items-center gap-2.5"
                    >
                        <Phone
                            class="h-4 w-4 flex-none text-muted-foreground"
                        />
                        {{
                            props.delivery.address?.phone ||
                            props.delivery.customer_phone
                        }}
                    </p>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 pt-1">
                        <p
                            v-if="props.delivery.scheduled_date"
                            class="flex items-center gap-2.5"
                        >
                            <Calendar
                                class="h-4 w-4 flex-none text-muted-foreground"
                            />
                            {{
                                new Date(
                                    props.delivery.scheduled_date,
                                ).toLocaleDateString()
                            }}
                        </p>
                        <p
                            v-if="props.delivery.cod_amount"
                            class="flex items-center gap-2.5 font-medium text-foreground"
                        >
                            <Banknote
                                class="h-4 w-4 flex-none text-muted-foreground"
                            />
                            COD ${{ props.delivery.cod_amount }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Live map -->
            <DeliveryLiveMapPanel
                :delivery-id="props.delivery.id"
                :latitude="props.delivery.address?.latitude ?? null"
                :longitude="props.delivery.address?.longitude ?? null"
                :directions-query="
                    props.delivery.address?.full_address ??
                    props.delivery.order_number
                "
            />

            <!-- Proof of delivery -->
            <section
                v-if="isDone && props.delivery.recipient_name"
                class="rounded-xl border border-border bg-background p-4"
            >
                <h2
                    class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Proof of delivery
                </h2>
                <div class="flex items-center gap-3 text-sm">
                    <div
                        class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-primary/10"
                    >
                        <Check class="h-4 w-4 text-primary" />
                    </div>
                    <p class="text-foreground">
                        {{ props.delivery.recipient_name }}
                    </p>
                    <p
                        v-if="props.delivery.cod_received"
                        class="ml-auto font-medium text-foreground"
                    >
                        ${{ props.delivery.cod_received }}
                    </p>
                </div>
            </section>

            <!-- Undelivered reason -->
            <section
                v-if="
                    props.delivery.status === 'undelivered' &&
                    props.delivery.failure_reason
                "
                class="rounded-xl border border-border bg-background p-4"
            >
                <h2
                    class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Not delivered
                </h2>
                <p class="text-sm font-medium text-foreground">
                    {{ props.delivery.failure_reason }}
                </p>
                <p
                    v-if="props.delivery.failure_note"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ props.delivery.failure_note }}
                </p>
            </section>

            <!-- Actions -->
            <section v-if="!isDone" class="space-y-2">
                <button
                    v-if="canStart"
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                    @click="
                        router.post(
                            `/delivery/deliveries/${props.delivery.id}/start`,
                        )
                    "
                >
                    <Truck class="h-4 w-4" /> Start delivery
                </button>

                <button
                    v-if="canArriving"
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-background px-4 py-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                    @click="
                        router.post(
                            `/delivery/deliveries/${props.delivery.id}/arriving`,
                        )
                    "
                >
                    <Flag class="h-4 w-4" /> I'll arrive today
                </button>

                <div v-if="canDeliver" class="space-y-2">
                    <button
                        type="button"
                        class="bg-success hover:bg-success/90 inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white transition-colors"
                        @click="showDeliver = true"
                    >
                        <Check class="h-4 w-4" /> Delivered
                    </button>

                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-destructive/30 bg-background px-4 py-3 text-sm font-semibold text-destructive transition-colors hover:bg-destructive/5"
                        @click="showUndelivered = true"
                    >
                        <X class="h-4 w-4" /> Couldn't deliver
                    </button>
                </div>
            </section>

            <!-- Deliver form -->
            <section
                v-if="showDeliver"
                class="rounded-xl border border-border bg-background p-4"
            >
                <h2 class="mb-3 text-sm font-semibold text-foreground">
                    Confirm delivery
                </h2>
                <form class="space-y-3" @submit.prevent="submitDeliver">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-muted-foreground"
                            for="recipient_name"
                        >
                            Recipient
                        </label>
                        <input
                            id="recipient_name"
                            v-model="deliverForm.recipient_name"
                            type="text"
                            required
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-muted-foreground"
                            for="cod_received"
                        >
                            Cash received (COD)
                        </label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >$</span
                            >
                            <input
                                id="cod_received"
                                v-model="deliverForm.cod_received"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full rounded-lg border border-border bg-background py-2 pr-3 pl-7 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                            />
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Amount the customer handed over, if COD.
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-muted-foreground"
                            for="note"
                            >Note (optional)</label
                        >
                        <textarea
                            id="note"
                            v-model="deliverForm.note"
                            rows="2"
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                            placeholder="Anything worth recording, e.g. handed to the neighbour."
                        ></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="delivering"
                            class="bg-success hover:bg-success/90 inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        >
                            <Check class="h-4 w-4" /> Confirm delivered
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                            @click="showDeliver = false"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </section>

            <!-- Undelivered form -->
            <section
                v-if="showUndelivered"
                class="rounded-xl border border-border bg-background p-4"
            >
                <h2 class="mb-3 text-sm font-semibold text-foreground">
                    Why couldn't you deliver?
                </h2>
                <form class="space-y-3" @submit.prevent="submitUndelivered">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-muted-foreground"
                            for="failure_reason"
                        >
                            Reason
                        </label>
                        <select
                            id="failure_reason"
                            v-model="undeliveredForm.failure_reason"
                            required
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                        >
                            <option value="" disabled>Select a reason</option>
                            <option
                                v-for="r in props.failureReasons"
                                :key="r.value"
                                :value="r.value"
                            >
                                {{ r.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-muted-foreground"
                            for="failure_note"
                        >
                            Details (optional)
                        </label>
                        <textarea
                            id="failure_note"
                            v-model="undeliveredForm.failure_note"
                            rows="2"
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                            placeholder="Did the line busy, gate closed, customer unreachable?"
                        ></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="delivering"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-destructive px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-destructive/90 disabled:opacity-50"
                        >
                            <X class="h-4 w-4" /> Mark as undelivered
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                            @click="showUndelivered = false"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </DeliveryLayout>
</template>
