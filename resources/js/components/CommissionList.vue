<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    scope: {
        type: String,
        required: true,
    },
    reportHref: {
        type: String,
        default: '',
    },
    expandable: {
        type: Boolean,
        default: false,
    },
});

const expanded = ref([]);
const showEmployees = computed(() => props.scope === 'all' || props.expandable);

function employeeKey(employee) {
    return `${employee.employee_number}-${employee.name}`;
}

function isExpanded(employee) {
    return expanded.value.includes(employeeKey(employee));
}

function toggle(employee) {
    const key = employeeKey(employee);

    expanded.value = expanded.value.includes(key)
        ? expanded.value.filter((item) => item !== key)
        : [...expanded.value, key];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm text-ink/60 dark:text-foam/60">{{ report.label }}</p>
                <Link v-if="reportHref" :href="reportHref" class="shrink-0 text-sm font-medium text-pine dark:text-sand">
                    Commissions report
                </Link>
            </div>
            <p class="text-3xl font-medium">{{ report.total }}</p>
            <p class="text-sm text-ink/55 dark:text-foam/50">
                {{ scope === 'all' ? 'Total commissions' : 'Your commissions' }}
            </p>
        </article>

        <div
            v-if="showEmployees"
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
                    <template v-for="employee in report.employees" :key="employeeKey(employee)">
                        <tr
                            class="border-b border-mist last:border-b-0 dark:border-white/10"
                            :class="expandable ? 'cursor-pointer' : ''"
                            @click="expandable ? toggle(employee) : undefined"
                        >
                            <td class="px-4 py-3 font-medium">
                                <button
                                    v-if="expandable"
                                    class="flex items-center gap-2 text-left font-medium"
                                    type="button"
                                    :aria-expanded="isExpanded(employee)"
                                    :aria-label="`${isExpanded(employee) ? 'Hide' : 'Show'} commissions for ${employee.name}`"
                                    @click.stop="toggle(employee)"
                                >
                                    <span class="inline-block text-xs text-ink/50 transition-transform dark:text-foam/50" :class="isExpanded(employee) ? 'rotate-90' : ''" aria-hidden="true">▶</span>
                                    {{ employee.name }}
                                </button>
                                <template v-else>{{ employee.name }}</template>
                            </td>
                            <td class="px-4 py-3">{{ employee.employee_number }}</td>
                            <td class="px-4 py-3">{{ employee.amount }}</td>
                        </tr>
                        <tr v-if="expandable && isExpanded(employee)" class="border-b border-mist bg-mist/40 last:border-b-0 dark:border-white/10 dark:bg-white/5">
                            <td class="px-4 py-4" colspan="3">
                                <table class="w-full min-w-[36rem] text-left text-sm">
                                    <thead class="text-ink/50 dark:text-foam/50">
                                        <tr>
                                            <th class="pb-2 pr-4 font-medium">Date</th>
                                            <th class="pb-2 pr-4 font-medium">Transaction ID</th>
                                            <th class="pb-2 pr-4 font-medium">Item</th>
                                            <th class="pb-2 pr-4 font-medium">Amount</th>
                                            <th class="pb-2 font-medium">Commission earned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="(employee.lines ?? []).length === 0">
                                            <td class="py-2 text-ink/60 dark:text-foam/60" colspan="5">No commissions in this period.</td>
                                        </tr>
                                        <tr v-for="(line, index) in employee.lines ?? []" :key="`${line.transaction}-${index}`">
                                            <td class="py-1.5 pr-4">{{ line.date }}</td>
                                            <td class="py-1.5 pr-4 font-medium">{{ line.transaction }}</td>
                                            <td class="py-1.5 pr-4">{{ line.item }}</td>
                                            <td class="py-1.5 pr-4">{{ line.amount }}</td>
                                            <td class="py-1.5">{{ line.commission }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
