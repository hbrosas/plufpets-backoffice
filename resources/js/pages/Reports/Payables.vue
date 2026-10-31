<script setup>
import { Head } from '@inertiajs/vue3';
import ReportTable from '../../components/ReportTable.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    payables: { type: Object, required: true },
});
</script>

<template>
    <Head title="Payables" />

    <Teleport defer to="#admin-header-actions">
        <a
            class="inline-flex h-10 items-center rounded-xl border border-mist bg-white px-4 text-sm font-medium dark:border-white/15 dark:bg-ink"
            href="/backoffice/reports/payables/export"
        >
            Export
        </a>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p class="text-sm text-ink/55 dark:text-foam/50">
            {{ payables.owed }} still to receive from suppliers. Open tickets are saved sales that have not been paid.
        </p>
        <ReportTable title="Owed to suppliers" :columns="[{ key: 'supplier', label: 'Supplier' }, { key: 'number', label: 'Order' }, { key: 'total', label: 'Outstanding' }, { key: 'age', label: 'Age' }]" :rows="payables.suppliers" empty="No confirmed order is waiting on a delivery." />
        <ReportTable title="Open tickets" :columns="[{ key: 'name', label: 'Ticket' }, { key: 'branch', label: 'Branch' }, { key: 'total', label: 'Amount' }, { key: 'age', label: 'Age' }]" :rows="payables.tickets" empty="No unpaid tickets." />
    </div>
</template>
