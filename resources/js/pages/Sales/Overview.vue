<script setup>
import { Head } from '@inertiajs/vue3';
import SalesDailyChart from '../../components/SalesDailyChart.vue';
import SalesFilters from '../../components/SalesFilters.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    filters: { type: Object, required: true },
    period_label: { type: String, required: true },
    branches: { type: Array, required: true },
    summary: { type: Object, required: true },
    by_branch: { type: Array, required: true },
    by_payment: { type: Array, required: true },
    daily: { type: Array, required: true },
});
</script>

<template>
    <Head title="Sales" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters action="/backoffice/sales" :filters="filters" :branches="branches" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Sales summary">
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Receipts</p>
                <p class="text-3xl font-medium">{{ summary.receipts }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">{{ period_label }}</p>
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Sales</p>
                <p class="text-3xl font-medium">{{ summary.total }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">Including tax</p>
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Tax</p>
                <p class="text-3xl font-medium">{{ summary.tax }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">Collected</p>
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Average ticket</p>
                <p class="text-3xl font-medium">{{ summary.average }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">Per receipt</p>
            </article>
        </section>

        <SalesDailyChart :daily="daily" />

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="flex flex-col gap-4 overflow-x-auto rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-lg font-medium">By branch</h2>
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="py-2 pr-4 font-medium">Branch</th>
                            <th class="py-2 pr-4 font-medium">Receipts</th>
                            <th class="py-2 font-medium">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="by_branch.length === 0">
                            <td class="py-8 text-ink/60 dark:text-foam/60" colspan="3">No sales in this period.</td>
                        </tr>
                        <tr v-for="row in by_branch" :key="row.name" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="py-3 pr-4 font-medium">{{ row.name }}</td>
                            <td class="py-3 pr-4">{{ row.receipts }}</td>
                            <td class="py-3">{{ row.total }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>

            <article class="flex flex-col gap-4 overflow-x-auto rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-lg font-medium">By payment</h2>
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="py-2 pr-4 font-medium">Method</th>
                            <th class="py-2 pr-4 font-medium">Tenders</th>
                            <th class="py-2 font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="by_payment.length === 0">
                            <td class="py-8 text-ink/60 dark:text-foam/60" colspan="3">No payments in this period.</td>
                        </tr>
                        <tr v-for="row in by_payment" :key="row.name" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="py-3 pr-4 font-medium">{{ row.name }}</td>
                            <td class="py-3 pr-4">{{ row.receipts }}</td>
                            <td class="py-3">{{ row.total }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </section>
    </div>
</template>
