<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SalesFilters from '../../../components/SalesFilters.vue';
import SalesRefundModal from '../../../components/SalesRefundModal.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    filters: { type: Object, required: true },
    period_label: { type: String, required: true },
    branches: { type: Array, required: true },
    receipts: { type: Object, required: true },
});

const refunding = ref(null);
</script>

<template>
    <Head title="Receipts" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters
            action="/backoffice/sales/receipts"
            :filters="filters"
            :branches="branches"
            show-search
            search-placeholder="Receipt or cashier"
        />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p class="text-sm text-ink/55 dark:text-foam/50">{{ period_label }}</p>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[52rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Receipt</th>
                        <th class="px-4 py-3 font-medium">When</th>
                        <th class="px-4 py-3 font-medium">Branch</th>
                        <th class="px-4 py-3 font-medium">Cashier</th>
                        <th class="px-4 py-3 font-medium">Payment</th>
                        <th class="px-4 py-3 font-medium">Items</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="receipts.data.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="8">No receipts in this period.</td>
                    </tr>
                    <tr
                        v-for="receipt in receipts.data"
                        :key="receipt.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">
                            <span>{{ receipt.number }}</span>
                            <span
                                v-if="receipt.is_refunded"
                                class="ml-2 rounded-full bg-danger/10 px-2 py-0.5 text-[11px] font-medium text-danger"
                            >
                                Refunded
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ receipt.sold_at }}</td>
                        <td class="px-4 py-3">{{ receipt.branch }}</td>
                        <td class="px-4 py-3">{{ receipt.cashier }}</td>
                        <td class="px-4 py-3">{{ receipt.payments }}</td>
                        <td class="px-4 py-3">{{ receipt.item_count }}</td>
                        <td class="px-4 py-3">{{ receipt.total }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <Link class="font-medium text-pine dark:text-sand" :href="`/backoffice/sales/receipts/${receipt.id}`">
                                    View
                                </Link>
                                <button
                                    v-if="!receipt.is_refunded"
                                    class="font-medium text-danger"
                                    type="button"
                                    @click="refunding = receipt"
                                >
                                    Refund
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="receipts.last_page > 1" class="flex gap-3" aria-label="Pagination">
            <Link
                v-if="receipts.prev_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="receipts.prev_page_url"
                :preserve-state="true"
            >
                Previous
            </Link>
            <Link
                v-if="receipts.next_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="receipts.next_page_url"
                :preserve-state="true"
            >
                Next
            </Link>
        </nav>
    </div>

    <SalesRefundModal v-if="refunding" :receipt="refunding" @close="refunding = null" />
</template>
