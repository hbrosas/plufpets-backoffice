<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import DeliveryReceiptHistory from '../../../components/DeliveryReceiptHistory.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

function receiveRows(order) {
    return order.items
        .filter((item) => item.remaining > 0)
        .map((item) => ({
            id: item.id,
            product: item.product,
            variant: item.variant,
            expected: item.remaining,
            checked: false,
            quantity: '',
        }));
}

const form = useForm({
    delivery_receipt_number: '',
    sales_invoice_number: '',
    notes: '',
    items: receiveRows(props.order),
});

const allChecked = computed(() => form.items.length > 0 && form.items.every((row) => row.checked));
const hasSelection = computed(() => form.items.some((row) => row.checked && Number(row.quantity) > 0));

function setReceived(row, checked) {
    row.checked = checked;
    row.quantity = checked ? row.expected : '';
}

function syncChecked(row) {
    row.checked = row.quantity !== '' && Number(row.quantity) > 0;
}

function toggleAll(event) {
    form.items.forEach((row) => setReceived(row, event.target.checked));
}

function rowError(row) {
    const index = form.items.filter((item) => item.checked).findIndex((item) => item.id === row.id);

    if (index === -1) {
        return null;
    }

    return form.errors[`items.${index}.quantity`] || form.errors[`items.${index}.id`] || null;
}

function submit() {
    form.transform((data) => ({
        delivery_receipt_number: data.delivery_receipt_number,
        sales_invoice_number: data.sales_invoice_number,
        notes: data.notes,
        items: data.items
            .filter((row) => row.checked && Number(row.quantity) > 0)
            .map((row) => ({
                id: row.id,
                quantity: Number(row.quantity),
            })),
    })).post(`/backoffice/inventory/delivery-orders/${props.order.id}/receive`);
}

watch(
    () => props.order.items.map((item) => `${item.id}:${item.remaining}`).join('|'),
    () => {
        form.defaults({
            delivery_receipt_number: '',
            sales_invoice_number: '',
            notes: '',
            items: receiveRows(props.order),
        });
        form.reset();
        form.clearErrors();
    },
);
</script>

<template>
    <Head :title="`${order.number} delivery`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-ink/60 dark:text-foam/60">{{ order.delivery_status_label }}</p>
            <h2 class="mt-1 text-2xl font-medium">{{ order.number }}</h2>
            <p class="mt-2">{{ order.supplier }}</p>
            <div class="mt-4 flex flex-wrap gap-6 text-sm">
                <p>Total quantity <span class="font-medium">{{ order.ordered_quantity }}</span></p>
                <p>Received <span class="font-medium">{{ order.received_quantity }}</span></p>
                <p>Total amount <span class="font-medium">{{ order.total }}</span></p>
            </div>
        </div>

        <form v-if="form.items.length > 0" class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="delivery-receipt-number">Delivery receipt number</label>
                    <input
                        id="delivery-receipt-number"
                        v-model="form.delivery_receipt_number"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        type="text"
                    />
                    <p v-if="form.errors.delivery_receipt_number" class="text-sm text-danger">
                        {{ form.errors.delivery_receipt_number }}
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="sales-invoice-number">Sales invoice number</label>
                    <input
                        id="sales-invoice-number"
                        v-model="form.sales_invoice_number"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        type="text"
                    />
                    <p v-if="form.errors.sales_invoice_number" class="text-sm text-danger">
                        {{ form.errors.sales_invoice_number }}
                    </p>
                </div>
            </div>
            <p v-if="order.receiver" class="text-sm text-ink/70 dark:text-foam/70">
                Receiver {{ order.receiver.name }} (#{{ order.receiver.employee_number }})
            </p>
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[40rem] text-left text-sm">
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
                            <th class="px-4 py-3 font-medium">Qty</th>
                            <th class="px-4 py-3 font-medium">Received</th>
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
                                    :aria-label="`Receive ${row.product}`"
                                    @change="setReceived(row, $event.target.checked)"
                                />
                            </td>
                            <td class="px-4 py-3 font-medium">{{ row.product }}</td>
                            <td class="px-4 py-3">{{ row.variant || '—' }}</td>
                            <td class="px-4 py-3">{{ row.expected }}</td>
                            <td class="px-4 py-3">
                                <input
                                    v-model="row.quantity"
                                    class="h-10 w-24 rounded-xl border border-mist bg-white px-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                                    :max="row.expected"
                                    min="1"
                                    type="number"
                                    :aria-label="`Received ${row.product}`"
                                    @input="syncChecked(row)"
                                />
                                <p v-if="rowError(row)" class="mt-1 text-sm text-danger">{{ rowError(row) }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="delivery-notes">Notes</label>
                <textarea
                    id="delivery-notes"
                    v-model="form.notes"
                    class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                />
                <p v-if="form.errors.notes" class="text-sm text-danger">{{ form.errors.notes }}</p>
            </div>
            <p v-if="form.errors.items" class="text-sm text-danger">{{ form.errors.items }}</p>
            <button
                class="h-11 self-start rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                type="submit"
                :disabled="form.processing || !hasSelection"
            >
                {{ form.processing ? 'Saving…' : 'Receive' }}
            </button>
        </form>

        <p v-else class="text-sm text-ink/70 dark:text-foam/70">This purchase order is fully received.</p>

        <DeliveryReceiptHistory :receipts="order.receipts" />

    </div>
</template>
