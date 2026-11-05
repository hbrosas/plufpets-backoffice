<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
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
const type = ref(props.types[0]?.value ?? 'leave');
const form = useForm({
    type: props.types[0]?.value ?? 'leave',
    starts_on: '',
    ends_on: '',
    hours: '',
    nights: '1',
    reason: '',
});

function selectType(value) {
    type.value = value;
    form.type = value;
    form.clearErrors();
}

function submit() {
    form.type = type.value;
    form.post('/backoffice/me/payroll', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('starts_on', 'ends_on', 'hours', 'reason');
            form.nights = '1';
        },
    });
}

function withdraw(filing) {
    router.delete(`/backoffice/me/payroll/${filing.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="My Payroll" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div>
            <h2 class="text-lg font-medium">My Payroll</h2>
            <p class="text-sm text-ink/60 dark:text-foam/60">File leave, overtime, and overnight. A manager approves it before payroll.</p>
        </div>

        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>
        <p v-if="!account" class="rounded-xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">
            Sign in with your employee account to file payroll.
        </p>

        <form v-else class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5" @submit.prevent="submit">
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filing type">
                <button
                    v-for="item in types"
                    :id="`filing-tab-${item.value}`"
                    :key="item.value"
                    class="h-10 rounded-xl px-4 text-sm font-medium"
                    :class="type === item.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                    type="button"
                    role="tab"
                    :aria-selected="type === item.value"
                    @click="selectType(item.value)"
                >
                    {{ item.label }}
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm" for="filing-start">{{ type === 'leave' ? 'From' : 'Date' }}</label>
                    <input id="filing-start" v-model="form.starts_on" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" required type="date" />
                    <p v-if="form.errors.starts_on" class="mt-1 text-sm text-danger">{{ form.errors.starts_on }}</p>
                </div>
                <div v-if="type === 'leave'">
                    <label class="mb-1 block text-sm" for="filing-end">To</label>
                    <input id="filing-end" v-model="form.ends_on" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" required type="date" />
                    <p v-if="form.errors.ends_on" class="mt-1 text-sm text-danger">{{ form.errors.ends_on }}</p>
                </div>
                <div v-else-if="type === 'overtime'">
                    <label class="mb-1 block text-sm" for="filing-hours">Hours</label>
                    <input id="filing-hours" v-model="form.hours" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" min="0.01" max="24" required step="0.01" type="number" />
                    <p v-if="form.errors.hours" class="mt-1 text-sm text-danger">{{ form.errors.hours }}</p>
                </div>
                <div v-else>
                    <label class="mb-1 block text-sm" for="filing-nights">Nights</label>
                    <input id="filing-nights" v-model="form.nights" class="h-11 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5" max="31" min="1" required step="1" type="number" />
                    <p v-if="form.errors.nights" class="mt-1 text-sm text-danger">{{ form.errors.nights }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm" for="filing-reason">Reason</label>
                <textarea id="filing-reason" v-model="form.reason" class="min-h-24 w-full rounded-xl border border-mist bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" maxlength="1000" />
                <p v-if="form.errors.reason" class="mt-1 text-sm text-danger">{{ form.errors.reason }}</p>
            </div>

            <button class="h-11 w-fit rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-60" type="submit" :disabled="form.processing">
                File {{ types.find((item) => item.value === type)?.label }}
            </button>
        </form>

        <section class="flex flex-col gap-3">
            <h3 class="text-base font-medium">Pay slips</h3>
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

        <section class="flex flex-col gap-3">
            <h3 class="text-base font-medium">Filings</h3>
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
</template>
