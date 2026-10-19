<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DeliveryReceiptHistory from '../../../components/DeliveryReceiptHistory.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    purchaseOrder: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const historyOpen = ref(false);

function confirmOrder() {
    if (!window.confirm(`Confirm ${props.purchaseOrder.number}? It will move to Delivery Orders.`)) {
        return;
    }

    router.post(`/backoffice/inventory/purchase-orders/${props.purchaseOrder.id}/confirm`);
}

function deleteOrder() {
    if (!window.confirm(`Delete ${props.purchaseOrder.number}?`)) {
        return;
    }

    router.delete(`/backoffice/inventory/purchase-orders/${props.purchaseOrder.id}`);
}
</script>

<template>
    <Head :title="purchaseOrder.number" />

    <Teleport defer to="#admin-header-actions">
        <button
            class="h-10 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
            type="button"
            @click="historyOpen = true"
        >
            Show History
        </button>
        <a
            class="inline-flex h-10 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
            :href="`/backoffice/inventory/purchase-orders/${purchaseOrder.id}/print?download=1`"
        >
            Download
        </a>
        <a
            class="inline-flex h-10 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
            :href="`/backoffice/inventory/purchase-orders/${purchaseOrder.id}/print`"
            target="_blank"
        >
            Print
        </a>
        <Link
            v-if="purchaseOrder.is_draft"
            class="inline-flex h-10 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
            :href="`/backoffice/inventory/purchase-orders/${purchaseOrder.id}/edit`"
        >
            Edit
        </Link>
        <button
            v-if="purchaseOrder.is_draft"
            class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam"
            type="button"
            @click="confirmOrder"
        >
            Confirm
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">{{ purchaseOrder.status_label }}</p>
                <h2 class="mt-1 text-2xl font-medium">{{ purchaseOrder.number }}</h2>
                <p class="mt-2">{{ purchaseOrder.supplier }}</p>
                <p v-if="purchaseOrder.supplier_detail.address" class="text-sm text-ink/70 dark:text-foam/70">
                    {{ purchaseOrder.supplier_detail.address }}
                </p>
                <p v-if="purchaseOrder.notes" class="mt-3 text-sm">{{ purchaseOrder.notes }}</p>
            </div>
            <div class="rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Business information</p>
                <h2 class="mt-1 text-lg font-medium">
                    {{ purchaseOrder.business.registered_business_name || purchaseOrder.business.branch || '—' }}
                </h2>
                <p v-if="purchaseOrder.business.branch && purchaseOrder.business.registered_business_name" class="text-sm">
                    {{ purchaseOrder.business.branch }}
                </p>
                <p v-if="purchaseOrder.business.address" class="mt-2 text-sm text-ink/70 dark:text-foam/70">
                    {{ purchaseOrder.business.address }}
                </p>
                <p v-if="purchaseOrder.business.tin" class="text-sm text-ink/70 dark:text-foam/70">
                    TIN {{ purchaseOrder.business.tin }}
                </p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Product</th>
                        <th class="px-4 py-3 font-medium">SKU</th>
                        <th class="px-4 py-3 font-medium">Qty</th>
                        <th class="px-4 py-3 font-medium">Received</th>
                        <th class="px-4 py-3 font-medium">Unit cost</th>
                        <th class="px-4 py-3 font-medium">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in purchaseOrder.items"
                        :key="item.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ item.product }} · {{ item.variant }}</td>
                        <td class="px-4 py-3">{{ item.sku || '—' }}</td>
                        <td class="px-4 py-3">{{ item.quantity }}</td>
                        <td class="px-4 py-3">{{ item.received_quantity }}</td>
                        <td class="px-4 py-3">{{ item.unit_cost }}</td>
                        <td class="px-4 py-3">{{ item.line_total }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-mist dark:border-white/10">
                        <td class="px-4 py-3 font-medium" colspan="2">Summary</td>
                        <td class="px-4 py-3 font-medium">{{ purchaseOrder.ordered_quantity }}</td>
                        <td class="px-4 py-3">{{ purchaseOrder.received_quantity }}</td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 font-medium">{{ purchaseOrder.total }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <button
            v-if="purchaseOrder.is_draft"
            class="self-start text-sm font-medium text-danger"
            type="button"
            @click="deleteOrder"
        >
            Delete draft
        </button>
    </div>

    <div v-if="historyOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[90vh] w-full max-w-3xl flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-medium">Transaction history</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">{{ purchaseOrder.number }}</p>
                </div>
                <button class="text-sm font-medium" type="button" @click="historyOpen = false">Close</button>
            </div>
            <DeliveryReceiptHistory :receipts="purchaseOrder.receipts" :show-heading="false" />
        </div>
    </div>
</template>
