<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
    schedules: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const scheduleError = computed(() => page.props.errors?.delivery_schedule_id ?? null);
const activeSchedules = computed(() => props.schedules.filter((schedule) => schedule.is_assignable && schedule.branch_id === props.order.branch_id));
const scheduleChoices = computed(() => {
    const choices = [...activeSchedules.value];
    const currentId = props.order.delivery_schedule_id;

    if (currentId && !choices.some((schedule) => schedule.id === currentId)) {
        const current = props.schedules.find((schedule) => schedule.id === currentId);

        choices.unshift(current ?? {
            id: currentId,
            label: props.order.schedule || 'Assigned schedule',
            is_assignable: false,
        });
    }

    return choices;
});
const mapUrl = computed(() => {
    if (props.order.coordinates) {
        return `https://www.google.com/maps?q=${encodeURIComponent(props.order.coordinates)}`;
    }

    if (props.order.address) {
        return `https://www.google.com/maps?q=${encodeURIComponent(props.order.address)}`;
    }

    return null;
});

function updateStatus(status) {
    router.put(`/backoffice/shop/orders/${props.order.id}`, { status }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function assignSchedule(scheduleId) {
    router.put(`/backoffice/shop/orders/${props.order.id}/delivery-schedule`, {
        delivery_schedule_id: scheduleId === '' ? null : Number(scheduleId),
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h3 class="text-lg font-medium">{{ order.number }}</h3>
                    <p class="text-sm text-ink/60 dark:text-foam/60">{{ order.branch }} · {{ order.placed_at }}</p>
                    <p class="text-sm">{{ order.status_label }}</p>
                </div>
                <p class="text-2xl font-medium">{{ order.total_label }}</p>
            </div>
            <div class="flex flex-wrap gap-4">
                <label class="flex w-full max-w-xs flex-col gap-1 text-sm" for="order-status">
                    <span class="text-ink/60 dark:text-foam/60">Status</span>
                    <select
                        id="order-status"
                        class="h-10 rounded-xl border border-mist bg-white pr-8 pl-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        :value="order.status"
                        @change="updateStatus($event.target.value)"
                    >
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </label>
                <label class="flex w-full max-w-xs flex-col gap-1 text-sm" for="order-delivery-schedule">
                    <span class="text-ink/60 dark:text-foam/60">Assign Delivery Schedule</span>
                    <span v-if="order.preferred_on">Preferred {{ order.preferred_on }}</span>
                    <select
                        id="order-delivery-schedule"
                        class="h-10 rounded-xl border border-mist bg-white pr-8 pl-3 text-sm outline-none focus:border-pine disabled:opacity-60 dark:border-white/10 dark:bg-white/5"
                        :value="order.delivery_schedule_id ?? ''"
                        :disabled="scheduleChoices.length === 0"
                        @change="assignSchedule($event.target.value)"
                    >
                        <option value="">No delivery schedule</option>
                        <option
                            v-for="schedule in scheduleChoices"
                            :key="schedule.id"
                            :value="schedule.id"
                            :disabled="!schedule.is_assignable"
                        >
                            {{ schedule.label }}
                        </option>
                    </select>
                    <span v-if="activeSchedules.length === 0" class="text-ink/60 dark:text-foam/60">No active delivery schedule.</span>
                    <span v-if="scheduleError" class="text-danger">{{ scheduleError }}</span>
                </label>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 text-sm dark:border-white/10 dark:bg-white/5">
                <h3 class="font-medium">Customer</h3>
                <p>{{ order.customer }}</p>
                <p v-if="order.phone" class="text-ink/70 dark:text-foam/70">{{ order.phone }}</p>
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 text-sm dark:border-white/10 dark:bg-white/5">
                <h3 class="font-medium">Delivery</h3>
                <p>{{ order.delivery || 'No delivery method' }}</p>
                <p v-if="order.delivery_fee_label" class="text-ink/70 dark:text-foam/70">Fee {{ order.delivery_fee_label }}</p>
                <p v-if="order.delivery_instructions" class="text-ink/70 dark:text-foam/70">{{ order.delivery_instructions }}</p>
                <img
                    v-for="(image, index) in order.delivery_instruction_images"
                    :key="image"
                    :src="image"
                    :alt="`Delivery instruction photo ${index + 1}`"
                    class="w-full rounded-xl object-contain"
                />
                <p v-if="order.address">{{ order.address }}</p>
                <p v-if="order.coordinates" class="text-ink/50 dark:text-foam/50">{{ order.coordinates }}</p>
                <a v-if="mapUrl" class="font-medium text-pine dark:text-sand" :href="mapUrl" target="_blank" rel="noopener">Open in Google Maps</a>
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 text-sm dark:border-white/10 dark:bg-white/5">
                <h3 class="font-medium">Payment</h3>
                <p>{{ order.payment || 'No payment method' }}</p>
                <p v-if="order.payment_fee_label" class="text-ink/70 dark:text-foam/70">Fee {{ order.payment_fee_label }}</p>
                <p v-if="order.payment_instructions" class="text-ink/70 dark:text-foam/70">{{ order.payment_instructions }}</p>
                <img
                    v-for="(image, index) in order.payment_instruction_images"
                    :key="image"
                    :src="image"
                    :alt="`Payment instruction photo ${index + 1}`"
                    class="w-full rounded-xl object-contain"
                />
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 text-sm dark:border-white/10 dark:bg-white/5">
                <h3 class="font-medium">Voucher</h3>
                <p v-if="order.voucher">{{ order.voucher }} · {{ order.voucher_discount_label }}</p>
                <p v-else class="text-ink/60 dark:text-foam/60">No voucher</p>
            </article>
        </section>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Item</th>
                        <th class="px-4 py-3 font-medium">Qty</th>
                        <th class="px-4 py-3 font-medium">Price</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, index) in order.lines" :key="`${line.name}-${index}`" class="border-b border-mist align-top last:border-b-0 dark:border-white/10">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ line.name }}</p>
                            <p v-if="line.variant" class="text-xs text-ink/50">{{ line.variant }}</p>
                            <p v-if="line.notes" class="text-xs text-ink/50">{{ line.notes }}</p>
                            <p v-for="modifier in line.modifiers" :key="modifier" class="text-xs text-ink/50">{{ modifier }}</p>
                            <p v-if="line.assigned.length" class="text-xs text-ink/50">Assigned {{ line.assigned.join(', ') }}</p>
                            <p v-if="line.assisted.length" class="text-xs text-ink/50">Assisted {{ line.assisted.join(', ') }}</p>
                        </td>
                        <td class="px-4 py-3">{{ line.quantity }}</td>
                        <td class="px-4 py-3">{{ line.price_label }}</td>
                        <td class="px-4 py-3">{{ line.line_total_label }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 text-sm dark:border-white/10 dark:bg-white/5">
            <h3 class="font-medium">Order total</h3>
            <div class="flex justify-between gap-3"><span>Items</span><span>{{ order.subtotal_label }}</span></div>
            <div v-if="order.delivery_fee_label" class="flex justify-between gap-3"><span>Delivery</span><span>{{ order.delivery_fee_label }}</span></div>
            <div v-if="order.payment_fee_label" class="flex justify-between gap-3"><span>Payment fee</span><span>{{ order.payment_fee_label }}</span></div>
            <div class="flex justify-between gap-3"><span>Discount</span><span>{{ order.discount_label }}</span></div>
            <div class="flex justify-between gap-3 font-medium"><span>Total</span><span>{{ order.total_label }}</span></div>
        </section>

        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-medium">Sale</h3>
                    <p class="text-sm text-ink/60 dark:text-foam/60">The register transaction created from this order.</p>
                </div>
                <Link
                    v-if="order.sale && order.can_view_receipt"
                    class="text-sm font-medium text-pine dark:text-sand"
                    :href="`/backoffice/sales/receipts/${order.sale.id}`"
                >
                    View receipt
                </Link>
            </div>

            <p v-if="!order.sale" class="text-sm text-ink/60 dark:text-foam/60">No sale recorded yet.</p>

            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex flex-col gap-1 text-sm">
                        <p class="font-medium">{{ order.sale.number }}</p>
                        <p class="text-ink/60 dark:text-foam/60">{{ order.sale.sold_at }}</p>
                        <p>{{ order.sale.cashier }} · #{{ order.sale.employee_number }}</p>
                        <p v-if="order.sale.delivery">{{ order.sale.delivery }}</p>
                        <p v-if="order.sale.is_refunded" class="text-danger">
                            Refunded {{ order.sale.refunded_at }} by {{ order.sale.refunded_by }}
                            <span v-if="order.sale.refund_reason"> · {{ order.sale.refund_reason }}</span>
                        </p>
                    </div>
                    <p class="text-2xl font-medium">{{ order.sale.total_label }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="py-3 pr-4 font-medium">Item</th>
                                <th class="px-4 py-3 font-medium">Qty</th>
                                <th class="px-4 py-3 font-medium">Price</th>
                                <th class="py-3 pl-4 font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in order.sale.items" :key="item.id" class="border-b border-mist align-top last:border-b-0 dark:border-white/10">
                                <td class="py-3 pr-4">
                                    <p class="font-medium">{{ item.product }}</p>
                                    <p v-if="item.variant && item.variant !== 'Default'" class="text-xs text-ink/50">{{ item.variant }}</p>
                                    <p v-if="item.notes" class="text-xs text-ink/50">{{ item.notes }}</p>
                                    <p v-for="modifier in item.modifiers" :key="modifier" class="text-xs text-ink/50">{{ modifier }}</p>
                                    <p v-for="commission in item.commissions" :key="`${commission.employee}-${commission.role}`" class="text-xs text-ink/50">
                                        {{ commission.role }} {{ commission.employee }} · {{ commission.amount_label }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">{{ item.quantity }}</td>
                                <td class="px-4 py-3">{{ item.unit_price_label }}</td>
                                <td class="py-3 pl-4">{{ item.line_total_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2 text-sm">
                        <h4 class="font-medium">Payments</h4>
                        <p v-if="order.sale.payments.length === 0" class="text-ink/60 dark:text-foam/60">No payment lines.</p>
                        <div v-for="(payment, index) in order.sale.payments" :key="`${payment.name}-${index}`" class="flex justify-between gap-3">
                            <span>
                                {{ payment.name }}
                                <span v-if="payment.reference" class="text-ink/50"> · {{ payment.reference }}</span>
                            </span>
                            <span>{{ payment.amount_label }}</span>
                        </div>
                    </div>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-3"><dt>Subtotal</dt><dd>{{ order.sale.subtotal_label }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Delivery</dt><dd>{{ order.sale.delivery_fee_label }}</dd></div>
                        <div v-for="tax in order.sale.taxes" :key="tax.name" class="flex justify-between gap-3">
                            <dt>{{ tax.name }}</dt>
                            <dd>{{ tax.amount_label }}</dd>
                        </div>
                        <div v-if="order.sale.taxes.length === 0" class="flex justify-between gap-3"><dt>Tax</dt><dd>{{ order.sale.tax_label }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Tendered</dt><dd>{{ order.sale.tendered_label }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Change</dt><dd>{{ order.sale.change_label }}</dd></div>
                        <div class="flex justify-between gap-3 font-medium"><dt>Total</dt><dd>{{ order.sale.total_label }}</dd></div>
                    </dl>
                </div>
            </template>
        </section>
    </div>
</template>
