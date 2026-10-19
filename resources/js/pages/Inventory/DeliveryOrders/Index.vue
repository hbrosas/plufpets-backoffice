<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    orders: {
        type: Object,
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

function selectTab(tab) {
    router.get('/backoffice/inventory/delivery-orders', { tab }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Delivery Orders" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                class="h-10 rounded-xl px-4 text-sm font-medium"
                :class="
                    filters.tab === tab.value
                        ? 'bg-pine text-foam'
                        : 'border border-mist dark:border-white/15'
                "
                type="button"
                @click="selectTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">PO</th>
                        <th class="px-4 py-3 font-medium">Supplier</th>
                        <th class="px-4 py-3 font-medium">Ordered</th>
                        <th class="px-4 py-3 font-medium">Received</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="orders.data.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="6">
                            No purchase orders in this tab.
                        </td>
                    </tr>
                    <tr
                        v-for="order in orders.data"
                        :key="order.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ order.number }}</td>
                        <td class="px-4 py-3">{{ order.supplier }}</td>
                        <td class="px-4 py-3">{{ order.ordered_quantity }}</td>
                        <td class="px-4 py-3">{{ order.received_quantity }}</td>
                        <td class="px-4 py-3">{{ order.delivery_status_label }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                class="inline-flex h-10 items-center rounded-xl bg-pine px-4 text-sm font-medium text-foam"
                                :href="`/backoffice/inventory/delivery-orders/${order.id}`"
                            >
                                {{ order.delivery_status === 'completed' ? 'View' : 'Receive' }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
