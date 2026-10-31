<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatAmount, formatPeso } from '../../formatPeso';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    staff: { type: Object, required: true },
    profile: { type: Object, required: true },
    services: { type: Array, required: true },
    genders: { type: Array, required: true },
    civilStatuses: { type: Array, required: true },
    employmentStatuses: { type: Array, required: true },
    payBases: { type: Array, required: true },
    payrollAssumptions: { type: Object, required: true },
    payroll: { type: Object, default: null },
    holidays: { type: Array, default: () => [] },
    finalizedPayrolls: { type: Array, default: () => [] },
});

const tab = ref(props.payroll ? 'payroll' : 'record');
const tabs = [
    { id: 'record', label: 'Employee 201 Record' },
    { id: 'compensation', label: 'Compensation' },
    { id: 'payroll', label: 'Payroll' },
];

const compensationSuggestions = ['Basic salary', 'COLA'];
const benefitSuggestions = ['Rice allowance', 'Clothing allowance', 'Laundry allowance', 'Medical cash allowance'];
const governmentSuggestions = ['SSS', 'PhilHealth', 'Pag-IBIG'];

const form = useForm({
    first_name: props.profile.first_name,
    middle_name: props.profile.middle_name,
    last_name: props.profile.last_name,
    gender: props.profile.gender,
    civil_status: props.profile.civil_status,
    address: props.profile.address,
    mobile: props.profile.mobile,
    email: props.profile.email,
    tin: props.profile.tin,
    sss: props.profile.sss,
    philhealth: props.profile.philhealth,
    pagibig: props.profile.pagibig,
    job_title: props.profile.job_title,
    employment_status: props.profile.employment_status,
    emergency_contact_name: props.profile.emergency_contact_name,
    emergency_contact_relationship: props.profile.emergency_contact_relationship,
    emergency_contact_phone: props.profile.emergency_contact_phone,
    pay_basis: props.profile.pay_basis,
    overnight_allowance: props.profile.overnight_allowance ?? '',
    compensation_lines: props.profile.compensation_lines.map((line) => ({ ...line })),
    benefit_lines: props.profile.benefit_lines.map((line) => ({ ...line })),
    government_lines: props.profile.government_lines.map((line) => ({ ...line })),
    commissions: props.services.map((service) => {
        const saved = props.profile.commissions.find((row) => row.catalog_item_id === service.id);

        return {
            catalog_item_id: service.id,
            name: service.name,
            assigned_rate: saved && Number(saved.assigned_rate) > 0 ? saved.assigned_rate : '',
            assisted_rate: saved && Number(saved.assisted_rate) > 0 ? saved.assisted_rate : '',
        };
    }),
});

const fieldClass =
    'h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5';

function linesKey(kind) {
    if (kind === 'compensation') {
        return 'compensation_lines';
    }

    if (kind === 'government') {
        return 'government_lines';
    }

    return 'benefit_lines';
}

function addLine(kind, name = '') {
    if (kind === 'government') {
        form.government_lines.push({
            name,
            employee_contribution: '',
            employer_contribution: '',
        });

        return;
    }

    form[linesKey(kind)].push({ name, amount: '' });
}

function addSuggested(kind, name) {
    const lines = form[linesKey(kind)];

    if (lines.some((line) => line.name.trim().toLowerCase() === name.toLowerCase())) {
        return;
    }

    addLine(kind, name);
}

function removeLine(kind, index) {
    form[linesKey(kind)].splice(index, 1);
}

function showGroup(lines, index) {
    const line = lines[index];

    return Boolean(line?.group) && (index === 0 || lines[index - 1]?.group !== line.group);
}

function formatDate(value) {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function submit() {
    form.put(`/backoffice/staff/${props.staff.id}/record`, {
        preserveScroll: true,
    });
}

const payrollForm = useForm({
    period_start: props.payroll?.period_start ?? '',
    period_end: props.payroll?.period_end ?? '',
    days_worked: props.payroll?.days_worked ?? '',
    late_hours: props.payroll?.late_hours ?? '0',
    overtime_hours: props.payroll?.overtime_hours ?? '0',
    overnight_nights: props.payroll?.overnight_nights ?? '0',
    deduct_government: props.payroll?.deduct_government ?? true,
    worked_holiday_ids: props.payroll?.worked_holiday_ids ?? [],
    adjustments: (props.payroll?.adjustments ?? []).map((item) => ({ ...item })),
});

const periodHolidays = computed(() => {
    if (!payrollForm.period_start || !payrollForm.period_end) {
        return [];
    }

    return props.holidays.filter((holiday) => holiday.date >= payrollForm.period_start && holiday.date <= payrollForm.period_end);
});

function generatePayroll() {
    payrollForm.post(`/backoffice/staff/${props.staff.id}/payroll`, {
        preserveScroll: true,
    });
}

function payrollQuery() {
    const params = new URLSearchParams({
        period_start: props.payroll.period_start,
        period_end: props.payroll.period_end,
        days_worked: props.payroll.days_worked,
        late_hours: props.payroll.late_hours,
        overtime_hours: props.payroll.overtime_hours,
        deduct_government: props.payroll.deduct_government ? '1' : '0',
        overnight_nights: props.payroll.overnight_nights,
    });

    props.payroll.worked_holiday_ids.forEach((id) => {
        params.append('worked_holiday_ids[]', id);
    });

    (props.payroll.adjustments ?? []).forEach((item, index) => {
        params.append(`adjustments[${index}][name]`, item.name);
        params.append(`adjustments[${index}][side]`, item.side);
        params.append(`adjustments[${index}][amount]`, item.amount);
    });

    return params;
}

function payrollDownloadHref() {
    return `/backoffice/staff/${props.staff.id}/payroll/download?${payrollQuery().toString()}`;
}

function addAdjustment() {
    payrollForm.adjustments.push({
        name: '',
        side: 'earning',
        amount: '',
    });
}

function removeAdjustment(index) {
    payrollForm.adjustments.splice(index, 1);
}

function finalizePayroll() {
    router.post(`/backoffice/staff/${props.staff.id}/payroll/finalize`, {
        period_start: props.payroll.period_start,
        period_end: props.payroll.period_end,
        days_worked: props.payroll.days_worked,
        late_hours: props.payroll.late_hours,
        overtime_hours: props.payroll.overtime_hours,
        overnight_nights: props.payroll.overnight_nights,
        deduct_government: props.payroll.deduct_government,
        worked_holiday_ids: props.payroll.worked_holiday_ids,
        adjustments: props.payroll.adjustments,
    }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${staff.name} · Employee record`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="item in tabs"
                :key="item.id"
                class="h-10 rounded-xl px-4 text-sm font-medium"
                :class="tab === item.id ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                @click="tab = item.id"
            >
                {{ item.label }}
            </button>
        </div>

        <form v-show="tab !== 'payroll'" class="flex flex-col gap-6" @submit.prevent="submit">
        <div v-show="tab === 'record'" class="flex flex-col gap-6">
            <section class="grid gap-4 rounded-2xl border border-mist bg-white p-5 sm:grid-cols-4 dark:border-white/10 dark:bg-white/5">
                <div>
                    <p class="text-xs text-ink/50 dark:text-foam/50">Name</p>
                    <p class="text-sm font-medium">{{ staff.name }}</p>
                </div>
                <div>
                    <p class="text-xs text-ink/50 dark:text-foam/50">EMPID</p>
                    <p class="text-sm font-medium">{{ staff.employee_number }}</p>
                </div>
                <div>
                    <p class="text-xs text-ink/50 dark:text-foam/50">Date of birth</p>
                    <p class="text-sm font-medium">{{ formatDate(staff.date_of_birth) }}</p>
                </div>
                <div>
                    <p class="text-xs text-ink/50 dark:text-foam/50">Start date</p>
                    <p class="text-sm font-medium">{{ formatDate(staff.start_date) }}</p>
                </div>
            </section>

            <section class="grid gap-4 rounded-2xl border border-mist bg-white p-5 sm:grid-cols-3 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-first-name">First name</label>
                    <input id="record-first-name" v-model="form.first_name" :class="fieldClass" type="text" />
                    <p v-if="form.errors.first_name" class="text-sm text-danger">{{ form.errors.first_name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-middle-name">Middle name</label>
                    <input id="record-middle-name" v-model="form.middle_name" :class="fieldClass" type="text" />
                    <p v-if="form.errors.middle_name" class="text-sm text-danger">{{ form.errors.middle_name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-last-name">Last name</label>
                    <input id="record-last-name" v-model="form.last_name" :class="fieldClass" type="text" />
                    <p v-if="form.errors.last_name" class="text-sm text-danger">{{ form.errors.last_name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-gender">Gender</label>
                    <select id="record-gender" v-model="form.gender" :class="fieldClass">
                        <option value="">Select</option>
                        <option v-for="option in genders" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p v-if="form.errors.gender" class="text-sm text-danger">{{ form.errors.gender }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-civil-status">Civil status</label>
                    <select id="record-civil-status" v-model="form.civil_status" :class="fieldClass">
                        <option value="">Select</option>
                        <option v-for="option in civilStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p v-if="form.errors.civil_status" class="text-sm text-danger">{{ form.errors.civil_status }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-employment-status">Employment status</label>
                    <select id="record-employment-status" v-model="form.employment_status" :class="fieldClass">
                        <option value="">Select</option>
                        <option v-for="option in employmentStatuses" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.employment_status" class="text-sm text-danger">{{ form.errors.employment_status }}</p>
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <label class="text-sm font-medium" for="record-address">Address</label>
                    <input id="record-address" v-model="form.address" :class="fieldClass" type="text" />
                    <p v-if="form.errors.address" class="text-sm text-danger">{{ form.errors.address }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-job-title">Job title</label>
                    <input id="record-job-title" v-model="form.job_title" :class="fieldClass" type="text" />
                    <p v-if="form.errors.job_title" class="text-sm text-danger">{{ form.errors.job_title }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-mobile">Mobile</label>
                    <input id="record-mobile" v-model="form.mobile" :class="fieldClass" type="text" />
                    <p v-if="form.errors.mobile" class="text-sm text-danger">{{ form.errors.mobile }}</p>
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <label class="text-sm font-medium" for="record-email">Email</label>
                    <input id="record-email" v-model="form.email" :class="fieldClass" type="email" />
                    <p v-if="form.errors.email" class="text-sm text-danger">{{ form.errors.email }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-tin">TIN</label>
                    <input id="record-tin" v-model="form.tin" :class="fieldClass" type="text" />
                    <p v-if="form.errors.tin" class="text-sm text-danger">{{ form.errors.tin }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-sss">SSS</label>
                    <input id="record-sss" v-model="form.sss" :class="fieldClass" type="text" />
                    <p v-if="form.errors.sss" class="text-sm text-danger">{{ form.errors.sss }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-philhealth">PhilHealth</label>
                    <input id="record-philhealth" v-model="form.philhealth" :class="fieldClass" type="text" />
                    <p v-if="form.errors.philhealth" class="text-sm text-danger">{{ form.errors.philhealth }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-pagibig">Pag-IBIG</label>
                    <input id="record-pagibig" v-model="form.pagibig" :class="fieldClass" type="text" />
                    <p v-if="form.errors.pagibig" class="text-sm text-danger">{{ form.errors.pagibig }}</p>
                </div>
            </section>

            <section class="grid gap-4 rounded-2xl border border-mist bg-white p-5 sm:grid-cols-3 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-sm font-medium sm:col-span-3">Emergency contact</h2>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-emergency-name">Name</label>
                    <input id="record-emergency-name" v-model="form.emergency_contact_name" :class="fieldClass" type="text" />
                    <p v-if="form.errors.emergency_contact_name" class="text-sm text-danger">
                        {{ form.errors.emergency_contact_name }}
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-emergency-relationship">Relationship</label>
                    <input
                        id="record-emergency-relationship"
                        v-model="form.emergency_contact_relationship"
                        :class="fieldClass"
                        type="text"
                    />
                    <p v-if="form.errors.emergency_contact_relationship" class="text-sm text-danger">
                        {{ form.errors.emergency_contact_relationship }}
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-emergency-phone">Phone</label>
                    <input id="record-emergency-phone" v-model="form.emergency_contact_phone" :class="fieldClass" type="text" />
                    <p v-if="form.errors.emergency_contact_phone" class="text-sm text-danger">
                        {{ form.errors.emergency_contact_phone }}
                    </p>
                </div>
            </section>
        </div>

        <div v-show="tab === 'compensation'" class="flex flex-col gap-6">
            <section class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-sm font-medium">Pay basis</h2>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="option in payBases"
                        :key="option.value"
                        class="flex h-10 cursor-pointer items-center rounded-xl px-4 text-sm font-medium"
                        :class="form.pay_basis === option.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                    >
                        <input v-model="form.pay_basis" class="sr-only" type="radio" :value="option.value" />
                        {{ option.label }}
                    </label>
                </div>
                <p v-if="form.errors.pay_basis" class="text-sm text-danger">{{ form.errors.pay_basis }}</p>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-medium">Compensation</h2>
                        <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="addLine('compensation')">
                            Add line
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="name in compensationSuggestions"
                            :key="name"
                            class="h-8 rounded-lg border border-mist px-3 text-xs font-medium dark:border-white/15"
                            type="button"
                            @click="addSuggested('compensation', name)"
                        >
                            {{ name }}
                        </button>
                    </div>
                    <p v-if="form.compensation_lines.length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                        No compensation lines yet.
                    </p>
                    <div v-for="(line, index) in form.compensation_lines" :key="`compensation-${index}`" class="flex items-start gap-2">
                        <div class="grid flex-1 gap-2 sm:grid-cols-2">
                            <input v-model="line.name" :class="fieldClass" placeholder="Name" type="text" />
                            <input v-model="line.amount" :class="fieldClass" min="0" placeholder="Amount" step="0.01" type="number" />
                            <p v-if="form.errors[`compensation_lines.${index}.name`]" class="text-sm text-danger sm:col-span-2">
                                {{ form.errors[`compensation_lines.${index}.name`] }}
                            </p>
                            <p v-if="form.errors[`compensation_lines.${index}.amount`]" class="text-sm text-danger sm:col-span-2">
                                {{ form.errors[`compensation_lines.${index}.amount`] }}
                            </p>
                        </div>
                        <button class="h-11 px-2 text-sm font-medium text-danger" type="button" @click="removeLine('compensation', index)">
                            Remove
                        </button>
                    </div>
                    <div class="flex flex-col gap-2 border-t border-mist pt-3 dark:border-white/10">
                        <label class="text-sm font-medium" for="overnight-allowance">Overnight allowance (per night)</label>
                        <input
                            id="overnight-allowance"
                            v-model="form.overnight_allowance"
                            :class="fieldClass"
                            min="0"
                            placeholder="0.00"
                            step="0.01"
                            type="number"
                        />
                        <p v-if="form.errors.overnight_allowance" class="text-sm text-danger">{{ form.errors.overnight_allowance }}</p>
                        <p class="text-sm text-ink/60 dark:text-foam/60">
                            Paid for each overnight night entered on the payroll. It stays out of the daily wage used for late, overtime, and holidays.
                        </p>
                    </div>
                </section>

                <section class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-medium">Benefits</h2>
                        <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="addLine('benefit')">
                            Add line
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="name in benefitSuggestions"
                            :key="name"
                            class="h-8 rounded-lg border border-mist px-3 text-xs font-medium dark:border-white/15"
                            type="button"
                            @click="addSuggested('benefit', name)"
                        >
                            {{ name }}
                        </button>
                    </div>
                    <p v-if="form.benefit_lines.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No benefit lines yet.</p>
                    <div v-for="(line, index) in form.benefit_lines" :key="`benefit-${index}`" class="flex items-start gap-2">
                        <div class="grid flex-1 gap-2 sm:grid-cols-2">
                            <input v-model="line.name" :class="fieldClass" placeholder="Name" type="text" />
                            <input v-model="line.amount" :class="fieldClass" min="0" placeholder="Amount" step="0.01" type="number" />
                            <p v-if="form.errors[`benefit_lines.${index}.name`]" class="text-sm text-danger sm:col-span-2">
                                {{ form.errors[`benefit_lines.${index}.name`] }}
                            </p>
                            <p v-if="form.errors[`benefit_lines.${index}.amount`]" class="text-sm text-danger sm:col-span-2">
                                {{ form.errors[`benefit_lines.${index}.amount`] }}
                            </p>
                        </div>
                        <button class="h-11 px-2 text-sm font-medium text-danger" type="button" @click="removeLine('benefit', index)">
                            Remove
                        </button>
                    </div>
                </section>
            </div>

            <section class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-medium">Government mandated benefits</h2>
                    <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="addLine('government')">
                        Add line
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="name in governmentSuggestions"
                        :key="name"
                        class="h-8 rounded-lg border border-mist px-3 text-xs font-medium dark:border-white/15"
                        type="button"
                        @click="addSuggested('government', name)"
                    >
                        {{ name }}
                    </button>
                </div>
                <p v-if="form.government_lines.length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                    No government mandated benefits yet.
                </p>
                <div v-for="(line, index) in form.government_lines" :key="`government-${index}`" class="flex items-start gap-2">
                    <div class="grid flex-1 gap-2 sm:grid-cols-3">
                        <label class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-ink/50 dark:text-foam/50">Name</span>
                            <input v-model="line.name" :class="fieldClass" placeholder="SSS" type="text" />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-ink/50 dark:text-foam/50">Employee</span>
                            <input v-model="line.employee_contribution" :class="fieldClass" min="0" placeholder="0.00" step="0.01" type="number" />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-ink/50 dark:text-foam/50">Employer</span>
                            <input v-model="line.employer_contribution" :class="fieldClass" min="0" placeholder="0.00" step="0.01" type="number" />
                        </label>
                        <p v-if="form.errors[`government_lines.${index}.name`]" class="text-sm text-danger sm:col-span-3">
                            {{ form.errors[`government_lines.${index}.name`] }}
                        </p>
                        <p v-if="form.errors[`government_lines.${index}.employee_contribution`]" class="text-sm text-danger sm:col-span-3">
                            {{ form.errors[`government_lines.${index}.employee_contribution`] }}
                        </p>
                        <p v-if="form.errors[`government_lines.${index}.employer_contribution`]" class="text-sm text-danger sm:col-span-3">
                            {{ form.errors[`government_lines.${index}.employer_contribution`] }}
                        </p>
                    </div>
                    <button class="mt-5 h-11 px-2 text-sm font-medium text-danger" type="button" @click="removeLine('government', index)">
                        Remove
                    </button>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-sm font-medium">Commission by service</h2>
                <p v-if="form.commissions.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No active services yet.</p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="py-3 pr-4 font-medium">Service</th>
                                <th class="w-36 py-3 pr-4 font-medium">Assigned %</th>
                                <th class="w-36 py-3 font-medium">Assisted %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(commission, index) in form.commissions"
                                :key="commission.catalog_item_id"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="py-3 pr-4 font-medium">{{ commission.name }}</td>
                                <td class="py-3 pr-4">
                                    <input
                                        v-model="commission.assigned_rate"
                                        class="h-10 w-full rounded-xl border border-mist bg-white px-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                                        max="100"
                                        min="0"
                                        placeholder="0"
                                        step="0.01"
                                        type="number"
                                    />
                                    <p v-if="form.errors[`commissions.${index}.assigned_rate`]" class="mt-1 text-sm text-danger">
                                        {{ form.errors[`commissions.${index}.assigned_rate`] }}
                                    </p>
                                </td>
                                <td class="py-3">
                                    <input
                                        v-model="commission.assisted_rate"
                                        class="h-10 w-full rounded-xl border border-mist bg-white px-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                                        max="100"
                                        min="0"
                                        placeholder="0"
                                        step="0.01"
                                        type="number"
                                    />
                                    <p v-if="form.errors[`commissions.${index}.assisted_rate`]" class="mt-1 text-sm text-danger">
                                        {{ form.errors[`commissions.${index}.assisted_rate`] }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div v-if="tab !== 'payroll'" class="flex justify-end">
            <button
                class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Save record' }}
            </button>
        </div>
        </form>

        <div v-show="tab === 'payroll'" class="flex flex-col gap-6">
            <form class="grid gap-4 rounded-2xl border border-mist bg-white p-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-white/10 dark:bg-white/5" @submit.prevent="generatePayroll">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payroll-start">Start date</label>
                    <input id="payroll-start" v-model="payrollForm.period_start" :class="fieldClass" required type="date" />
                    <p v-if="payrollForm.errors.period_start" class="text-sm text-danger">{{ payrollForm.errors.period_start }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payroll-end">End date</label>
                    <input id="payroll-end" v-model="payrollForm.period_end" :class="fieldClass" required type="date" />
                    <p v-if="payrollForm.errors.period_end" class="text-sm text-danger">{{ payrollForm.errors.period_end }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payroll-days">Ordinary days worked</label>
                    <input
                        id="payroll-days"
                        v-model="payrollForm.days_worked"
                        :class="fieldClass"
                        max="31"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                    <p v-if="payrollForm.errors.days_worked" class="text-sm text-danger">{{ payrollForm.errors.days_worked }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payroll-nights">Overnight nights</label>
                    <input
                        id="payroll-nights"
                        v-model="payrollForm.overnight_nights"
                        :class="fieldClass"
                        max="31"
                        min="0"
                        required
                        step="1"
                        type="number"
                    />
                    <p v-if="payrollForm.errors.overnight_nights" class="text-sm text-danger">{{ payrollForm.errors.overnight_nights }}</p>
                    <p class="text-sm text-ink/50 dark:text-foam/50">{{ formatPeso(profile.overnight_allowance || 0) }} per night, from Compensation.</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payroll-late">Late (hours)</label>
                    <input
                        id="payroll-late"
                        v-model="payrollForm.late_hours"
                        :class="fieldClass"
                        max="744"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                    <p v-if="payrollForm.errors.late_hours" class="text-sm text-danger">{{ payrollForm.errors.late_hours }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payroll-overtime">Overtime (hours)</label>
                    <input
                        id="payroll-overtime"
                        v-model="payrollForm.overtime_hours"
                        :class="fieldClass"
                        max="744"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                    <p v-if="payrollForm.errors.overtime_hours" class="text-sm text-danger">{{ payrollForm.errors.overtime_hours }}</p>
                </div>
                <label class="flex items-center gap-3 text-sm sm:col-span-2 lg:col-span-3">
                    <input v-model="payrollForm.deduct_government" class="size-4 rounded border-mist" type="checkbox" />
                    Deduct government benefits (SSS, PhilHealth, Pag-IBIG)
                </label>
                <div v-if="periodHolidays.length > 0" class="flex flex-col gap-2 sm:col-span-2 lg:col-span-3">
                    <p class="text-sm font-medium">Holidays in this period</p>
                    <p v-if="payrollForm.errors.worked_holiday_ids" class="text-sm text-danger">{{ payrollForm.errors.worked_holiday_ids }}</p>
                    <label v-for="holiday in periodHolidays" :key="holiday.id" class="flex items-start gap-3 text-sm">
                        <input v-model="payrollForm.worked_holiday_ids" class="mt-1 size-4 rounded border-mist" type="checkbox" :value="holiday.id" />
                        <span>
                            {{ formatDate(holiday.date) }} · {{ holiday.name }} · {{ holiday.type_label }}
                            <span class="block text-ink/50 dark:text-foam/50">Check this if the employee worked. Unchecked uses the unworked rate: {{ holiday.unworked_label }}.</span>
                        </span>
                    </label>
                </div>
                <div class="flex flex-col gap-3 sm:col-span-2 lg:col-span-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-medium">One-time items</h2>
                        <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="addAdjustment">
                            Add item
                        </button>
                    </div>
                    <p class="text-sm text-ink/60 dark:text-foam/60">
                        Add a bonus, a cash advance, or another amount that belongs only to this payroll.
                    </p>
                    <div v-for="(item, index) in payrollForm.adjustments" :key="`adjustment-${index}`" class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_10rem_auto]">
                        <input v-model="item.name" :class="fieldClass" placeholder="Name" type="text" />
                        <select v-model="item.side" :class="fieldClass">
                            <option value="earning">Earning</option>
                            <option value="deduction">Deduction</option>
                        </select>
                        <input v-model="item.amount" :class="fieldClass" min="0.01" placeholder="Amount" step="0.01" type="number" />
                        <button class="h-11 px-2 text-sm font-medium text-danger" type="button" @click="removeAdjustment(index)">
                            Remove
                        </button>
                        <p v-if="payrollForm.errors[`adjustments.${index}.name`]" class="text-sm text-danger sm:col-span-4">
                            {{ payrollForm.errors[`adjustments.${index}.name`] }}
                        </p>
                        <p v-if="payrollForm.errors[`adjustments.${index}.side`]" class="text-sm text-danger sm:col-span-4">
                            {{ payrollForm.errors[`adjustments.${index}.side`] }}
                        </p>
                        <p v-if="payrollForm.errors[`adjustments.${index}.amount`]" class="text-sm text-danger sm:col-span-4">
                            {{ payrollForm.errors[`adjustments.${index}.amount`] }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-2 text-sm text-ink/60 sm:col-span-2 lg:col-span-3 dark:text-foam/60">
                    <p>Days worked are ordinary days only. Holidays in this period are paid separately.</p>
                    <p v-if="profile.pay_basis === 'month'">
                        Monthly amounts are divided by {{ payrollAssumptions.monthly_work_days }} working days, then multiplied by the ordinary days worked.
                    </p>
                    <p v-else>Daily amounts are multiplied by the ordinary days worked.</p>
                    <p>
                        A day is {{ payrollAssumptions.hours_per_day }} hours. Late is deducted at the hourly basic rate. Ordinary overtime is
                        {{ payrollAssumptions.overtime_multiplier }}× that rate. Regular holidays: 200% if worked, 100% if not. Special holidays: 130% if worked, no pay if not. Government contributions are deducted only when selected.
                    </p>
                </div>
                <div class="flex justify-end sm:col-span-2 lg:col-span-3">
                    <button
                        class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                        type="submit"
                        :disabled="payrollForm.processing"
                    >
                        {{ payrollForm.processing ? 'Generating…' : 'Generate payroll' }}
                    </button>
                </div>
            </form>

            <section
                v-if="payroll"
                class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <h2 class="text-sm font-medium">Generated payroll</h2>
                        <p class="text-sm text-ink/60 dark:text-foam/60">
                            {{ formatDate(payroll.period_start) }} to {{ formatDate(payroll.period_end) }}
                            · {{ payroll.days_worked }} ordinary {{ Number(payroll.days_worked) === 1 ? 'day' : 'days' }}
                            · {{ payroll.late_hours }} late {{ Number(payroll.late_hours) === 1 ? 'hour' : 'hours' }}
                            · {{ payroll.overtime_hours }} overtime {{ Number(payroll.overtime_hours) === 1 ? 'hour' : 'hours' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            class="inline-flex h-10 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                            :href="payrollDownloadHref()"
                        >
                            Download PDF
                        </a>
                        <button
                            class="inline-flex h-10 items-center rounded-xl bg-pine px-4 text-sm font-medium text-foam"
                            type="button"
                            @click="finalizePayroll"
                        >
                            Finalize payroll
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-6">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[32rem] text-left text-sm">
                            <thead class="text-ink/50 dark:text-foam/50">
                                <tr class="border-b border-mist dark:border-white/10">
                                    <th class="py-2 pr-4 font-medium">Earnings</th>
                                    <th class="w-28 py-2 pr-4 text-right font-medium">Amount</th>
                                    <th class="w-24 py-2 pr-4 text-right font-medium">Unit</th>
                                    <th class="w-28 py-2 text-right font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="(line, index) in payroll.earnings" :key="`earning-${index}`">
                                    <tr v-if="showGroup(payroll.earnings, index)">
                                        <td class="pt-3 font-medium" colspan="4">{{ line.group }}</td>
                                    </tr>
                                    <tr class="border-b border-mist last:border-b-0 dark:border-white/10">
                                        <td class="py-2 pr-4" :class="line.group ? 'pl-4' : ''">{{ line.label || line.name }}</td>
                                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatAmount(line.rate) }}</td>
                                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatAmount(line.unit) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ formatAmount(line.amount) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[32rem] text-left text-sm">
                            <thead class="text-ink/50 dark:text-foam/50">
                                <tr class="border-b border-mist dark:border-white/10">
                                    <th class="py-2 pr-4 font-medium">Deductions</th>
                                    <th class="w-28 py-2 pr-4 text-right font-medium">Amount</th>
                                    <th class="w-24 py-2 pr-4 text-right font-medium">Unit</th>
                                    <th class="w-28 py-2 text-right font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="(line, index) in payroll.deductions" :key="`deduction-${index}`">
                                    <tr v-if="showGroup(payroll.deductions, index)">
                                        <td class="pt-3 font-medium" colspan="4">{{ line.group }}</td>
                                    </tr>
                                    <tr class="border-b border-mist last:border-b-0 dark:border-white/10">
                                        <td class="py-2 pr-4" :class="line.group ? 'pl-4' : ''">{{ line.label || line.name }}</td>
                                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatAmount(line.rate) }}</td>
                                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatAmount(line.unit) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ formatAmount(line.amount) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col gap-2 border-t border-mist pt-4 text-sm dark:border-white/10">
                    <div class="flex justify-between gap-4">
                        <span>Gross pay</span>
                        <span>{{ formatPeso(payroll.gross) }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span>Deductions</span>
                        <span>{{ formatPeso(payroll.total_deductions) }}</span>
                    </div>
                    <div class="flex justify-between gap-4 text-base font-medium">
                        <span>Net pay</span>
                        <span>{{ formatPeso(payroll.net) }}</span>
                    </div>
                </div>

                <div v-if="payroll.employer_shares.length > 0" class="flex flex-col gap-2">
                    <h3 class="text-sm font-medium">Employer share</h3>
                    <p class="text-sm text-ink/60 dark:text-foam/60">Not deducted from pay.</p>
                    <div
                        v-for="line in payroll.employer_shares"
                        :key="`employer-${line.name}`"
                        class="flex justify-between gap-4 text-sm"
                    >
                        <span>{{ line.name }}</span>
                        <span>{{ formatPeso(line.amount) }}</span>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-sm font-medium">Finalized payrolls</h2>
                <p v-if="finalizedPayrolls.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No payroll has been finalized yet.</p>
                <div
                    v-for="saved in finalizedPayrolls"
                    :key="saved.id"
                    class="flex flex-wrap items-center justify-between gap-3 text-sm"
                >
                    <span>{{ formatDate(saved.period_start) }} to {{ formatDate(saved.period_end) }} · {{ formatPeso(saved.net) }}</span>
                    <a class="font-medium text-pine" :href="`/backoffice/staff/payrolls/${saved.id}/download`">Download PDF</a>
                </div>
            </section>
        </div>
    </div>
</template>
