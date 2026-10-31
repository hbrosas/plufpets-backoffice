<script setup>
import { Head } from '@inertiajs/vue3';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    filters: { type: Object, required: true },
    staff: { type: Object, required: true },
});
</script>

<template>
    <Head title="Staff report" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/staff" export-url="/backoffice/reports/staff/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p class="text-sm text-ink/55 dark:text-foam/50">{{ filters.label }}.</p>
        <ReportTable title="Sales by cashier" :columns="[{ key: 'name', label: 'Cashier' }, { key: 'number', label: 'Number' }, { key: 'receipts', label: 'Receipts' }, { key: 'sales', label: 'Sales' }, { key: 'refunds', label: 'Refunds' }, { key: 'refund_rate', label: 'Refund rate' }]" :rows="staff.cashiers" />
        <ReportTable title="Cash over and short" :columns="[{ key: 'branch', label: 'Branch' }, { key: 'closed_by', label: 'Closed by' }, { key: 'direction', label: 'Result' }, { key: 'variance', label: 'Variance' }]" :rows="staff.variances" empty="No shifts were closed in this period." />
    </div>
</template>
