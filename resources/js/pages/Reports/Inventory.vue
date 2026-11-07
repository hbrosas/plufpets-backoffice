<script setup>
import { Head } from '@inertiajs/vue3';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    filters: { type: Object, required: true },
    inventory: { type: Object, required: true },
});
</script>

<template>
    <Head title="Inventory" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/inventory" export-url="/backoffice/reports/inventory/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">On hand</p>
                <p class="text-3xl font-medium">{{ inventory.quantity }}</p>
            </article>
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Stock value</p>
                <p class="text-3xl font-medium">{{ inventory.value }}</p>
            </article>
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Turnover</p>
                <p class="text-3xl font-medium">{{ inventory.turnover }}</p>
            </article>
        </div>
        <ReportTable title="Movement" :columns="[{ key: 'label', label: 'Movement' }, { key: 'quantity', label: 'Quantity' }]" :rows="inventory.movement" />
        <ReportTable title="Stock on hand" :columns="[{ key: 'product', label: 'Product' }, { key: 'variant', label: 'Variant' }, { key: 'branch', label: 'Branch' }, { key: 'quantity', label: 'On hand' }, { key: 'value', label: 'Value' }]" :rows="inventory.items" empty="No products on hand." />
    </div>
</template>
