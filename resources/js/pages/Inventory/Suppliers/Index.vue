<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    suppliers: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const modalOpen = ref(false);
const editingSupplier = ref(null);

const form = useForm({
    name: '',
    code: '',
    contact_name: '',
    email: '',
    phone: '',
    address: '',
    notes: '',
    is_active: true,
});

function openCreate() {
    editingSupplier.value = null;
    form.reset();
    form.is_active = true;
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(supplier) {
    editingSupplier.value = supplier;
    form.name = supplier.name;
    form.code = supplier.code ?? '';
    form.contact_name = supplier.contact_name ?? '';
    form.email = supplier.email ?? '';
    form.phone = supplier.phone ?? '';
    form.address = supplier.address ?? '';
    form.notes = supplier.notes ?? '';
    form.is_active = supplier.is_active;
    form.clearErrors();
    modalOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
            editingSupplier.value = null;
        },
    };

    if (editingSupplier.value) {
        form.put(`/backoffice/inventory/suppliers/${editingSupplier.value.id}`, options);
        return;
    }

    form.post('/backoffice/inventory/suppliers', options);
}

function deleteSupplier(supplier) {
    if (!window.confirm(`Delete ${supplier.name}?`)) {
        return;
    }

    router.delete(`/backoffice/inventory/suppliers/${supplier.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Suppliers" />

    <Teleport defer to="#admin-header-actions">
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="openCreate">
            Add supplier
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 font-medium">Contact</th>
                        <th class="px-4 py-3 font-medium">POs</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.suppliers.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="6">No suppliers yet.</td>
                    </tr>
                    <tr
                        v-for="supplier in props.suppliers"
                        :key="supplier.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ supplier.name }}</td>
                        <td class="px-4 py-3">{{ supplier.code || '—' }}</td>
                        <td class="px-4 py-3">{{ supplier.contact_name || supplier.email || '—' }}</td>
                        <td class="px-4 py-3">{{ supplier.purchase_order_count }}</td>
                        <td class="px-4 py-3">{{ supplier.is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <button class="font-medium text-pine dark:text-sand" type="button" @click="openEdit(supplier)">
                                    Edit
                                </button>
                                <button class="font-medium text-danger" type="button" @click="deleteSupplier(supplier)">
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
        <div class="flex w-full max-w-lg flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingSupplier ? 'Edit supplier' : 'Add supplier' }}</h2>
                <button class="text-sm font-medium" type="button" @click="modalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="supplier-name">Name</label>
                    <input
                        id="supplier-name"
                        v-model="form.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="supplier-code">Code</label>
                        <input
                            id="supplier-code"
                            v-model="form.code"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            type="text"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="supplier-phone">Phone</label>
                        <input
                            id="supplier-phone"
                            v-model="form.phone"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            type="text"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="supplier-contact">Contact name</label>
                        <input
                            id="supplier-contact"
                            v-model="form.contact_name"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            type="text"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="supplier-email">Email</label>
                        <input
                            id="supplier-email"
                            v-model="form.email"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            type="email"
                        />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="supplier-address">Address</label>
                    <input
                        id="supplier-address"
                        v-model="form.address"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        type="text"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="form.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
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
