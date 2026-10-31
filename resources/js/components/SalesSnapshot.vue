<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    sales: {
        type: Object,
        required: true,
    },
    showReportLink: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <section class="flex flex-col gap-4" aria-label="Sales">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h2 class="text-lg font-medium">Sales</h2>
                <p class="text-sm text-ink/55 dark:text-foam/50">
                    This month: {{ sales.receipts.sold }} receipts, {{ sales.receipts.refunded }} refunded.
                    Refunded receipts are left out of the totals.
                </p>
            </div>
            <Link
                v-if="showReportLink"
                href="/backoffice/reports/sales"
                class="text-sm font-medium text-pine dark:text-sand"
            >
                Sales report
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <article
                v-for="period in sales.periods"
                :key="period.key"
                class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
            >
                <p class="text-sm text-ink/60 dark:text-foam/60">{{ period.label }}</p>
                <p class="text-3xl font-medium">{{ period.total }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">
                    {{ period.receipts }} {{ period.receipts === 1 ? 'receipt' : 'receipts' }}
                </p>
            </article>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Branch</th>
                        <th class="px-4 py-3 font-medium">Today</th>
                        <th class="px-4 py-3 font-medium">This month</th>
                        <th class="px-4 py-3 font-medium">This quarter</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="sales.branches.length === 0">
                        <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="4">No branches yet.</td>
                    </tr>
                    <tr
                        v-for="branch in sales.branches"
                        :key="branch.name"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ branch.name }}</td>
                        <td class="px-4 py-3">{{ branch.today }}</td>
                        <td class="px-4 py-3">{{ branch.month }}</td>
                        <td class="px-4 py-3">{{ branch.quarter }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h3 class="text-lg font-medium">Services done</h3>
                <dl class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-ink/55 dark:text-foam/50">Today</dt>
                        <dd class="text-2xl font-medium">{{ sales.services.today }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-ink/55 dark:text-foam/50">This month</dt>
                        <dd class="text-2xl font-medium">{{ sales.services.month }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-ink/55 dark:text-foam/50">This quarter</dt>
                        <dd class="text-2xl font-medium">{{ sales.services.quarter }}</dd>
                    </div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h3 class="text-lg font-medium">Top products this month</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="py-2 pr-3 font-medium">Product</th>
                                <th class="py-2 pr-3 font-medium">Qty</th>
                                <th class="py-2 font-medium">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="sales.top_products.length === 0">
                                <td class="py-6 text-ink/60 dark:text-foam/60" colspan="3">No products sold this month.</td>
                            </tr>
                            <tr
                                v-for="product in sales.top_products"
                                :key="product.name"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="py-2 pr-3">{{ product.name }}</td>
                                <td class="py-2 pr-3">{{ product.quantity }}</td>
                                <td class="py-2">{{ product.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </section>
</template>
