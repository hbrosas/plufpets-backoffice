<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    account: {
        type: Boolean,
        required: true,
    },
    types: {
        type: Array,
        required: true,
    },
    filings: {
        type: Array,
        required: true,
    },
    payrolls: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const tab = ref('payslips');
const filingType = ref(null);
const form = useForm({
    type: 'leave',
    starts_on: '',
    ends_on: '',
    hours: '',
    nights: '1',
    amount: '',
    reason: '',
});

const tabs = [
    { id: 'payslips', label: 'Pay slips' },
    { id: 'filings', label: 'Filings' },
];

const fileActions = [
    { value: 'leave', label: 'File Leave' },
    { value: 'overnight', label: 'File Overnight' },
    { value: 'cash_advance', label: 'Request Cash Advance' },
];

const filingTitle = computed(() => fileActions.find((action) => action.value === filingType.value)?.label ?? 'File');

function openFiling(value) {
    filingType.value = value;
    form.type = value;
    form.reset('starts_on', 'ends_on', 'hours', 'amount', 'reason');
    form.nights = '1';
    form.clearErrors();
}

function closeFiling() {
    if (form.processing) {
        return;
    }

    filingType.value = null;
}

function submit() {
    form.type = filingType.value;
    form.post('/backoffice/me/payroll', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('starts_on', 'ends_on', 'hours', 'amount', 'reason');
            form.nights = '1';
            filingType.value = null;
            tab.value = 'filings';
        },
    });
}

function withdraw(filing) {
    router.delete(`/backoffice/me/payroll/${filing.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="My Payroll" />

    <Teleport defer to="#admin-header-tabs">
        <div class="flex w-max gap-2" role="tablist" aria-label="My Payroll">
            <button
                v-for="item in tabs"
                :id="`payroll-tab-${item.id}`"
                :key="item.id"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="tab === item.id ? 'bg-pine text-foam' : 'border border-mist bg-white'"
                type="button"
                role="tab"
                :aria-selected="tab === item.id"
                :aria-controls="`payroll-panel-${item.id}`"
                @click="tab = item.id"
            >
                {{ item.label }}
            </button>
        </div>
    </Teleport>

    <Teleport v-if="account" defer to="#admin-header-actions">
        <button
            v-for="action in fileActions"
            :key="action.value"
            class="inline-flex h-10 shrink-0 items-center rounded-xl bg-pine px-4 text-sm font-medium whitespace-nowrap text-foam"
            type="button"
            @click="openFiling(action.value)"
        >
            {{ action.label }}
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>
        <p v-if="!account" class="rounded-xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">
            Sign in with your employee account to file payroll.
        </p>

        <section v-show="tab === 'payslips'" id="payroll-panel-payslips" role="tabpanel" aria-labelledby="payroll-tab-payslips">
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Period</th>
                            <th class="px-4 py-3 font-medium">Net</th>
                            <th class="px-4 py-3 font-medium"><span class="sr-only">Download</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="payrolls.length === 0">
                            <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="3">No finalized payroll yet.</td>
                        </tr>
                        <tr v-for="payroll in payrolls" :key="payroll.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="px-4 py-3">{{ payroll.period }}</td>
                            <td class="px-4 py-3 font-medium">{{ payroll.net }}</td>
                            <td class="px-4 py-3 text-right">
                                <a class="font-medium text-pine dark:text-sand" :href="`/backoffice/me/payrolls/${payroll.id}/download`">Download</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-show="tab === 'filings'" id="payroll-panel-filings" role="tabpanel" aria-labelledby="payroll-tab-filings">
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">When</th>
                            <th class="px-4 py-3 font-medium">Reason</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filings.length === 0">
                            <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="5">No filings yet.</td>
                        </tr>
                        <tr v-for="filing in filings" :key="filing.id" class="border-b border-mist align-top last:border-b-0 dark:border-white/10">
                            <td class="px-4 py-3 font-medium">{{ filing.type_label }}</td>
                            <td class="px-4 py-3">{{ filing.detail }}</td>
                            <td class="px-4 py-3">
                                <p>{{ filing.reason || '—' }}</p>
                                <p v-if="filing.review_note" class="text-xs text-ink/50 dark:text-foam/50">{{ filing.reviewed_by_name }}: {{ filing.review_note }}</p>
                            </td>
                            <td class="px-4 py-3">{{ filing.status_label }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="filing.status === 'pending'"
                                    class="font-medium text-pine dark:text-sand"
                                    type="button"
                                    @click="withdraw(filing)"
                                >
                                    Withdraw
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div v-if="filingType" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4" @click.self="closeFiling">
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink" role="dialog" aria-modal="true" aria-labelledby="payroll-filing-title">
            <div class="flex items-start justify-between gap-4">
                <h2 id="payroll-filing-title" class="text-lg font-medium">{{ filingTitle }}</h2>
                <button class="text-sm font-medium" type="button" @click="closeFiling">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <template v-if="filingType === 'cash_advance'">
                    <div>
                        <label class="mb-1 block text-sm" for="filing-amount">Amount</label>
                        <input id="filing-amount" v-model="form.amount" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" min="0.01" required step="0.01" type="number" />
                        <p v-if="form.errors.amount" class="mt-1 text-sm text-danger">{{ form.errors.amount }}</p>
                    </div>
                </template>
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm" for="filing-start">{{ filingType === 'leave' ? 'From' : 'Date' }}</label>
                        <input id="filing-start" v-model="form.starts_on" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" required type="date" />
                        <p v-if="form.errors.starts_on" class="mt-1 text-sm text-danger">{{ form.errors.starts_on }}</p>
                    </div>
                    <div v-if="filingType === 'leave'">
                        <label class="mb-1 block text-sm" for="filing-end">To</label>
                        <input id="filing-end" v-model="form.ends_on" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" required type="date" />
                        <p v-if="form.errors.ends_on" class="mt-1 text-sm text-danger">{{ form.errors.ends_on }}</p>
                    </div>
                    <div v-else>
                        <label class="mb-1 block text-sm" for="filing-nights">Nights</label>
                        <input id="filing-nights" v-model="form.nights" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" max="31" min="1" required step="1" type="number" />
                        <p v-if="form.errors.nights" class="mt-1 text-sm text-danger">{{ form.errors.nights }}</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm" for="filing-reason">Reason</label>
                    <textarea id="filing-reason" v-model="form.reason" class="min-h-24 w-full rounded-xl border border-mist bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" maxlength="1000" :required="filingType === 'cash_advance'" />
                    <p v-if="form.errors.reason" class="mt-1 text-sm text-danger">{{ form.errors.reason }}</p>
                </div>
                <button class="h-11 w-fit rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-60" type="submit" :disabled="form.processing">
                    {{ filingTitle }}
                </button>
            </form>
        </div>
    </div>
</template>
