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
        <div class="grid gap-4 sm:grid-cols-2">
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Lead time</p>
                <p class="text-3xl font-medium">{{ purchasing.lead_time }}</p>
            </article>
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Fill rate</p>
                <p class="text-3xl font-medium">{{ purchasing.fill_rate }}</p>
            </article>
        </div>
        <ReportTable title="Spend by supplier" :columns="[{ key: 'name', label: 'Supplier' }, { key: 'total', label: 'Received' }]" :rows="purchasing.suppliers" />
        <ReportTable title="Price changes" :columns="[{ key: 'product', label: 'Product' }, { key: 'previous', label: 'Earlier cost' }, { key: 'latest', label: 'Latest cost' }]" :rows="purchasing.prices" empty="No purchase price has changed." />
    </div>
</template>
