<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    year: { type: Number, required: true },
    holidays: { type: Array, required: true },
    types: { type: Array, required: true },
});

const form = useForm({
    date: '',
    name: '',
    type: 'regular',
});

const importForm = useForm({
    year: props.year,
});

function submit() {
    form.post('/backoffice/staff/holidays', {
        preserveScroll: true,
        onSuccess: () => form.reset('date', 'name'),
    });
}

function importHolidays() {
    importForm.post('/backoffice/staff/holidays/import', {
        preserveScroll: true,
    });
}

function showYear() {
    router.get('/backoffice/staff/holidays', { year: importForm.year }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function deleteHoliday(holiday) {
    if (!window.confirm(`Delete ${holiday.name}?`)) {
        return;
    }

    router.delete(`/backoffice/staff/holidays/${holiday.id}`, {
        preserveScroll: true,
    });
}

function formatDate(value) {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

const fieldClass = 'h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5';
</script>

<template>
    <Head title="Holidays" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="grid gap-4 rounded-2xl border border-mist bg-white p-5 lg:grid-cols-2 dark:border-white/10 dark:bg-white/5">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <h2 class="text-sm font-medium">Add a holiday</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="holiday-date">Date</label>
                        <input id="holiday-date" v-model="form.date" :class="fieldClass" required type="date" />
                        <p v-if="form.errors.date" class="text-sm text-danger">{{ form.errors.date }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="holiday-type">Type</label>
                        <select id="holiday-type" v-model="form.type" :class="fieldClass" required>
                            <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <p v-if="form.errors.type" class="text-sm text-danger">{{ form.errors.type }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="holiday-name">Name</label>
                    <input id="holiday-name" v-model="form.name" :class="fieldClass" required type="text" />
                    <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                </div>
                <p class="text-sm text-ink/60 dark:text-foam/60">
                    Regular holidays are paid at 200% if worked and 100% if not worked. Special non-working days are paid at 130% if worked and are unpaid if not worked.
                </p>
                <div class="flex justify-end">
                    <button class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70" type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Add holiday' }}
                    </button>
                </div>
            </form>

            <form class="flex flex-col gap-4" @submit.prevent="importHolidays">
                <h2 class="text-sm font-medium">Import Philippine holidays</h2>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="holiday-year">Year</label>
                    <input id="holiday-year" v-model="importForm.year" :class="fieldClass" max="2100" min="2000" required type="number" />
                    <p v-if="importForm.errors.year" class="text-sm text-danger">{{ importForm.errors.year }}</p>
                </div>
                <p class="text-sm text-ink/60 dark:text-foam/60">
                    Imports the regular holidays and the special non-working days fixed for that year. Dates already on the calendar are left as they are. Add Eid’l Fitr and Eid’l Adha when Malacañang proclaims them.
                </p>
                <div class="flex flex-wrap justify-end gap-3">
                    <button class="h-11 rounded-xl border border-mist px-5 text-sm font-medium dark:border-white/15" type="button" @click="showYear">
                        Show year
                    </button>
                    <button class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70" type="submit" :disabled="importForm.processing">
                        {{ importForm.processing ? 'Importing…' : 'Import holidays' }}
                    </button>
                </div>
            </form>
        </section>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Holiday</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Pay</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="holidays.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="5">No holidays for {{ year }} yet.</td>
                    </tr>
                    <tr v-for="holiday in holidays" :key="holiday.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                        <td class="px-4 py-3">{{ formatDate(holiday.date) }}</td>
                        <td class="px-4 py-3 font-medium">{{ holiday.name }}</td>
                        <td class="px-4 py-3">{{ holiday.type_label }}</td>
                        <td class="px-4 py-3">{{ holiday.worked_label }} · {{ holiday.unworked_label }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end">
                                <button class="font-medium text-danger" type="button" @click="deleteHoliday(holiday)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
