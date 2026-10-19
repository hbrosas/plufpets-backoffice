<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    purchaseOrders: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
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

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">PO</th>
                        <th class="px-4 py-3 font-medium">Supplier</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Items</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="purchaseOrders.data.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="6">
                            No purchase orders yet.
                        </td>
                    </tr>
                    <tr
                        v-for="order in purchaseOrders.data"
                        :key="order.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ order.number }}</td>
                        <td class="px-4 py-3">{{ order.supplier }}</td>
                        <td class="px-4 py-3">{{ order.status_label }}</td>
                        <td class="px-4 py-3">{{ order.item_count }}</td>
                        <td class="px-4 py-3">{{ order.total }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <Link class="font-medium text-pine dark:text-sand" :href="`/backoffice/inventory/purchase-orders/${order.id}`">
                                    View
                                </Link>
                                <Link
                                    v-if="order.status === 'draft'"
                                    class="font-medium text-pine dark:text-sand"
                                    :href="`/backoffice/inventory/purchase-orders/${order.id}/edit`"
                                >
                                    Edit
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
