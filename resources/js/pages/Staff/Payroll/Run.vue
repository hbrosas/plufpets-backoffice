<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    employees: {
        type: Array,
        required: true,
    },
    prepared: {
        type: Object,
        default: null,
    },
    preview: {
        type: Object,
        default: null,
    },
    completed: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const steps = [
    { id: 1, label: 'Period' },
    { id: 2, label: 'Attendance' },
    { id: 3, label: 'Additional items' },
    { id: 4, label: 'Payrolls' },
    { id: 5, label: 'Done' },
];
const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const fieldClass = 'h-11 w-full rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5';

function attendanceRows() {
    const source = props.preview?.draft ?? props.prepared?.employees ?? [];

    return source.map((employee) => ({
        id: employee.id,
        days_worked: String(employee.days_worked ?? ''),
        late_hours: String(employee.late_hours ?? '0'),
        overtime_hours: String(employee.overtime_hours ?? '0'),
        overnight_nights: String(employee.overnight_nights ?? '0'),
        worked_holiday_ids: [...(employee.worked_holiday_ids ?? [])],
        worked_dates: [...(employee.worked_dates ?? [])],
        adjustments: (employee.adjustments ?? []).map((item) => ({ ...item })),
    }));
}

const step = ref(props.completed ? 5 : props.preview ? 4 : props.prepared ? 2 : 1);
const selectedId = ref(props.preview?.employees?.[0]?.id ?? null);
const periodForm = useForm({
    period_start: props.prepared?.period_start ?? props.preview?.period_start ?? '',
    period_end: props.prepared?.period_end ?? props.preview?.period_end ?? '',
    deduct_government: props.prepared?.deduct_government ?? props.preview?.deduct_government ?? true,
    employee_ids: props.prepared?.employee_ids ?? props.employees.map((employee) => employee.id),
});
const runForm = useForm({
    period_start: props.preview?.period_start ?? props.prepared?.period_start ?? '',
    period_end: props.preview?.period_end ?? props.prepared?.period_end ?? '',
    deduct_government: props.preview?.deduct_government ?? props.prepared?.deduct_government ?? true,
    employees: attendanceRows(),
});

const allSelected = computed(() => props.employees.length > 0 && periodForm.employee_ids.length === props.employees.length);
const selected = computed(() => props.preview?.employees?.find((employee) => employee.id === selectedId.value) ?? props.preview?.employees?.[0] ?? null);
const weeks = computed(() => {
    const days = props.prepared?.calendar ?? [];

    if (days.length === 0) {
        return [];
    }

    const cells = Array.from({ length: days[0].weekday }, () => null);
    days.forEach((day) => cells.push(day));

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    const rows = [];

    for (let index = 0; index < cells.length; index += 7) {
        rows.push(cells.slice(index, index + 7));
    }

    return rows;
});
const runErrors = computed(() => [...new Set(Object.values(runForm.errors).filter(Boolean))]);

watch(() => props.prepared, (prepared) => {
    if (!prepared || props.preview || props.completed) {
        return;
    }

    periodForm.period_start = prepared.period_start;
    periodForm.period_end = prepared.period_end;
    periodForm.deduct_government = prepared.deduct_government;
    periodForm.employee_ids = [...prepared.employee_ids];
    runForm.period_start = prepared.period_start;
    runForm.period_end = prepared.period_end;
    runForm.deduct_government = prepared.deduct_government;
    runForm.employees = attendanceRows();
    step.value = 2;
});

watch(() => props.preview, (preview) => {
    if (!preview || props.completed) {
        return;
    }

    runForm.period_start = preview.period_start;
    runForm.period_end = preview.period_end;
    runForm.deduct_government = preview.deduct_government;
    periodForm.deduct_government = preview.deduct_government;
    runForm.employees = attendanceRows();
    selectedId.value = preview.employees?.[0]?.id ?? null;
    step.value = 4;
});

watch(() => props.completed, (completed) => {
    if (completed) {
        step.value = 5;
    }
});

watch(() => runForm.errors, (errors) => {
    const keys = Object.keys(errors);

    if (keys.length === 0 || props.completed) {
        return;
    }

    if (keys.some((key) => key.startsWith('period_') || key === 'deduct_government' || key === 'employee_ids')) {
        step.value = 1;

        return;
    }

    if (keys.some((key) => /days_worked|late_hours|overtime_hours|overnight_nights|worked_holiday|worked_dates/.test(key))) {
        step.value = 2;

        return;
    }

    if (keys.some((key) => key.includes('adjustments') || key.includes('.id'))) {
        step.value = 3;
    }
}, { deep: true });

function toggleAll(event) {
    periodForm.employee_ids = event.target.checked ? props.employees.map((employee) => employee.id) : [];
}

function continuePeriod() {
    periodForm.post('/backoffice/staff/payroll/prepare', {
        preserveScroll: true,
    });
}

function generate() {
    runForm.period_start = props.prepared?.period_start ?? runForm.period_start;
    runForm.period_end = props.prepared?.period_end ?? runForm.period_end;
    runForm.deduct_government = props.prepared?.deduct_government ?? runForm.deduct_government;
    runForm.employees.forEach(applyWorkedDays);
    runForm.post('/backoffice/staff/payroll/preview', {
        preserveScroll: true,
    });
}

function finalize() {
    if (!window.confirm('Finalize payroll for every selected employee? Their payslips will be saved.')) {
        return;
    }

    runForm.period_start = props.preview?.period_start ?? runForm.period_start;
    runForm.period_end = props.preview?.period_end ?? runForm.period_end;
    runForm.deduct_government = props.preview?.deduct_government ?? runForm.deduct_government;
    runForm.employees.forEach(applyWorkedDays);
    runForm.post('/backoffice/staff/payroll/finalize', {
        preserveScroll: true,
    });
}

function employeeDetails(id) {
    return props.prepared?.employees?.find((employee) => employee.id === id) ?? {
        name: 'Employee',
        employee_number: '',
        commissions: [],
        commission_total: '₱0.00',
        cash_advances: [],
        cash_advance_total: '₱0.00',
    };
}

function holidayDates() {
    return new Set((props.prepared?.holidays ?? []).map((holiday) => holiday.date));
}

function toggleWorked(employee, date) {
    const index = employee.worked_dates.indexOf(date);

    if (index === -1) {
        employee.worked_dates.push(date);
    } else {
        employee.worked_dates.splice(index, 1);
    }
}

function ordinaryCount(employee) {
    const holidays = holidayDates();

    return employee.worked_dates.filter((date) => !holidays.has(date)).length;
}

function holidayCount(employee) {
    const holidays = holidayDates();

    return employee.worked_dates.filter((date) => holidays.has(date)).length;
}

function filingsFor(day, employeeId) {
    return (day.filings ?? []).filter((filing) => filing.employee_id === employeeId);
}

function dayClass(day, employee) {
    const worked = employee.worked_dates.includes(day.date);

    if (worked) {
        return day.holiday
            ? 'border-sand bg-sand/30'
            : 'border-pine bg-pine/15 dark:border-sand dark:bg-sand/20';
    }

    if (day.holiday) {
        return 'border-sand/60 bg-sand/10';
    }

    if (day.is_day_off) {
        return 'border-mist bg-mist/40 text-ink/50 dark:border-white/10 dark:text-foam/50';
    }

    return 'border-mist dark:border-white/10';
}

function applyWorkedDays(employee) {
    const holidays = new Map((props.prepared?.holidays ?? []).map((holiday) => [holiday.date, holiday.id]));
    const dates = [...new Set(employee.worked_dates)];
    const holidayIds = [];
    let ordinary = 0;

    dates.forEach((date) => {
        if (holidays.has(date)) {
            holidayIds.push(holidays.get(date));
        } else {
            ordinary += 1;
        }
    });

    employee.days_worked = String(ordinary);
    employee.worked_holiday_ids = holidayIds;
    employee.worked_dates = dates;
}

function addAdjustment(index) {
    runForm.employees[index].adjustments.push({
        name: '',
        side: 'earning',
        amount: '',
    });
}

function removeAdjustment(employeeIndex, index) {
    runForm.employees[employeeIndex].adjustments.splice(index, 1);
}

function openStep(id) {
    if (props.completed || id >= step.value || id === 5) {
        return;
    }

    step.value = id;
}
</script>

<template>
    <Head title="Run Payroll" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <ol class="flex gap-2 overflow-x-auto">
                <li v-for="item in steps" :key="item.id">
                    <button
                        class="flex h-10 shrink-0 items-center gap-2 rounded-full px-3 text-sm whitespace-nowrap"
                        :class="step === item.id ? 'bg-pine text-foam' : 'text-ink/70 dark:text-foam/70'"
                        :disabled="completed || item.id >= step || item.id === 5"
                        type="button"
                        @click="openStep(item.id)"
                    >
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-xs" :class="step === item.id ? 'bg-white/20' : 'bg-mist text-ink dark:bg-white/10 dark:text-foam'">{{ item.id }}</span>
                        {{ item.label }}
                    </button>
                </li>
            </ol>
            <Link
                v-if="prepared || preview || completed"
                class="shrink-0 text-sm font-medium text-pine dark:text-sand"
                href="/backoffice/staff/payroll?new=1"
            >
                Start over
            </Link>
        </div>

        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>
        <div v-if="runErrors.length > 0" class="rounded-xl border border-danger/30 bg-white px-4 py-3 text-sm text-danger dark:bg-white/5">
            <p v-for="message in runErrors" :key="message">{{ message }}</p>
        </div>

        <section v-if="step === 1" class="rounded-2xl border border-mist bg-white p-4 sm:p-6 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-semibold">Payroll period</h2>
            <p class="mt-1 text-sm text-ink/60 dark:text-foam/60">Choose the period and the employees included in this run. A period can be at most 31 days.</p>
            <p v-if="employees.length === 0" class="mt-6 text-sm text-ink/60 dark:text-foam/60">
                No employee records yet. Generate one from Users.
            </p>
            <form v-else class="mt-6 flex flex-col gap-6" @submit.prevent="continuePeriod">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="font-medium">Start date</span>
                        <input v-model="periodForm.period_start" :class="fieldClass" required type="date" />
                        <span v-if="periodForm.errors.period_start" class="text-danger">{{ periodForm.errors.period_start }}</span>
                    </label>
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="font-medium">End date</span>
                        <input v-model="periodForm.period_end" :class="fieldClass" required type="date" />
                        <span v-if="periodForm.errors.period_end" class="text-danger">{{ periodForm.errors.period_end }}</span>
                    </label>
                </div>
                <fieldset class="flex flex-col gap-3">
                    <legend class="text-sm font-medium">Will you deduct government benefits?</legend>
                    <p class="text-sm text-ink/60 dark:text-foam/60">Yes deducts each employee's SSS, PhilHealth, and Pag-IBIG share.</p>
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="periodForm.deduct_government" type="radio" :value="true" />
                        Yes
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="periodForm.deduct_government" type="radio" :value="false" />
                        No
                    </label>
                    <p v-if="periodForm.errors.deduct_government" class="text-sm text-danger">{{ periodForm.errors.deduct_government }}</p>
                </fieldset>
                <fieldset class="flex flex-col gap-3">
                    <legend class="text-sm font-medium">Employees</legend>
                    <label class="flex items-center gap-3 text-sm">
                        <input :checked="allSelected" type="checkbox" @change="toggleAll" />
                        All employees
                    </label>
                    <label v-for="employee in employees" :key="employee.id" class="flex items-center gap-3 text-sm">
                        <input v-model="periodForm.employee_ids" type="checkbox" :value="employee.id" />
                        <span>{{ employee.name }}</span>
                        <span class="text-ink/50 dark:text-foam/50">{{ employee.employee_number }}</span>
                    </label>
                    <p v-if="periodForm.errors.employee_ids" class="text-sm text-danger">{{ periodForm.errors.employee_ids }}</p>
                </fieldset>
                <div>
                    <button class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70" :disabled="periodForm.processing || periodForm.employee_ids.length === 0" type="submit">
                        {{ periodForm.processing ? 'Continuing…' : 'Continue' }}
                    </button>
                </div>
            </form>
        </section>

        <section v-else-if="step === 2 && prepared" class="flex flex-col gap-6">
            <div>
                <h2 class="text-lg font-semibold">{{ prepared.period_label }}</h2>
                <p class="mt-1 text-sm text-ink/60 dark:text-foam/60">Select the days each employee worked. Ordinary days and holidays are counted from those days. Sunday starts unselected.</p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs">
                    <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm border border-pine bg-pine/15 dark:border-sand dark:bg-sand/20" /> Worked</span>
                    <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm border border-sand bg-sand/10" /> Holiday</span>
                    <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-danger/20" /> Leave</span>
                    <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-pine/20 dark:bg-sand/30" /> Overnight</span>
                    <span class="text-ink/55 dark:text-foam/50">A dashed border is still pending.</span>
                </div>
            </div>

            <article v-for="(employee, index) in runForm.employees" :key="employee.id" class="rounded-2xl border border-mist bg-white p-4 sm:p-6 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-base font-semibold">{{ employeeDetails(employee.id).name }}</h2>
                <p class="text-sm text-ink/50 dark:text-foam/50">{{ employeeDetails(employee.id).employee_number }}</p>
                <div class="mt-4 overflow-x-auto">
                    <div class="grid min-w-[36rem] grid-cols-7 gap-2 text-center text-xs">
                        <div v-for="weekday in weekdays" :key="`${employee.id}-${weekday}`" class="py-2 font-medium text-ink/50 dark:text-foam/50">{{ weekday }}</div>
                        <div
                            v-for="(day, dayIndex) in weeks.flat()"
                            :key="day?.date ?? `${employee.id}-empty-${dayIndex}`"
                            class="min-h-24 rounded-xl border"
                            :class="day ? dayClass(day, employee) : 'border-transparent'"
                        >
                            <button
                                v-if="day"
                                class="flex h-full w-full flex-col px-2 py-2 text-left hover:bg-sand/20"
                                type="button"
                                :aria-pressed="employee.worked_dates.includes(day.date)"
                                @click="toggleWorked(employee, day.date)"
                            >
                                <span class="text-center font-medium">{{ day.day }}</span>
                                <span v-if="employee.worked_dates.includes(day.date)" class="mt-1 text-center">Worked</span>
                                <span v-else-if="day.is_day_off" class="mt-1 text-center">Off</span>
                                <span v-if="day.holiday" class="mt-1 text-center leading-4">{{ day.holiday.name }}</span>
                                <ul class="mt-1 flex flex-col gap-1">
                                    <li
                                        v-for="filing in filingsFor(day, employee.id)"
                                        :key="`${employee.id}-${day.date}-${filing.id}`"
                                        class="truncate rounded-md px-1.5 py-0.5"
                                        :class="[
                                            filing.type === 'leave' ? 'bg-danger/10 text-danger' : 'bg-pine/10 text-pine dark:bg-sand/15 dark:text-sand',
                                            filing.status === 'pending' ? 'border border-dashed border-current' : '',
                                        ]"
                                        :title="filing.reason || filing.detail"
                                    >
                                        {{ filing.type_label }}
                                    </li>
                                </ul>
                            </button>
                        </div>
                    </div>
                </div>
                <p class="mt-4 text-sm font-medium">Ordinary days {{ ordinaryCount(employee) }} · Holidays worked {{ holidayCount(employee) }}</p>
                <p v-if="runForm.errors[`employees.${index}.days_worked`]" class="mt-1 text-sm text-danger">{{ runForm.errors[`employees.${index}.days_worked`] }}</p>
                <p v-if="runForm.errors[`employees.${index}.worked_dates`]" class="mt-1 text-sm text-danger">{{ runForm.errors[`employees.${index}.worked_dates`] }}</p>
                <p v-if="runForm.errors[`employees.${index}.worked_holiday_ids`]" class="mt-1 text-sm text-danger">{{ runForm.errors[`employees.${index}.worked_holiday_ids`] }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="font-medium">Overnights</span>
                        <input v-model="employee.overnight_nights" :class="fieldClass" inputmode="numeric" type="text" />
                        <span class="text-ink/50 dark:text-foam/50">Filled from approved overnight filings in this period.</span>
                        <span v-if="runForm.errors[`employees.${index}.overnight_nights`]" class="text-danger">{{ runForm.errors[`employees.${index}.overnight_nights`] }}</span>
                    </label>
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="font-medium">Overtime in hrs</span>
                        <input v-model="employee.overtime_hours" :class="fieldClass" inputmode="decimal" type="text" />
                        <span v-if="runForm.errors[`employees.${index}.overtime_hours`]" class="text-danger">{{ runForm.errors[`employees.${index}.overtime_hours`] }}</span>
                    </label>
                    <label class="flex flex-col gap-2 text-sm">
                        <span class="font-medium">Late in hrs</span>
                        <input v-model="employee.late_hours" :class="fieldClass" inputmode="decimal" type="text" />
                        <span v-if="runForm.errors[`employees.${index}.late_hours`]" class="text-danger">{{ runForm.errors[`employees.${index}.late_hours`] }}</span>
                    </label>
                </div>
            </article>

            <div class="flex flex-wrap gap-3">
                <button class="h-11 rounded-xl border border-mist px-5 text-sm font-medium dark:border-white/15" type="button" @click="step = 1">Back</button>
                <button class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam" type="button" @click="step = 3">Continue</button>
            </div>
        </section>

        <section v-else-if="step === 3 && prepared" class="flex flex-col gap-6">
            <article v-for="(employee, index) in runForm.employees" :key="employee.id" class="rounded-2xl border border-mist bg-white p-4 sm:p-6 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-base font-semibold">{{ employeeDetails(employee.id).name }}</h2>
                <p class="text-sm text-ink/50 dark:text-foam/50">{{ employeeDetails(employee.id).employee_number }}</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-medium">Computed commissions</h3>
                        <ul class="mt-2 flex flex-col gap-1 text-sm">
                            <li v-for="line in employeeDetails(employee.id).commissions" :key="line.name" class="flex justify-between gap-3">
                                <span>{{ line.name }}</span>
                                <span>{{ line.amount_label }}</span>
                            </li>
                        </ul>
                        <p class="mt-2 text-sm font-medium">{{ employeeDetails(employee.id).commission_total }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium">Computed cash advances</h3>
                        <p v-if="employeeDetails(employee.id).cash_advances.length === 0" class="mt-2 text-sm text-ink/60 dark:text-foam/60">No open cash advances.</p>
                        <ul v-else class="mt-2 flex flex-col gap-1 text-sm">
                            <li v-for="advance in employeeDetails(employee.id).cash_advances" :key="advance.id" class="flex justify-between gap-3">
                                <span>{{ advance.reason || 'Cash advance' }}</span>
                                <span>{{ advance.amount_label }}</span>
                            </li>
                        </ul>
                        <p class="mt-2 text-sm font-medium">{{ employeeDetails(employee.id).cash_advance_total }}</p>
                    </div>
                </div>
                <div class="mt-6 flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-medium">One-time items</h3>
                        <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="addAdjustment(index)">Add item</button>
                    </div>
                    <p class="text-sm text-ink/60 dark:text-foam/60">Add an earning or a deduction that belongs only to this payroll.</p>
                    <div v-for="(item, itemIndex) in employee.adjustments" :key="`${employee.id}-item-${itemIndex}`" class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_10rem_auto]">
                        <input v-model="item.name" :class="fieldClass" placeholder="Name" type="text" />
                        <select v-model="item.side" :class="fieldClass">
                            <option value="earning">Earning</option>
                            <option value="deduction">Deduction</option>
                        </select>
                        <input v-model="item.amount" :class="fieldClass" min="0.01" placeholder="Amount" step="0.01" type="number" />
                        <button class="h-11 px-2 text-sm font-medium text-danger" type="button" @click="removeAdjustment(index, itemIndex)">Remove</button>
                        <p v-if="runForm.errors[`employees.${index}.adjustments.${itemIndex}.name`]" class="text-sm text-danger sm:col-span-4">
                            {{ runForm.errors[`employees.${index}.adjustments.${itemIndex}.name`] }}
                        </p>
                        <p v-if="runForm.errors[`employees.${index}.adjustments.${itemIndex}.side`]" class="text-sm text-danger sm:col-span-4">
                            {{ runForm.errors[`employees.${index}.adjustments.${itemIndex}.side`] }}
                        </p>
                        <p v-if="runForm.errors[`employees.${index}.adjustments.${itemIndex}.amount`]" class="text-sm text-danger sm:col-span-4">
                            {{ runForm.errors[`employees.${index}.adjustments.${itemIndex}.amount`] }}
                        </p>
                    </div>
                </div>
            </article>
            <div class="flex flex-wrap gap-3">
                <button class="h-11 rounded-xl border border-mist px-5 text-sm font-medium dark:border-white/15" type="button" @click="step = 2">Back</button>
                <button class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70" :disabled="runForm.processing" type="button" @click="generate">
                    {{ runForm.processing ? 'Generating…' : 'Generate payrolls' }}
                </button>
            </div>
        </section>

        <section v-else-if="step === 4 && preview" class="flex flex-col gap-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold">{{ preview.period_label }}</h2>
                <p class="text-sm font-medium">Payroll total {{ preview.total_label }}</p>
            </div>
            <div class="grid items-start gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <div class="flex flex-col gap-2">
                    <button
                        v-for="employee in preview.employees"
                        :key="employee.id"
                        class="rounded-2xl border px-4 py-3 text-left"
                        :class="selected?.id === employee.id ? 'border-pine bg-pine text-foam' : 'border-mist bg-white dark:border-white/10 dark:bg-white/5'"
                        type="button"
                        @click="selectedId = employee.id"
                    >
                        <span class="block text-sm font-medium">{{ employee.name }}</span>
                        <span class="block text-sm" :class="selected?.id === employee.id ? 'text-foam/80' : 'text-ink/60 dark:text-foam/60'">{{ employee.net_label }}</span>
                    </button>
                </div>
                <iframe
                    v-if="selected"
                    class="h-[44rem] w-full rounded-2xl border border-mist bg-white"
                    :srcdoc="selected.preview_html"
                    title="Payroll preview"
                />
            </div>
            <div class="flex flex-wrap gap-3">
                <button class="h-11 rounded-xl border border-mist px-5 text-sm font-medium dark:border-white/15" type="button" @click="step = 3">Back</button>
                <button class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70" :disabled="runForm.processing" type="button" @click="finalize">
                    {{ runForm.processing ? 'Finalizing…' : 'Finalize Payroll' }}
                </button>
            </div>
        </section>

        <section v-else-if="step === 5 && completed" class="rounded-2xl border border-mist bg-white p-4 sm:p-6 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Payroll saved</h2>
                    <p class="mt-1 text-sm text-ink/60 dark:text-foam/60">{{ completed.period_label }}. These payslips are on each employee's My Payroll page.</p>
                </div>
                <a class="inline-flex h-11 items-center rounded-xl bg-pine px-5 text-sm font-medium text-foam" :href="completed.download_all_url">Download all</a>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-3 py-3 font-medium">Employee</th>
                            <th class="px-3 py-3 text-right font-medium">Gross</th>
                            <th class="px-3 py-3 text-right font-medium">Deductions</th>
                            <th class="px-3 py-3 text-right font-medium">Net</th>
                            <th class="px-3 py-3 font-medium"><span class="sr-only">Download</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="employee in completed.employees" :key="employee.id" class="border-b border-mist dark:border-white/10">
                            <td class="px-3 py-3">
                                <span class="block font-medium">{{ employee.name }}</span>
                                <span class="block text-ink/50 dark:text-foam/50">{{ employee.employee_number }}</span>
                            </td>
                            <td class="px-3 py-3 text-right">{{ employee.gross_label }}</td>
                            <td class="px-3 py-3 text-right">{{ employee.deductions_label }}</td>
                            <td class="px-3 py-3 text-right font-medium">{{ employee.net_label }}</td>
                            <td class="px-3 py-3 text-right">
                                <a class="font-medium text-pine dark:text-sand" :href="employee.download_url">Download</a>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="px-3 py-3 font-semibold" colspan="3">Payroll total</td>
                            <td class="px-3 py-3 text-right font-semibold">{{ completed.total_label }}</td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</template>
