<script setup>
import { Head } from '@inertiajs/vue3';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    filters: { type: Object, required: true },
    products: { type: Object, required: true },
});
</script>

<template>
    <Head title="Products" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/products" export-url="/backoffice/reports/products/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <ReportTable title="Best sellers" :columns="[{ key: 'name', label: 'Product' }, { key: 'quantity', label: 'Qty' }, { key: 'total', label: 'Sales' }]" :rows="products.best" />
        <ReportTable title="Slow movers" :columns="[{ key: 'name', label: 'Product' }, { key: 'quantity', label: 'Qty sold' }, { key: 'total', label: 'Sales' }]" :rows="products.slow" empty="Every stocked product sold in this period." />
        <ReportTable title="ABC analysis" :columns="[{ key: 'name', label: 'Product' }, { key: 'total', label: 'Sales' }, { key: 'share', label: 'Share' }, { key: 'class', label: 'Class' }]" :rows="products.abc" />
        <ReportTable title="Bought together" :columns="[{ key: 'pair', label: 'Items' }, { key: 'sales', label: 'Receipts' }]" :rows="products.together" empty="No items were bought together." />
    </div>
</template>
