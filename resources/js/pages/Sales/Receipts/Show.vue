<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SalesRefundModal from '../../../components/SalesRefundModal.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    receipt: { type: Object, required: true },
});

const refundOpen = ref(false);
</script>

<template>
    <Head :title="receipt.number" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
            <Link class="text-sm font-medium text-pine dark:text-sand" href="/backoffice/sales/receipts">Back to receipts</Link>
            <button
                v-if="!receipt.is_refunded"
                class="h-10 rounded-xl bg-danger px-4 text-sm font-medium text-foam"
                type="button"
                @click="refundOpen = true"
            >
                Refund
            </button>
        </div>

        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-medium">{{ receipt.number }}</h1>
                    <p class="text-sm text-ink/60 dark:text-foam/60">{{ receipt.sold_at }}</p>
                    <p v-if="receipt.is_refunded" class="text-sm text-danger">
                        Refunded {{ receipt.refunded_at }} by {{ receipt.refunded_by }}
                        <span v-if="receipt.refund_reason"> · {{ receipt.refund_reason }}</span>
                    </p>
                </div>
                <p class="text-2xl font-medium">{{ receipt.total }}</p>
            </div>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="flex flex-col gap-0.5">
                    <dt class="text-ink/55 dark:text-foam/50">Branch</dt>
                    <dd>{{ receipt.branch }}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-ink/55 dark:text-foam/50">Cashier</dt>
                    <dd>{{ receipt.cashier }} · #{{ receipt.employee_number }}</dd>
                </div>
                <div v-if="receipt.delivery" class="flex flex-col gap-0.5">
                    <dt class="text-ink/55 dark:text-foam/50">Delivery</dt>
                    <dd>{{ receipt.delivery }}</dd>
                </div>
            </dl>
        </section>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Item</th>
                        <th class="px-4 py-3 font-medium">Qty</th>
                        <th class="px-4 py-3 font-medium">Price</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in receipt.items" :key="item.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ item.product }}</p>
                            <p v-if="item.variant && item.variant !== 'Default'" class="text-xs text-ink/50">{{ item.variant }}</p>
                            <p v-if="item.notes" class="text-xs text-ink/50">{{ item.notes }}</p>
                        </td>
                        <td class="px-4 py-3">{{ item.quantity }}</td>
                        <td class="px-4 py-3">{{ item.unit_price }}</td>
                        <td class="px-4 py-3">{{ item.line_total }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="grid gap-4 sm:grid-cols-2">
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-sm font-medium">Payments</h2>
                <ul class="flex flex-col gap-2 text-sm">
                    <li v-for="(payment, index) in receipt.payments" :key="`${payment.name}-${index}`" class="flex justify-between gap-3">
                        <span>
                            {{ payment.name }}
                            <span v-if="payment.reference" class="text-ink/50"> · {{ payment.reference }}</span>
                        </span>
                        <span>{{ payment.amount }}</span>
                    </li>
                    <li v-if="receipt.payments.length === 0" class="text-ink/60 dark:text-foam/60">No payment lines.</li>
                </ul>
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-sm font-medium">Totals</h2>
                <dl class="flex flex-col gap-2 text-sm">
                    <div class="flex justify-between gap-3"><dt>Subtotal</dt><dd>{{ receipt.subtotal }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Delivery</dt><dd>{{ receipt.delivery_fee }}</dd></div>
                    <div v-for="tax in receipt.taxes" :key="tax.name" class="flex justify-between gap-3">
                        <dt>{{ tax.name }}</dt>
                        <dd>{{ tax.amount }}</dd>
                    </div>
                    <div v-if="receipt.taxes.length === 0" class="flex justify-between gap-3"><dt>Tax</dt><dd>{{ receipt.tax }}</dd></div>
                    <div class="flex justify-between gap-3 font-medium"><dt>Total</dt><dd>{{ receipt.total }}</dd></div>
                </dl>
            </article>
        </section>
    </div>

    <SalesRefundModal v-if="refundOpen" :receipt="receipt" @close="refundOpen = false" />
</template>
