<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const selectedId = ref(props.items[0]?.id ?? '');

const form = useForm({
    purchase_order_item_id: props.items[0]?.id ?? '',
    branch_id: '',
    quantity: props.items[0]?.remaining ?? 1,
    selling_price: props.items[0]?.suggested_price ?? '',
});

function selectItem(item) {
    selectedId.value = item.id;
    form.purchase_order_item_id = item.id;
    form.quantity = item.remaining;
    form.selling_price = item.suggested_price;
    form.clearErrors();
}

function submit() {
    form.post('/backoffice/inventory/stock-assignments', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Stock Assignment" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p v-if="items.length === 0" class="text-sm text-ink/70 dark:text-foam/70">
            Nothing to assign. Receive a delivery first, then come back to send stock to a branch and set the price.
        </p>

        <div v-else class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[32rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">PO</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 font-medium">Received</th>
                            <th class="px-4 py-3 font-medium">Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="cursor-pointer border-b border-mist last:border-b-0 dark:border-white/10"
                            :class="selectedId === item.id ? 'bg-pine/10' : ''"
                            @click="selectItem(item)"
                        >
                            <td class="px-4 py-3">{{ item.purchase_order }}</td>
                            <td class="px-4 py-3 font-medium">{{ item.product }} · {{ item.variant }}</td>
                            <td class="px-4 py-3">{{ item.received_quantity }}</td>
                            <td class="px-4 py-3">{{ item.remaining }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5" @submit.prevent="submit">
                <h2 class="text-lg font-medium">Assign to a branch</h2>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Branch</label>
                    <select
                        v-model="form.branch_id"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                    >
                        <option value="">Choose branch</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                    </select>
                    <p v-if="form.errors.branch_id" class="text-sm text-danger">{{ form.errors.branch_id }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Quantity</label>
                    <input
                        v-model="form.quantity"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="1"
                        required
                        type="number"
                    />
                    <p v-if="form.errors.quantity" class="text-sm text-danger">{{ form.errors.quantity }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Selling price</label>
                    <input
                        v-model="form.selling_price"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                    <p v-if="form.errors.selling_price" class="text-sm text-danger">{{ form.errors.selling_price }}</p>
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Assigning…' : 'Assign stock' }}
                </button>
            </form>
        </div>
    </div>
</template>
