<script setup>
import { Head } from '@inertiajs/vue3';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    filters: { type: Object, required: true },
    profit: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Item' },
    { key: 'quantity', label: 'Qty' },
    { key: 'revenue', label: 'Revenue' },
    { key: 'margin', label: 'Margin' },
    { key: 'margin_rate', label: 'Margin %' },
];
</script>

<template>
    <Head title="Profit" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/profit" export-url="/backoffice/reports/profit/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <ReportTable title="By category" :columns="[{ key: 'name', label: 'Category' }, { key: 'revenue', label: 'Revenue' }, { key: 'margin', label: 'Margin' }, { key: 'margin_rate', label: 'Margin %' }]" :rows="profit.categories" />
        <ReportTable title="Best margin" :columns="columns" :rows="profit.best" empty="No priced products in this period." />
        <ReportTable title="Worst margin" :columns="columns" :rows="profit.worst" empty="No priced products in this period." />
    </div>
</template>
