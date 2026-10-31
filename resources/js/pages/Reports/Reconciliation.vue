<script setup>
import { Head } from '@inertiajs/vue3';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    filters: { type: Object, required: true },
    reconciliation: { type: Object, required: true },
});
</script>

<template>
    <Head title="Reconciliation" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/reconciliation" export-url="/backoffice/reports/reconciliation/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p class="text-sm text-ink/55 dark:text-foam/50">
            {{ filters.label }}. Counted cash is compared with the expected drawer. A separate physical stock count is not stored, so the stock list is the system quantity for items at 5 or fewer.
        </p>
        <ReportTable title="Closed shifts" :columns="[{ key: 'branch', label: 'Branch' }, { key: 'expected', label: 'Expected' }, { key: 'counted', label: 'Counted' }, { key: 'direction', label: 'Result' }, { key: 'variance', label: 'Variance' }, { key: 'sales', label: 'Sales' }]" :rows="reconciliation.shifts" empty="No shifts were closed in this period." />
        <ReportTable title="Payments" :columns="[{ key: 'name', label: 'Method' }, { key: 'receipts', label: 'Receipts' }, { key: 'total', label: 'Collected' }]" :rows="reconciliation.payments" />
        <ReportTable title="System stock at 5 or fewer" :columns="[{ key: 'product', label: 'Product' }, { key: 'branch', label: 'Branch' }, { key: 'quantity', label: 'System qty' }, { key: 'status', label: 'Status' }]" :rows="reconciliation.stock" empty="Every product is above 5 on hand." />
    </div>
</template>
