<script setup>
import { Head } from '@inertiajs/vue3';
import CommissionList from '../../components/CommissionList.vue';
import CommissionPeriodFilter from '../../components/CommissionPeriodFilter.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    account: {
        type: Boolean,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    report: {
        type: Object,
        required: true,
    },
    lines: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head title="My Commissions" />

    <Teleport defer to="#admin-header-actions">
        <CommissionPeriodFilter action="/backoffice/me/commissions" :filters="filters" />
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">

        <p v-if="!account" class="rounded-xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">
            Sign in with your employee account to see your commissions.
        </p>

        <CommissionList :report="report" scope="self" />

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">When</th>
                        <th class="px-4 py-3 font-medium">Sale</th>
                        <th class="px-4 py-3 font-medium">Item</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="lines.length === 0">
                        <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="5">No commissions in this period.</td>
                    </tr>
                    <tr v-for="(line, index) in lines" :key="`${line.number}-${index}`" class="border-b border-mist last:border-b-0 dark:border-white/10">
                        <td class="px-4 py-3">{{ line.sold_at }}</td>
                        <td class="px-4 py-3 font-medium">{{ line.number }}</td>
                        <td class="px-4 py-3">{{ line.item }}</td>
                        <td class="px-4 py-3">{{ line.role }}</td>
                        <td class="px-4 py-3">{{ line.amount }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
