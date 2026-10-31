<script setup>
import { Head } from '@inertiajs/vue3';
import CommissionList from '../../components/CommissionList.vue';
import ReportPeriodFilter from '../../components/ReportPeriodFilter.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    filters: {
        type: Object,
        required: true,
    },
    scope: {
        type: String,
        required: true,
    },
    report: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Commissions" />

    <Teleport defer to="#admin-header-actions">
        <ReportPeriodFilter action="/backoffice/reports/commissions" export-url="/backoffice/reports/commissions/export" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p class="text-sm text-ink/55 dark:text-foam/50">
            {{ scope === 'all' ? 'Commissions for every employee.' : 'Your commissions only.' }}
        </p>
        <CommissionList :report="report" :scope="scope" />
    </div>
</template>
