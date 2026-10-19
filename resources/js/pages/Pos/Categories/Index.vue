<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    categories: { type: Array, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const modalOpen = ref(false);
const editingCategory = ref(null);
const form = useForm({
    name: '',
    is_active: true,
});

function openCreate() {
    editingCategory.value = null;
    form.reset();
    form.is_active = true;
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(category) {
    editingCategory.value = category;
    form.name = category.name;
    form.is_active = category.is_active;
    form.clearErrors();
    modalOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
            editingCategory.value = null;
        },
    };

    if (editingCategory.value) {
        form.put(`/backoffice/pos/categories/${editingCategory.value.id}`, options);
        return;
    }

    form.post('/backoffice/pos/categories', options);
}

function remove(category) {
    if (!window.confirm(`Delete ${category.name}? Shop categories will be unlinked from this POS category.`)) {
        return;
    }

    router.delete(`/backoffice/pos/categories/${category.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="POS Categories" />

    <Teleport defer to="#admin-header-actions">
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="openCreate">
            Add category
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">POS Categories</h2>
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Shop categories</th>
                            <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="categories.length === 0">
                            <td class="px-4 py-8 text-ink/60" colspan="3">No POS categories yet.</td>
                        </tr>
                        <tr v-for="category in categories" :key="category.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ category.name }}</p>
                                <p class="text-xs text-ink/50">{{ category.is_active ? 'Active' : 'Inactive' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p v-if="category.shop_categories.length === 0" class="text-ink/50">None</p>
                                <p v-else>{{ category.shop_categories.join(', ') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <button class="font-medium text-pine dark:text-sand" type="button" @click="openEdit(category)">Edit</button>
                                    <button class="font-medium text-danger" type="button" @click="remove(category)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingCategory ? 'Edit POS category' : 'Add POS category' }}</h2>
                <button class="text-sm font-medium" type="button" @click="modalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <input
                    v-model="form.name"
                    class="h-11 rounded-xl border border-mist bg-white px-4 text-sm"
                    required
                    type="text"
                    placeholder="Name"
                />
                <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="form.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
                <button class="h-11 rounded-xl bg-pine text-sm font-medium text-foam" type="submit" :disabled="form.processing">
                    Save
                </button>
            </form>
        </div>
    </div>
</template>
