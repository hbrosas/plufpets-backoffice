<script setup>
import { Head } from '@inertiajs/vue3';
import SalesFilters from '../../../components/SalesFilters.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    filters: { type: Object, required: true },
    period_label: { type: String, required: true },
    branches: { type: Array, required: true },
    items: { type: Array, required: true },
});
</script>

<template>
    <Head title="Sold items" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters action="/backoffice/sales/items" :filters="filters" :branches="branches" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">

        <p class="text-sm text-ink/55 dark:text-foam/50">{{ period_label }}</p>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Item</th>
                        <th class="px-4 py-3 font-medium">Variant</th>
                        <th class="px-4 py-3 font-medium">Qty sold</th>
                        <th class="px-4 py-3 font-medium">Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="items.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="4">No sold items in this period.</td>
                    </tr>
                    <tr
                        v-for="item in items"
                        :key="`${item.product}-${item.variant}`"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ item.product }}</td>
                        <td class="px-4 py-3">{{ item.variant }}</td>
                        <td class="px-4 py-3">{{ item.quantity }}</td>
                        <td class="px-4 py-3">{{ item.total }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
