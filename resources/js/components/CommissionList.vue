<script setup>
defineProps({
    report: {
        type: Object,
        required: true,
    },
    scope: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-ink/60 dark:text-foam/60">{{ report.label }}</p>
            <p class="text-3xl font-medium">{{ report.total }}</p>
            <p class="text-sm text-ink/55 dark:text-foam/50">
                {{ scope === 'all' ? 'Total commissions' : 'Your commissions' }}
            </p>
        </article>

        <div
            v-if="scope === 'all'"
            class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5"
        >
            <table class="w-full min-w-[28rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Employee</th>
                        <th class="px-4 py-3 font-medium">Number</th>
                        <th class="px-4 py-3 font-medium">Commission</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="report.employees.length === 0">
                        <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="3">No commissions in this period.</td>
                    </tr>
                    <tr
                        v-for="employee in report.employees"
                        :key="`${employee.employee_number}-${employee.name}`"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ employee.name }}</td>
                        <td class="px-4 py-3">{{ employee.employee_number }}</td>
                        <td class="px-4 py-3">{{ employee.amount }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
