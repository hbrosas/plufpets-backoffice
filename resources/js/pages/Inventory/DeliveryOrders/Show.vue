<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const form = useForm({
    delivery_receipt_number: '',
    sales_invoice_number: '',
    items: props.order.items
        .filter((item) => item.remaining > 0)
        .map((item) => ({
            id: item.id,
            quantity: item.remaining,
        })),
});

function submit() {
    form.post(`/backoffice/inventory/delivery-orders/${props.order.id}/receive`);
}
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
            <div
                v-for="(row, index) in form.items"
                :key="row.id"
                class="grid gap-3 rounded-2xl border border-mist p-4 sm:grid-cols-2 dark:border-white/10"
            >
                <div>
                    <p class="font-medium">
                        {{ order.items.find((item) => item.id === row.id)?.product }}
                    </p>
                    <p class="text-sm text-ink/60 dark:text-foam/60">
                        Remaining {{ order.items.find((item) => item.id === row.id)?.remaining }}
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Received now</label>
                    <input
                        v-model="row.quantity"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="1"
                        required
                        type="number"
                    />
                    <p v-if="form.errors[`items.${index}.quantity`]" class="text-sm text-danger">
                        {{ form.errors[`items.${index}.quantity`] }}
                    </p>
                </div>
            </div>
            <p v-if="form.errors.items" class="text-sm text-danger">{{ form.errors.items }}</p>
            <button
                class="h-11 self-start rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Receive' }}
            </button>
        </form>

        <p v-else class="text-sm text-ink/70 dark:text-foam/70">This purchase order is fully received.</p>

        <DeliveryReceiptHistory :receipts="order.receipts" />

        <Link class="text-sm font-medium text-sand" href="/backoffice/inventory/delivery-orders">Back to delivery orders</Link>
    </div>
</template>
