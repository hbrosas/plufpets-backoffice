<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SalesRefundModal from '../../../components/SalesRefundModal.vue';

const props = defineProps({
    receipt: { type: Object, required: true },
    canWaive: { type: Boolean, default: false },
});

const refundOpen = ref(false);
const waiveForm = useForm({
    employee_id: 'all',
});

const openEmployees = computed(() => {
    const employees = new Map();

    for (const line of props.receipt.commissions ?? []) {
        if (line.waived || employees.has(line.employee_id)) {
            continue;
        }

        employees.set(line.employee_id, line);
    }

    return [...employees.values()];
});

function waiveCommission() {
    const params = window.location.search;

    waiveForm.post(`/backoffice/sales/receipts/${props.receipt.id}/commissions/waive${params}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
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
                <div class="flex flex-col items-end gap-2">
                    <p class="text-2xl font-medium">{{ receipt.total }}</p>
                    <button
                        v-if="!receipt.is_refunded"
                        class="h-10 rounded-xl bg-danger px-4 text-sm font-medium text-foam"
                        type="button"
                        @click="refundOpen = true"
                    >
                        Refund
                    </button>
                </div>
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

        <section
            v-if="(receipt.commissions ?? []).length > 0"
            class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
        >
            <h2 class="text-sm font-medium">Commissions</h2>
            <ul class="flex flex-col gap-3 text-sm">
                <li v-for="line in receipt.commissions" :key="line.id" class="flex flex-wrap items-baseline justify-between gap-2">
                    <span>
                        <span class="font-medium">{{ line.employee_name }}</span>
                        <span class="text-ink/55 dark:text-foam/50"> · #{{ line.employee_number }} · {{ line.role }} · {{ line.item }}</span>
                        <span v-if="line.waived" class="text-ink/55 dark:text-foam/50"> · Waived {{ line.waived_at }} by {{ line.waived_by }}</span>
                    </span>
                    <span :class="line.waived ? 'text-ink/40 line-through dark:text-foam/40' : ''">{{ line.amount }}</span>
                </li>
            </ul>
            <form
                v-if="canWaive && !receipt.is_refunded && openEmployees.length > 0"
                class="flex flex-col gap-3 border-t border-mist pt-4 dark:border-white/10"
                @submit.prevent="waiveCommission"
            >
                <label class="flex max-w-sm flex-col gap-1 text-sm" for="waive-commission-employee">
                    Employee
                    <select
                        id="waive-commission-employee"
                        v-model="waiveForm.employee_id"
                        class="h-10 rounded-xl border border-mist bg-white pr-10 pl-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
                    >
                        <option value="all">All employees</option>
                        <option v-for="employee in openEmployees" :key="employee.employee_id" :value="String(employee.employee_id)">
                            {{ employee.employee_name }} · #{{ employee.employee_number }}
                        </option>
                    </select>
                </label>
                <p v-if="waiveForm.errors.employee_id" class="text-sm text-danger">{{ waiveForm.errors.employee_id }}</p>
                <button
                    class="h-10 w-fit rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="waiveForm.processing"
                >
                    {{ waiveForm.processing ? 'Waiving…' : 'Waive commission' }}
                </button>
            </form>
        </section>

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
