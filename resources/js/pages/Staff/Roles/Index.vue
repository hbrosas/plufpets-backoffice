<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
    permissionGroups: {
        type: Array,
        required: true,
    },
});

const modalOpen = ref(false);
const editingRole = ref(null);

const form = useForm({
    name: '',
    permissions: [],
});

function openCreate() {
    editingRole.value = null;
    form.reset();
    form.permissions = [];
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(role) {
    editingRole.value = role;
    form.name = role.name;
    form.permissions = [...role.permissions];
    form.clearErrors();
    modalOpen.value = true;
}

function groupChecked(group) {
    return group.permissions.every((permission) => form.permissions.includes(permission.key));
}

function toggleGroup(group, checked) {
    const keys = group.permissions.map((permission) => permission.key);

    if (checked) {
        form.permissions = [...new Set([...form.permissions, ...keys])];
        return;
    }

    form.permissions = form.permissions.filter((key) => !keys.includes(key));
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
            form.permissions = [];
            editingRole.value = null;
        },
    };

    if (editingRole.value) {
        form.put(`/backoffice/staff/roles/${editingRole.value.id}`, options);
        return;
    }

    form.post('/backoffice/staff/roles', options);
}

function deleteRole(role) {
    if (!window.confirm(`Delete ${role.name}?`)) {
        return;
    }

    router.delete(`/backoffice/staff/roles/${role.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Roles" />

    <Teleport defer to="#admin-header-actions">
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="openCreate">
            Add role
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Staff</th>
                        <th class="px-4 py-3 font-medium">Permissions</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.roles.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="4">No roles yet.</td>
                    </tr>
                    <tr
                        v-for="role in props.roles"
                        :key="role.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ role.name }}</td>
                        <td class="px-4 py-3">{{ role.employee_count }}</td>
                        <td class="px-4 py-3">{{ role.permissions.length }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <button class="font-medium text-pine dark:text-sand" type="button" @click="openEdit(role)">
                                    Edit
                                </button>
                                <button class="font-medium text-danger" type="button" @click="deleteRole(role)">
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
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingRole ? 'Edit role' : 'Add role' }}</h2>
                <button class="text-sm font-medium" type="button" @click="modalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="role-name">Name</label>
                    <input
                        id="role-name"
                        v-model="form.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                </div>

                <fieldset class="flex flex-col gap-4">
                    <legend class="text-sm font-medium">Permissions</legend>
                    <p v-if="form.errors.permissions" class="text-sm text-danger">{{ form.errors.permissions }}</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <section
                            v-for="group in props.permissionGroups"
                            :key="group.group"
                            class="flex flex-col gap-3 rounded-2xl border border-mist p-4 dark:border-white/10"
                        >
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input
                                    class="h-4 w-4 accent-pine"
                                    type="checkbox"
                                    :checked="groupChecked(group)"
                                    @change="toggleGroup(group, $event.target.checked)"
                                />
                                {{ group.group }}
                            </label>
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.key"
                                class="flex items-center gap-2 pl-6 text-sm"
                            >
                                <input
                                    v-model="form.permissions"
                                    class="h-4 w-4 accent-pine"
                                    type="checkbox"
                                    :value="permission.key"
                                />
                                {{ permission.name }}
                            </label>
                        </section>
                    </div>
                </fieldset>

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
