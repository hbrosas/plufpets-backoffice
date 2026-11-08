<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PurchaseOrderView from '../../../components/PurchaseOrderView.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    purchaseOrders: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    tabs: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const statusTab = ref(props.filters.tab);
const selectedOrderId = ref(null);
const visibleOrders = computed(() => props.purchaseOrders.filter((order) => order.status === statusTab.value));
const selectedOrder = computed(() => props.purchaseOrders.find((order) => order.id === selectedOrderId.value) ?? null);

function selectTab(tab) {
    statusTab.value = tab;

    if (selectedOrder.value?.status !== tab) {
        selectedOrderId.value = null;
    }
}

function selectOrder(order) {
    selectedOrderId.value = order.id;
}
</script>

<template>
    <Head title="Purchase Orders" />

    <Teleport defer to="#admin-header-actions">
        <Link
            class="inline-flex h-10 items-center rounded-xl bg-pine px-4 text-sm font-medium text-foam"
            href="/backoffice/inventory/purchase-orders/create"
        >
            Create PO
        </Link>
    </Teleport>

    <Teleport defer to="#admin-header-subnav">
        <div class="flex w-max gap-2" role="tablist" aria-label="Purchase order status">
            <button
                v-for="tab in tabs"
                :id="`purchase-orders-tab-${tab.value}`"
                :key="tab.value"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="statusTab === tab.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="statusTab === tab.value"
                @click="selectTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>
    </Teleport>

    <div class="flex min-h-0 w-full flex-1 flex-col gap-4">
        <p v-if="flash.status" class="shrink-0 rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>

        <div class="grid min-h-0 flex-1 grid-rows-[minmax(16rem,0.8fr)_minmax(0,1.2fr)] gap-4 lg:grid-cols-[minmax(18rem,24rem)_minmax(0,1fr)] lg:grid-rows-1">
            <section class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5" aria-labelledby="purchase-order-list-heading">
                <h2 id="purchase-order-list-heading" class="border-b border-mist px-4 py-3 text-sm font-medium dark:border-white/10">Purchase Orders</h2>
                <div class="min-h-0 flex-1 overflow-y-auto" role="tabpanel" :aria-labelledby="`purchase-orders-tab-${statusTab}`">
                    <p v-if="visibleOrders.length === 0" class="px-4 py-8 text-sm text-ink/60 dark:text-foam/60">No purchase orders with this status.</p>
                    <button
                        v-for="order in visibleOrders"
                        :key="order.id"
                        class="flex w-full flex-col gap-1 border-b border-mist px-4 py-3 text-left last:border-b-0 dark:border-white/10"
                        :class="selectedOrderId === order.id ? 'bg-pine/10 dark:bg-white/10' : 'hover:bg-black/[0.03] dark:hover:bg-white/[0.04]'"
                        type="button"
                        :aria-current="selectedOrderId === order.id ? 'true' : undefined"
                        @click="selectOrder(order)"
                    >
                        <span class="font-medium">{{ order.number }}</span>
                        <span class="text-sm">{{ order.supplier }}</span>
                        <span class="text-xs text-ink/50 dark:text-foam/50">{{ order.item_count }} items · {{ order.total }}</span>
                        <span class="text-xs">{{ order.status_label }}</span>
                    </button>
                </div>
            </section>

            <section class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-mist bg-foam dark:border-white/10 dark:bg-ink" aria-labelledby="purchase-order-view-heading">
                <h2 id="purchase-order-view-heading" class="border-b border-mist bg-white px-4 py-3 text-sm font-medium dark:border-white/10 dark:bg-white/5">View</h2>
                <div class="min-h-0 flex-1 overflow-auto p-4">
                    <p v-if="!selectedOrder" class="py-12 text-center text-sm text-ink/60 dark:text-foam/60">Select a purchase order.</p>
                    <PurchaseOrderView v-else :key="selectedOrder.id" :purchase-order="selectedOrder" />
                </div>
            </section>
        </div>
    </div>
</template>
