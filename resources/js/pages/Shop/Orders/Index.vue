<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CountBadge from '../../../components/CountBadge.vue';
import ShopOrderView from '../../../components/ShopOrderView.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    orders: {
        type: Array,
        required: true,
    },
    preorders: {
        type: Array,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
    schedules: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const preordersOpen = ref(false);
const schedulesOpen = ref(false);
const statusTab = ref(props.statuses[0]?.value ?? '');
const selectedOrderId = ref(null);
const visibleOrders = computed(() => props.orders.filter((order) => order.status === statusTab.value));
const selectedOrder = computed(() => props.orders.find((order) => order.id === selectedOrderId.value) ?? null);
const fieldClass = 'h-10 w-full rounded-xl border border-mist bg-white px-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5';
const weekdayOptions = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];
const scheduleForm = useForm({
    branch_id: '',
    kind: 'date',
    scheduled_on: '',
    frequency: 'daily',
    weekdays: [],
    slots: [{ time: '' }],
});

function ordersInStatus(status) {
    return props.orders.filter((order) => order.status === status).length;
}

function selectOrder(order) {
    selectedOrderId.value = order.id;
}

function createSchedule() {
    scheduleForm.post('/backoffice/shop/delivery-schedules', {
        preserveScroll: true,
        preserveState: true,
        only: ['schedules', 'flash'],
        onSuccess: () => scheduleForm.reset(),
    });
}

function addSlot() {
    scheduleForm.slots = [...scheduleForm.slots, { time: '' }];
}

function removeSlot(index) {
    scheduleForm.slots = scheduleForm.slots.filter((_, slotIndex) => slotIndex !== index);
}

function toggleWeekday(day) {
    const selected = scheduleForm.weekdays.includes(day)
        ? scheduleForm.weekdays.filter((value) => value !== day)
        : [...scheduleForm.weekdays, day];

    scheduleForm.weekdays = selected.sort((left, right) => left - right);
}

function setScheduleActive(schedule, isActive) {
    router.put(`/backoffice/shop/delivery-schedules/${schedule.id}`, { is_active: isActive }, {
        preserveScroll: true,
        preserveState: true,
        only: ['schedules', 'flash'],
    });
}

function openAssignedOrder(assigned) {
    schedulesOpen.value = false;
    const order = props.orders.find((item) => item.id === assigned.id);

    if (!order) {
        return;
    }

    statusTab.value = order.status;
    selectOrder(order);
}
</script>

<template>
    <Head title="Manage Orders" />

    <Teleport defer to="#admin-header-actions">
        <button
            class="inline-flex h-10 shrink-0 items-center rounded-xl bg-pine px-3 text-sm font-medium text-foam"
            type="button"
            @click="preordersOpen = true"
        >
            For Pre-Order
        </button>
        <button
            class="inline-flex h-10 shrink-0 items-center rounded-xl border border-mist bg-white px-3 text-sm font-medium dark:border-white/15 dark:bg-transparent"
            type="button"
            @click="schedulesOpen = true"
        >
            Manage Delivery Schedules
        </button>
    </Teleport>

    <Teleport defer to="#admin-header-subnav">
        <div class="flex w-max gap-2" role="tablist" aria-label="Order status">
            <button
                v-for="status in statuses"
                :id="`orders-tab-${status.value}`"
                :key="status.value"
                class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="statusTab === status.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="statusTab === status.value"
                @click="statusTab = status.value"
            >
                {{ status.label }}
                <CountBadge :count="ordersInStatus(status.value)" />
            </button>
        </div>
    </Teleport>

    <div class="flex min-h-0 w-full flex-1 flex-col gap-4">
        <p v-if="flash.status" class="shrink-0 rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>

        <div class="grid min-h-0 flex-1 grid-rows-[minmax(16rem,0.8fr)_minmax(0,1.2fr)] gap-4 lg:grid-cols-[minmax(18rem,24rem)_minmax(0,1fr)] lg:grid-rows-1">
            <section class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5" aria-labelledby="order-list-heading">
                <h2 id="order-list-heading" class="border-b border-mist px-4 py-3 text-sm font-medium dark:border-white/10">Order</h2>
                <div class="min-h-0 flex-1 overflow-y-auto" role="tabpanel" :aria-labelledby="`orders-tab-${statusTab}`">
                    <p v-if="visibleOrders.length === 0" class="px-4 py-8 text-sm text-ink/60 dark:text-foam/60">No orders with this status.</p>
                    <button
                        v-for="listed in visibleOrders"
                        :key="listed.id"
                        class="flex w-full flex-col gap-1 border-b border-mist px-4 py-3 text-left last:border-b-0 dark:border-white/10"
                        :class="selectedOrderId === listed.id ? 'bg-pine/10 dark:bg-white/10' : 'hover:bg-black/[0.03] dark:hover:bg-white/[0.04]'"
                        type="button"
                        :aria-current="selectedOrderId === listed.id ? 'true' : undefined"
                        @click="selectOrder(listed)"
                    >
                        <span class="font-medium">{{ listed.number }}</span>
                        <span class="text-sm">{{ listed.customer }}</span>
                        <span class="text-xs text-ink/50 dark:text-foam/50">{{ listed.branch }} · {{ listed.placed_at }}</span>
                        <span class="text-xs">{{ listed.total_label }} · {{ listed.status_label }}</span>
                        <span v-if="listed.schedule" class="text-xs text-ink/60 dark:text-foam/60">{{ listed.schedule }}</span>
                    </button>
                </div>
            </section>

            <section class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-mist bg-foam dark:border-white/10 dark:bg-ink" aria-labelledby="order-view-heading">
                <h2 id="order-view-heading" class="border-b border-mist bg-white px-4 py-3 text-sm font-medium dark:border-white/10 dark:bg-white/5">Order View</h2>
                <div class="min-h-0 flex-1 overflow-auto p-4">
                    <p v-if="!selectedOrder" class="py-12 text-center text-sm text-ink/60 dark:text-foam/60">Select an order.</p>
                    <ShopOrderView v-else :order="selectedOrder" :schedules="schedules" :statuses="statuses" />
                </div>
            </section>
        </div>
    </div>

    <div v-if="preordersOpen" class="fixed inset-0 z-50 flex bg-ink/50 p-3 sm:p-4">
        <div class="flex h-full w-full flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-4 sm:p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">For Pre-Order</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">Pre-order items on orders that are not completed yet.</p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <a
                        class="inline-flex h-10 items-center rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                        href="/backoffice/shop/orders/preorders/export"
                    >
                        Export
                    </a>
                    <button class="text-sm font-medium" type="button" @click="preordersOpen = false">Close</button>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-auto rounded-2xl border border-mist dark:border-white/10">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Order</th>
                            <th class="px-4 py-3 font-medium">Branch</th>
                            <th class="px-4 py-3 font-medium">Brand</th>
                            <th class="px-4 py-3 font-medium">Category</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Variant</th>
                            <th class="px-4 py-3 font-medium">Qty</th>
                            <th class="px-4 py-3 font-medium">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="preorders.length === 0">
                            <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="8">No pre-orders on open orders.</td>
                        </tr>
                        <tr v-for="(line, index) in preorders" :key="`${line.order_number}-${index}`" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="px-4 py-3 font-medium">{{ line.order_number }}</td>
                            <td class="px-4 py-3">{{ line.branch || '—' }}</td>
                            <td class="px-4 py-3">{{ line.brand || '—' }}</td>
                            <td class="px-4 py-3">{{ line.category || '—' }}</td>
                            <td class="px-4 py-3">{{ line.name }}</td>
                            <td class="px-4 py-3">{{ line.variant || '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ line.quantity }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ line.price_label }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div v-if="schedulesOpen" class="fixed inset-0 z-50 flex bg-ink/50 p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="delivery-schedules-title">
        <div class="flex h-full w-full flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-4 sm:p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="delivery-schedules-title" class="text-lg font-medium">Manage Delivery Schedules</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">Add times for one date or a repeating frequency, for a branch. Closing a slot keeps the orders already on it.</p>
                </div>
                <button class="text-sm font-medium" type="button" @click="schedulesOpen = false">Close</button>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(20rem,28rem)_minmax(0,1fr)]">
            <form class="flex min-h-0 flex-col gap-4 overflow-auto rounded-2xl border border-mist bg-white p-4 dark:border-white/10 dark:bg-white/5" @submit.prevent="createSchedule">
                <fieldset class="flex flex-col gap-2">
                    <legend class="text-sm font-medium">Schedule</legend>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Schedule type">
                        <button
                            class="inline-flex h-10 items-center rounded-xl px-3 text-sm font-medium"
                            :class="scheduleForm.kind === 'date' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                            type="button"
                            role="radio"
                            :aria-checked="scheduleForm.kind === 'date'"
                            @click="scheduleForm.kind = 'date'"
                        >
                            By date
                        </button>
                        <button
                            class="inline-flex h-10 items-center rounded-xl px-3 text-sm font-medium"
                            :class="scheduleForm.kind === 'frequency' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                            type="button"
                            role="radio"
                            :aria-checked="scheduleForm.kind === 'frequency'"
                            @click="scheduleForm.kind = 'frequency'"
                        >
                            By frequency
                        </button>
                    </div>
                    <p v-if="scheduleForm.errors.kind" class="text-sm text-danger">{{ scheduleForm.errors.kind }}</p>
                </fieldset>

                <label v-if="scheduleForm.kind === 'date'" class="flex flex-col gap-1 text-sm" for="schedule-date">
                    <span class="font-medium">Date</span>
                    <input id="schedule-date" v-model="scheduleForm.scheduled_on" :class="fieldClass" required type="date" />
                    <span v-if="scheduleForm.errors.scheduled_on" class="text-danger">{{ scheduleForm.errors.scheduled_on }}</span>
                </label>

                <fieldset v-else class="flex flex-col gap-3">
                    <legend class="text-sm font-medium">Frequency</legend>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Frequency">
                        <button
                            class="inline-flex h-10 items-center rounded-xl px-3 text-sm font-medium"
                            :class="scheduleForm.frequency === 'daily' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                            type="button"
                            role="radio"
                            :aria-checked="scheduleForm.frequency === 'daily'"
                            @click="scheduleForm.frequency = 'daily'"
                        >
                            Daily
                        </button>
                        <button
                            class="inline-flex h-10 items-center rounded-xl px-3 text-sm font-medium"
                            :class="scheduleForm.frequency === 'weekly' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                            type="button"
                            role="radio"
                            :aria-checked="scheduleForm.frequency === 'weekly'"
                            @click="scheduleForm.frequency = 'weekly'"
                        >
                            Weekly
                        </button>
                    </div>
                    <p v-if="scheduleForm.errors.frequency" class="text-sm text-danger">{{ scheduleForm.errors.frequency }}</p>
                    <div v-if="scheduleForm.frequency === 'weekly'" class="flex flex-col gap-2">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="day in weekdayOptions"
                                :key="day.value"
                                class="inline-flex h-10 items-center rounded-xl px-3 text-sm font-medium"
                                :class="scheduleForm.weekdays.includes(day.value) ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                                type="button"
                                :aria-pressed="scheduleForm.weekdays.includes(day.value)"
                                @click="toggleWeekday(day.value)"
                            >
                                {{ day.label }}
                            </button>
                        </div>
                        <p v-if="scheduleForm.errors.weekdays" class="text-sm text-danger">{{ scheduleForm.errors.weekdays }}</p>
                    </div>
                </fieldset>

                <fieldset class="flex flex-col gap-3">
                    <legend class="text-sm font-medium">Time slots</legend>
                    <div class="flex flex-wrap items-end gap-3">
                        <div v-for="(slot, index) in scheduleForm.slots" :key="index" class="flex min-w-44 flex-1 items-end gap-2">
                            <label class="flex min-w-0 flex-1 flex-col gap-1 text-sm" :for="`schedule-time-${index}`">
                                <span class="font-medium">Time</span>
                                <input :id="`schedule-time-${index}`" v-model="slot.time" :class="fieldClass" required type="time" />
                                <span v-if="scheduleForm.errors[`slots.${index}.time`]" class="text-danger">{{ scheduleForm.errors[`slots.${index}.time`] }}</span>
                            </label>
                            <button
                                v-if="scheduleForm.slots.length > 1"
                                class="inline-flex h-10 items-center rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                                type="button"
                                @click="removeSlot(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                    <p v-if="scheduleForm.errors.slots" class="text-sm text-danger">{{ scheduleForm.errors.slots }}</p>
                    <div>
                        <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="addSlot">Add time slot</button>
                    </div>
                </fieldset>

                <label class="flex flex-col gap-1 text-sm" for="schedule-branch">
                    <span class="font-medium">Branch</span>
                    <select id="schedule-branch" v-model="scheduleForm.branch_id" :class="fieldClass" required>
                        <option disabled value="">Choose a branch</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                    </select>
                    <span v-if="scheduleForm.errors.branch_id" class="text-danger">{{ scheduleForm.errors.branch_id }}</span>
                    <span v-if="branches.length === 0" class="text-ink/60 dark:text-foam/60">Add a branch before creating a delivery schedule.</span>
                </label>

                <div class="flex justify-end">
                    <button class="inline-flex h-10 items-center justify-center rounded-xl bg-pine px-3 text-sm font-medium text-foam disabled:opacity-70" type="submit" :disabled="scheduleForm.processing">
                        {{ scheduleForm.processing ? 'Saving…' : 'Create schedule' }}
                    </button>
                </div>
            </form>

            <div class="grid min-h-0 content-start gap-3 overflow-auto sm:grid-cols-2 xl:grid-cols-3">
                <p v-if="schedules.length === 0" class="text-sm text-ink/60 sm:col-span-2 xl:col-span-3 dark:text-foam/60">No delivery schedules yet.</p>
                <article v-for="schedule in schedules" :key="schedule.id" class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-start gap-3">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <h3 class="font-medium">{{ schedule.label }}</h3>
                            <p v-if="schedule.branch" class="text-xs text-ink/60 dark:text-foam/60">{{ schedule.branch }}</p>
                            <p v-if="schedule.has_ended" class="text-xs text-ink/50 dark:text-foam/50">Ended</p>
                            <p v-else-if="schedule.is_active" class="text-xs font-medium text-pine dark:text-sand">Active</p>
                            <p v-else class="text-xs text-ink/50 dark:text-foam/50">Closed</p>
                        </div>
                        <button
                            v-if="!schedule.has_ended && schedule.is_active"
                            class="inline-flex h-8 shrink-0 items-center rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                            type="button"
                            @click="setScheduleActive(schedule, false)"
                        >
                            Close
                        </button>
                        <button
                            v-else-if="!schedule.has_ended"
                            class="inline-flex h-8 shrink-0 items-center rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                            type="button"
                            @click="setScheduleActive(schedule, true)"
                        >
                            Reopen
                        </button>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-medium">Assigned orders</h4>
                            <a
                                class="shrink-0 text-sm font-medium text-pine dark:text-sand"
                                :href="`/backoffice/shop/delivery-schedules/${schedule.id}/export`"
                            >
                                Export
                            </a>
                        </div>
                        <p v-if="schedule.orders.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No orders assigned.</p>
                        <ul v-else class="flex flex-col gap-2">
                            <li v-for="assigned in schedule.orders" :key="assigned.id">
                                <button class="text-left text-sm" type="button" @click="openAssignedOrder(assigned)">
                                    <span class="font-medium text-pine dark:text-sand">{{ assigned.number }}</span>
                                    <span class="text-ink/70 dark:text-foam/70"> · {{ assigned.customer }}<span v-if="assigned.branch"> · {{ assigned.branch }}</span> · {{ assigned.status_label }}</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>
            </div>
        </div>
    </div>
</template>
