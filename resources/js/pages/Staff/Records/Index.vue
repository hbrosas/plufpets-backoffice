<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    records: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head title="Employees Records" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">EMPID</th>
                        <th class="px-4 py-3 font-medium">Job title</th>
                        <th class="px-4 py-3 font-medium">Employment status</th>
                        <th class="px-4 py-3 font-medium">Mobile</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="records.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="6">
                            No employee records yet. Generate one from Staff.
                        </td>
                    </tr>
                    <tr
                        v-for="record in records"
                        :key="record.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ record.name }}</td>
                        <td class="px-4 py-3">{{ record.employee_number }}</td>
                        <td class="px-4 py-3">{{ record.job_title || '—' }}</td>
                        <td class="px-4 py-3">{{ record.employment_status || '—' }}</td>
                        <td class="px-4 py-3">{{ record.mobile || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end">
                                <Link
                                    class="font-medium text-pine dark:text-sand"
                                    :href="`/backoffice/staff/${record.id}/record`"
                                >
                                    Open
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
