<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    account: { type: Boolean, required: true },
    staff: { type: Object, default: null },
    profile: { type: Object, default: null },
    genders: { type: Array, required: true },
    civilStatuses: { type: Array, required: true },
    employmentStatuses: { type: Array, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const form = useForm({
    first_name: props.profile?.first_name ?? '',
    middle_name: props.profile?.middle_name ?? '',
    last_name: props.profile?.last_name ?? '',
    gender: props.profile?.gender ?? '',
    civil_status: props.profile?.civil_status ?? '',
    address: props.profile?.address ?? '',
    mobile: props.profile?.mobile ?? '',
    email: props.profile?.email ?? '',
    tin: props.profile?.tin ?? '',
    sss: props.profile?.sss ?? '',
    philhealth: props.profile?.philhealth ?? '',
    pagibig: props.profile?.pagibig ?? '',
    job_title: props.profile?.job_title ?? '',
    employment_status: props.profile?.employment_status ?? '',
    emergency_contact_name: props.profile?.emergency_contact_name ?? '',
    emergency_contact_relationship: props.profile?.emergency_contact_relationship ?? '',
    emergency_contact_phone: props.profile?.emergency_contact_phone ?? '',
});

const fieldClass = 'h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5';
const lockedFieldClass = `${fieldClass} cursor-not-allowed bg-mist/40 text-ink/70 dark:bg-white/5 dark:text-foam/70`;

function formatDate(value) {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function submit() {
    form.put('/backoffice/me/employee-201', { preserveScroll: true });
}
</script>

<template>
    <Head title="My Employee 201" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>
        <p v-if="!account" class="rounded-xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">
            Sign in with your employee account to update your 201 file.
        </p>

        <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
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
                    <select id="record-employment-status" v-model="form.employment_status" :class="lockedFieldClass" disabled>
                        <option value="">Select</option>
                        <option v-for="option in employmentStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
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
                    <input id="record-job-title" v-model="form.job_title" :class="lockedFieldClass" type="text" disabled />
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
                    <p v-if="form.errors.emergency_contact_name" class="text-sm text-danger">{{ form.errors.emergency_contact_name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-emergency-relationship">Relationship</label>
                    <input id="record-emergency-relationship" v-model="form.emergency_contact_relationship" :class="fieldClass" type="text" />
                    <p v-if="form.errors.emergency_contact_relationship" class="text-sm text-danger">{{ form.errors.emergency_contact_relationship }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="record-emergency-phone">Phone</label>
                    <input id="record-emergency-phone" v-model="form.emergency_contact_phone" :class="fieldClass" type="text" />
                    <p v-if="form.errors.emergency_contact_phone" class="text-sm text-danger">{{ form.errors.emergency_contact_phone }}</p>
                </div>
            </section>

            <div class="flex justify-end">
                <button class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save record' }}
                </button>
            </div>
        </form>
    </div>
</template>
