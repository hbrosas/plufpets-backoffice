<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        required: true,
    },
});

function assignmentRows(items) {
    return items.map((item) => ({
        id: item.id,
        product: item.product,
        variant: item.variant,
        purchase_order: item.purchase_order,
        expected: item.remaining,
        branch_quantities: item.branch_quantities,
        checked: false,
        quantity: '',
    }));
}

const form = useForm({
    branch_id: '',
    items: assignmentRows(props.items),
});

const allChecked = computed(() => form.items.length > 0 && form.items.every((row) => row.checked));
const hasSelection = computed(() => form.branch_id !== '' && form.items.some((row) => row.checked && Number(row.quantity) > 0));

function setAssigned(row, checked) {
    row.checked = checked;
    row.quantity = checked ? row.expected : '';
}

function syncChecked(row) {
    row.checked = row.quantity !== '' && Number(row.quantity) > 0;
}

function toggleAll(event) {
    form.items.forEach((row) => setAssigned(row, event.target.checked));
}

function rowError(row) {
    const index = form.items.filter((item) => item.checked).findIndex((item) => item.id === row.id);

    if (index === -1) {
        return null;
    }

    return form.errors[`items.${index}.quantity`] || form.errors[`items.${index}.purchase_order_item_id`] || null;
}

function submit() {
    form.transform((data) => ({
        branch_id: data.branch_id,
        items: data.items
            .filter((row) => row.checked && Number(row.quantity) > 0)
            .map((row) => ({
                purchase_order_item_id: row.id,
                quantity: Number(row.quantity),
            })),
    })).post('/backoffice/inventory/stock-assignments', {
        preserveScroll: true,
    });
}

watch(
    () => props.items.map((item) => `${item.id}:${item.remaining}:${item.branch_quantities.join(',')}`).join('|'),
    () => {
        form.defaults({
            branch_id: '',
            items: assignmentRows(props.items),
        });
        form.reset();
        form.clearErrors();
    },
);
</script>

<template>
    <Head title="Stock Assignment" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p v-if="items.length === 0" class="text-sm text-ink/70 dark:text-foam/70">
            Nothing to assign. Receive a delivery first, then come back to send stock to a branch.
        </p>

        <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex max-w-sm flex-col gap-2">
                <label class="text-sm font-medium" for="assignment-branch">Assign To Branch</label>
                <select
                    id="assignment-branch"
                    v-model="form.branch_id"
                    class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    required
                >
                    <option value="">Choose branch</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
                <p v-if="form.errors.branch_id" class="text-sm text-danger">{{ form.errors.branch_id }}</p>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="w-12 px-4 py-3 font-medium">
                                <input
                                    class="h-4 w-4 accent-pine"
                                    type="checkbox"
                                    :checked="allChecked"
                                    aria-label="Select all products"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 font-medium">Variant</th>
                            <th class="px-4 py-3 font-medium">PO</th>
                            <th class="px-4 py-3 font-medium">Left</th>
                            <th v-for="branch in branches" :key="branch.id" class="px-4 py-3 font-medium">
                                {{ branch.name }}
                            </th>
                            <th class="px-4 py-3 font-medium">Assign</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in form.items"
                            :key="row.id"
                            class="border-b border-mist last:border-b-0 dark:border-white/10"
                        >
                            <td class="px-4 py-3">
                                <input
                                    class="h-4 w-4 accent-pine"
                                    type="checkbox"
                                    :checked="row.checked"
                                    :aria-label="`Assign ${row.product}`"
                                    @change="setAssigned(row, $event.target.checked)"
                                />
                            </td>
                            <td class="px-4 py-3 font-medium">{{ row.product }}</td>
                            <td class="px-4 py-3">{{ row.variant || '—' }}</td>
                            <td class="px-4 py-3">{{ row.purchase_order }}</td>
                            <td class="px-4 py-3">{{ row.expected }}</td>
                            <td v-for="(quantity, index) in row.branch_quantities" :key="`${row.id}-${index}`" class="px-4 py-3">
                                {{ quantity }}
                            </td>
                            <td class="px-4 py-3">
                                <input
                                    v-model="row.quantity"
                                    class="h-10 w-24 rounded-xl border border-mist bg-white px-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                                    :max="row.expected"
                                    min="1"
                                    type="number"
                                    :aria-label="`Assign quantity for ${row.product}`"
                                    @input="syncChecked(row)"
                                />
                                <p v-if="rowError(row)" class="mt-1 text-sm text-danger">{{ rowError(row) }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="form.errors.items" class="text-sm text-danger">{{ form.errors.items }}</p>
            <button
                class="h-11 self-start rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                type="submit"
                :disabled="form.processing || !hasSelection"
            >
                {{ form.processing ? 'Assigning…' : 'Assign stock' }}
            </button>
        </form>
    </div>
</template>
