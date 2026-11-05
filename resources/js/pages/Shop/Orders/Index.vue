<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const preordersOpen = ref(false);
const statusTab = ref(props.statuses[0]?.value ?? '');
const visibleOrders = computed(() => props.orders.filter((order) => order.status === statusTab.value));

function ordersInStatus(status) {
    return props.orders.filter((order) => order.status === status).length;
}

function updateStatus(order, status) {
    router.put(`/backoffice/shop/orders/${order.id}`, { status }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Orders" />

    <Teleport defer to="#admin-header-actions">
        <button
            class="inline-flex h-10 shrink-0 items-center rounded-xl bg-pine px-3 text-sm font-medium text-foam"
            type="button"
            @click="preordersOpen = true"
        >
            For Pre-Order
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4">
        <div>
            <h2 class="text-lg font-medium">Shop orders</h2>
            <p class="text-sm text-ink/60 dark:text-foam/60">Changing the status sends the matching text to the customer.</p>
        </div>
        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Order status">
            <button
                v-for="status in statuses"
                :id="`orders-tab-${status.value}`"
                :key="status.value"
                class="h-10 rounded-xl px-4 text-sm font-medium"
                :class="statusTab === status.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="statusTab === status.value"
                @click="statusTab = status.value"
            >
                {{ status.label }}
                <span class="ml-1 tabular-nums">{{ ordersInStatus(status.value) }}</span>
            </button>
        </div>
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5" role="tabpanel" :aria-labelledby="`orders-tab-${statusTab}`">
            <table class="w-full text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th class="px-4 py-3 font-medium">Customer</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="visibleOrders.length === 0">
                        <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="4">No orders with this status.</td>
                    </tr>
                    <tr v-for="order in visibleOrders" :key="order.id" class="border-b border-mist align-top last:border-b-0 dark:border-white/10">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ order.number }}</p>
                            <p class="text-xs text-ink/50 dark:text-foam/50">{{ order.branch }} · {{ order.placed_at }}</p>
                            <p v-if="order.sale_number" class="text-xs text-ink/70 dark:text-foam/70">Sale {{ order.sale_number }}</p>
                            <p class="mt-1 text-xs text-ink/70 dark:text-foam/70">{{ order.items.join(', ') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ order.customer }}</p>
                            <p class="text-xs text-ink/50 dark:text-foam/50">{{ order.phone }}</p>
                            <p v-if="order.delivery" class="mt-1 text-xs">{{ order.delivery }}<span v-if="order.address"> · {{ order.address }}</span></p>
                            <p v-if="order.coordinates" class="text-xs text-ink/50 dark:text-foam/50">{{ order.coordinates }}</p>
                            <p v-if="order.payment" class="text-xs">{{ order.payment }}</p>
                            <p v-if="order.voucher" class="text-xs">Voucher {{ order.voucher }}</p>
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            <p class="font-medium">{{ order.total_label }}</p>
                            <p class="text-xs text-ink/50 dark:text-foam/50">Items {{ order.subtotal_label }}</p>
                            <p v-if="order.delivery_fee_label" class="text-xs text-ink/50 dark:text-foam/50">Delivery {{ order.delivery_fee_label }}</p>
                            <p v-if="order.payment_fee_label" class="text-xs text-ink/50 dark:text-foam/50">Payment {{ order.payment_fee_label }}</p>
                            <p class="text-xs text-ink/50 dark:text-foam/50">Discount {{ order.discount_label }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <label class="sr-only" :for="`order-status-${order.id}`">Status</label>
                            <select
                                :id="`order-status-${order.id}`"
                                class="h-10 rounded-xl border border-mist bg-white pr-8 pl-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                                :value="order.status"
                                @change="updateStatus(order, $event.target.value)"
                            >
                                <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                            <Link
                                class="mt-2 inline-flex h-10 items-center rounded-xl bg-pine px-3 text-sm font-medium text-foam"
                                :href="`/backoffice/shop/orders/${order.id}`"
                            >
                                View
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="preordersOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[90dvh] w-full max-w-5xl flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-6 dark:bg-ink">
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
            <div class="min-h-0 overflow-auto rounded-2xl border border-mist dark:border-white/10">
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
</template>
