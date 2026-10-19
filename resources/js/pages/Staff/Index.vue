<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    employees: {
        type: Array,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const modalOpen = ref(false);
const editingEmployee = ref(null);

const form = useForm({
    name: '',
    date_of_birth: '',
    start_date: '',
    pin: '',
    role_id: '',
    is_active: true,
});

function openCreate() {
    editingEmployee.value = null;
    form.reset();
    form.is_active = true;
    form.role_id = props.roles[0]?.id ?? '';
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(employee) {
    editingEmployee.value = employee;
    form.name = employee.name;
    form.date_of_birth = employee.date_of_birth;
    form.start_date = employee.start_date;
    form.pin = '';
    form.role_id = employee.role_id;
    form.is_active = employee.is_active;
    form.clearErrors();
    modalOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
            editingEmployee.value = null;
        },
    };

    if (editingEmployee.value) {
        form.put(`/backoffice/staff/${editingEmployee.value.id}`, options);
        return;
    }

    form.post('/backoffice/staff', options);
}

function deleteEmployee(employee) {
    if (!window.confirm(`Delete ${employee.name}?`)) {
        return;
    }

    router.delete(`/backoffice/staff/${employee.id}`, {
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
</script>

<template>
    <Head title="Staff" />

    <Teleport defer to="#admin-header-actions">
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="openCreate">
            Add staff
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">EMPID</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Start date</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.employees.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="6">No staff accounts yet.</td>
                    </tr>
                    <tr
                        v-for="employee in props.employees"
                        :key="employee.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ employee.name }}</td>
                        <td class="px-4 py-3">{{ employee.employee_number }}</td>
                        <td class="px-4 py-3">{{ employee.role_name }}</td>
                        <td class="px-4 py-3">{{ formatDate(employee.start_date) }}</td>
                        <td class="px-4 py-3">{{ employee.is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <button class="font-medium text-pine dark:text-sand" type="button" @click="openEdit(employee)">
                                    Edit
                                </button>
                                <button class="font-medium text-danger" type="button" @click="deleteEmployee(employee)">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">{{ editingEmployee ? 'Edit staff' : 'Add staff' }}</h2>
                    <p v-if="editingEmployee" class="text-sm text-ink/60 dark:text-foam/60">
                        EMPID {{ editingEmployee.employee_number }}
                    </p>
                </div>
                <button class="text-sm font-medium" type="button" @click="modalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="staff-name">Name</label>
                    <input
                        id="staff-name"
                        v-model="form.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="staff-dob">Date of birth</label>
                        <input
                            id="staff-dob"
                            v-model="form.date_of_birth"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            required
                            type="date"
                        />
                        <p v-if="form.errors.date_of_birth" class="text-sm text-danger">{{ form.errors.date_of_birth }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="staff-start">Start date</label>
                        <input
                            id="staff-start"
                            v-model="form.start_date"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            required
                            type="date"
                        />
                        <p v-if="form.errors.start_date" class="text-sm text-danger">{{ form.errors.start_date }}</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="staff-role">Role</label>
                        <select
                            id="staff-role"
                            v-model="form.role_id"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            required
                        >
                            <option v-for="role in props.roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                        <p v-if="form.errors.role_id" class="text-sm text-danger">{{ form.errors.role_id }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="staff-pin">PIN</label>
                        <input
                            id="staff-pin"
                            v-model="form.pin"
                            autocomplete="new-password"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            inputmode="numeric"
                            :placeholder="editingEmployee ? 'Leave blank to keep the current PIN' : '4 to 8 digits'"
                            :required="!editingEmployee"
                            type="password"
                        />
                        <p v-if="form.errors.pin" class="text-sm text-danger">{{ form.errors.pin }}</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="form.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
                <p v-if="form.errors.is_active" class="text-sm text-danger">{{ form.errors.is_active }}</p>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>
</template>
