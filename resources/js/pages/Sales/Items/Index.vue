<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SalesFilters from '../../../components/SalesFilters.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    filters: { type: Object, required: true },
    period_label: { type: String, required: true },
    branches: { type: Array, required: true },
    products: { type: Array, required: true },
    services: { type: Array, required: true },
});

const columns = computed(() => [
    { key: 'products', title: 'Products', rows: props.products, empty: 'No products sold in this period.' },
    { key: 'services', title: 'Services', rows: props.services, empty: 'No services sold in this period.' },
]);
</script>

<template>
    <Head title="Sold items" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters action="/backoffice/sales/items" :filters="filters" :branches="branches" />
    </Teleport>

    <div class="mx-auto grid w-full max-w-6xl items-start gap-6 lg:grid-cols-2">
        <section
            v-for="column in columns"
            :key="column.key"
            class="min-w-0 overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5"
        >
            <h2 class="px-4 pt-4 text-lg font-medium">{{ column.title }}</h2>
            <table class="w-full text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Item</th>
                        <th class="px-4 py-3 font-medium">Variant</th>
                        <th class="px-4 py-3 font-medium">Qty</th>
                        <th class="px-4 py-3 font-medium">Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="column.rows.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="4">{{ column.empty }}</td>
                    </tr>
                    <tr
                        v-for="item in column.rows"
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
        </section>
    </div>
</template>
