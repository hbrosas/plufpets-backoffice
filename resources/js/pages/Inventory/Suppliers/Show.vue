<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    supplier: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="supplier.name" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-1">
                <p class="text-sm text-ink/60 dark:text-foam/60">{{ supplier.is_active ? 'Active' : 'Inactive' }}</p>
                <h2 class="text-2xl font-medium">{{ supplier.name }}</h2>
                <p v-if="supplier.code" class="text-sm text-ink/70 dark:text-foam/70">{{ supplier.code }}</p>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <dt class="text-sm text-ink/60 dark:text-foam/60">Business TIN</dt>
                    <dd>{{ supplier.tin || '—' }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm text-ink/60 dark:text-foam/60">Address</dt>
                    <dd>{{ supplier.address || '—' }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm text-ink/60 dark:text-foam/60">Contact person</dt>
                    <dd>{{ supplier.contact_name || '—' }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm text-ink/60 dark:text-foam/60">Phone</dt>
                    <dd>{{ supplier.phone || '—' }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm text-ink/60 dark:text-foam/60">Email</dt>
                    <dd>{{ supplier.email || '—' }}</dd>
                </div>
                <div v-if="supplier.notes" class="flex flex-col gap-1 sm:col-span-2">
                    <dt class="text-sm text-ink/60 dark:text-foam/60">Notes</dt>
                    <dd>{{ supplier.notes }}</dd>
                </div>
            </dl>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">Purchase order history</h2>
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Order</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="supplier.purchase_orders.length === 0">
                            <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="4">No purchase orders yet.</td>
                        </tr>
                        <tr
                            v-for="order in supplier.purchase_orders"
                            :key="order.id"
                            class="border-b border-mist last:border-b-0 dark:border-white/10"
                        >
                            <td class="px-4 py-3 font-medium">
                                <Link class="text-pine dark:text-sand" :href="`/backoffice/inventory/purchase-orders/${order.id}`">
                                    {{ order.number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">{{ order.status_label }}</td>
                            <td class="px-4 py-3">{{ order.ordered_at || '—' }}</td>
                            <td class="px-4 py-3">{{ order.total }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
