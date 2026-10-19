<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SalesFilters from '../../../components/SalesFilters.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    filters: { type: Object, required: true },
    period_label: { type: String, required: true },
    branches: { type: Array, required: true },
    shifts: { type: Object, required: true },
});

const openShift = ref(null);
</script>

<template>
    <Head title="Shifts" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters action="/backoffice/sales/shifts" :filters="filters" :branches="branches" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p class="text-sm text-ink/55 dark:text-foam/50">{{ period_label }}</p>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[56rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Branch</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Opened</th>
                        <th class="px-4 py-3 font-medium">Closed</th>
                        <th class="px-4 py-3 font-medium">Receipts</th>
                        <th class="px-4 py-3 font-medium">Sales</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="shifts.data.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="7">No shifts in this period.</td>
                    </tr>
                    <tr
                        v-for="shift in shifts.data"
                        :key="shift.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ shift.branch }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    shift.status === 'Open'
                                        ? 'bg-pine/10 text-pine dark:bg-sand/15 dark:text-sand'
                                        : 'bg-mist text-ink/50 dark:bg-white/10 dark:text-foam/50'
                                "
                            >
                                {{ shift.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ shift.opened_at }}</p>
                            <p class="text-xs text-ink/50">{{ shift.opened_by }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <template v-if="shift.closed_at">
                                <p>{{ shift.closed_at }}</p>
                                <p class="text-xs text-ink/50">{{ shift.closed_by }}</p>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-4 py-3">{{ shift.receipts }}</td>
                        <td class="px-4 py-3">{{ shift.sales_total }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <button
                                    class="h-9 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                                    type="button"
                                    @click="openShift = shift"
                                >
                                    Show Transactions
                                </button>
                                <a
                                    v-if="shift.z_report_href"
                                    class="inline-flex h-9 items-center rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                                    :href="shift.z_report_href"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    View Z-Report
                                </a>
                                <button
                                    v-else
                                    class="h-9 cursor-not-allowed rounded-xl border border-mist px-3 text-sm font-medium opacity-40 dark:border-white/15"
                                    type="button"
                                    disabled
                                    title="Close the shift to view a Z-Report"
                                >
                                    View Z-Report
                                </button>
                                <a
                                    v-if="shift.z_report_print_href"
                                    class="inline-flex h-9 items-center rounded-xl bg-pine px-3 text-sm font-medium text-foam"
                                    :href="shift.z_report_print_href"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Print Z-Report
                                </a>
                                <button
                                    v-else
                                    class="h-9 cursor-not-allowed rounded-xl bg-pine px-3 text-sm font-medium text-foam opacity-40"
                                    type="button"
                                    disabled
                                    title="Close the shift to print a Z-Report"
                                >
                                    Print Z-Report
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="shifts.last_page > 1" class="flex gap-3" aria-label="Pagination">
            <Link
                v-if="shifts.prev_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="shifts.prev_page_url"
                :preserve-state="true"
            >
                Previous
            </Link>
            <Link
                v-if="shifts.next_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="shifts.next_page_url"
                :preserve-state="true"
            >
                Next
            </Link>
        </nav>
    </div>

    <div v-if="openShift" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[80vh] w-full max-w-2xl flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">{{ openShift.branch }} transactions</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">{{ openShift.opened_at }}</p>
                </div>
                <button class="text-sm font-medium" type="button" @click="openShift = null">Close</button>
            </div>
            <div class="min-h-0 overflow-y-auto">
                <p v-if="openShift.transactions.length === 0" class="py-8 text-sm text-ink/60 dark:text-foam/60">
                    No transactions on this shift.
                </p>
                <ul v-else class="flex flex-col gap-2">
                    <li
                        v-for="row in openShift.transactions"
                        :key="row.key"
                        class="flex items-start justify-between gap-3 rounded-xl border border-mist px-3 py-3 text-sm dark:border-white/10"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">{{ row.title }}</p>
                            <p class="text-xs text-ink/50">{{ row.type }} · {{ row.time }} · {{ row.actor }}</p>
                            <p v-if="row.detail" class="mt-1 text-xs text-ink/55 dark:text-foam/55">{{ row.detail }}</p>
                        </div>
                        <p class="shrink-0 font-medium">{{ row.amount }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
