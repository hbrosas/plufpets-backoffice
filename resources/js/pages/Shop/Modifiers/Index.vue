<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import MultiValueInput from '../../../components/MultiValueInput.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    modifiers: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const modalOpen = ref(false);
const editingModifier = ref(null);
const optionsInput = ref(null);
const form = useForm({
    name: '',
    options: [],
    is_active: true,
});

function openCreate() {
    editingModifier.value = null;
    form.reset();
    form.options = [];
    form.is_active = true;
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(modifier) {
    editingModifier.value = modifier;
    form.name = modifier.name;
    form.options = [...(modifier.options ?? [])];
    form.is_active = modifier.is_active;
    form.clearErrors();
    modalOpen.value = true;
}

async function submit() {
    optionsInput.value?.commitDraft();
    await nextTick();

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
            editingModifier.value = null;
        },
    };

    if (editingModifier.value) {
        form.put(`/backoffice/shop/modifiers/${editingModifier.value.id}`, options);
        return;
    }

    form.post('/backoffice/shop/modifiers', options);
}

function deleteModifier(modifier) {
    if (!window.confirm(`Delete ${modifier.name}? Products using it will no longer offer this add-on.`)) {
        return;
    }

    router.delete(`/backoffice/shop/modifiers/${modifier.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Modifiers" />

    <Teleport defer to="#admin-header-actions">
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="openCreate">
            Add modifier
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Modifier name</th>
                        <th class="px-4 py-3 font-medium">Options</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Products</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.modifiers.length === 0">
                        <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="5">No modifiers yet.</td>
                    </tr>
                    <tr
                        v-for="modifier in props.modifiers"
                        :key="modifier.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ modifier.name }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="option in modifier.options"
                                    :key="`${modifier.id}-${option}`"
                                    class="rounded-lg bg-mist px-2 py-1 text-xs dark:bg-white/10"
                                >
                                    {{ option }}
                                </span>
                                <span v-if="modifier.options.length === 0" class="text-ink/50 dark:text-foam/50">—</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ modifier.is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3">{{ modifier.item_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <button class="font-medium text-pine dark:text-sand" type="button" @click="openEdit(modifier)">
                                    Edit
                                </button>
                                <button class="font-medium text-danger" type="button" @click="deleteModifier(modifier)">
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
                <h2 class="text-lg font-medium">{{ editingModifier ? 'Edit modifier' : 'Add modifier' }}</h2>
                <button class="text-sm font-medium" type="button" @click="modalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="modifier-name">Modifier name</label>
                    <input
                        id="modifier-name"
                        v-model="form.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="modifier-options">Options</label>
                    <MultiValueInput id="modifier-options" ref="optionsInput" v-model="form.options" />
                    <p class="text-sm text-ink/60 dark:text-foam/60">
                        Press Enter or comma after each option. You can also paste a comma-separated list.
                    </p>
                    <p v-if="form.errors.options" class="text-sm text-danger">{{ form.errors.options }}</p>
                    <p v-if="form.errors['options.0']" class="text-sm text-danger">{{ form.errors['options.0'] }}</p>
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
