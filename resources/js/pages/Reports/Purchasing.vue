<script setup>
import { Head } from '@inertiajs/vue3';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    filters: { type: Object, required: true },
    purchasing: { type: Object, required: true },
});
</script>

<template>
    <Head title="Purchasing" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/purchasing" export-url="/backoffice/reports/purchasing/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p class="text-sm text-ink/55 dark:text-foam/50">
            {{ filters.label }}. Spend is goods received. Fill rate is received quantity divided by ordered quantity.
            Lead time is {{ purchasing.lead_time }}. Fill rate is {{ purchasing.fill_rate }}.
        </p>
        <ReportTable title="Spend by supplier" :columns="[{ key: 'name', label: 'Supplier' }, { key: 'total', label: 'Received' }]" :rows="purchasing.suppliers" />
        <ReportTable title="Price changes" :columns="[{ key: 'product', label: 'Product' }, { key: 'previous', label: 'Earlier cost' }, { key: 'latest', label: 'Latest cost' }]" :rows="purchasing.prices" empty="No purchase price has changed." />
    </div>
</template>
