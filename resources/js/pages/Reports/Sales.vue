<script setup>
import { Head } from '@inertiajs/vue3';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    filters: { type: Object, required: true },
    sales: { type: Object, required: true },
});
</script>

<template>
    <Head title="Sales report" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/sales" export-url="/backoffice/reports/sales/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in [
                ['Gross', sales.summary.gross],
                ['Discounts', sales.summary.discounts],
                ['Net', sales.summary.net],
                ['Tax', sales.summary.tax],
                ['Collected', sales.summary.collected],
                ['Refunds', sales.summary.refunds],
                ['Receipts', sales.summary.receipts],
                ['Services', sales.summary.services],
            ]" :key="card[0]" class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">{{ card[0] }}</p>
                <p class="text-2xl font-medium">{{ card[1] }}</p>
            </article>
        </div>
        <ReportTable title="By day" :columns="[{ key: 'name', label: 'Day' }, { key: 'receipts', label: 'Receipts' }, { key: 'total', label: 'Sales' }]" :rows="sales.days" />
        <ReportTable title="By hour" :columns="[{ key: 'name', label: 'Hour' }, { key: 'receipts', label: 'Receipts' }, { key: 'total', label: 'Sales' }]" :rows="sales.hours" />
        <ReportTable title="By category" :columns="[{ key: 'name', label: 'Category' }, { key: 'quantity', label: 'Qty' }, { key: 'total', label: 'Sales' }]" :rows="sales.categories" />
        <ReportTable title="By item" :columns="[{ key: 'name', label: 'Item' }, { key: 'quantity', label: 'Qty' }, { key: 'total', label: 'Sales' }]" :rows="sales.items" />
        <ReportTable title="By cashier" :columns="[{ key: 'name', label: 'Cashier' }, { key: 'receipts', label: 'Receipts' }, { key: 'total', label: 'Sales' }]" :rows="sales.cashiers" />
        <ReportTable title="By branch" :columns="[{ key: 'name', label: 'Branch' }, { key: 'receipts', label: 'Receipts' }, { key: 'total', label: 'Sales' }]" :rows="sales.branches" />
        <ReportTable title="By payment method" :columns="[{ key: 'name', label: 'Method' }, { key: 'receipts', label: 'Receipts' }, { key: 'total', label: 'Sales' }]" :rows="sales.payments" />
    </div>
</template>
