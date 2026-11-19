<script setup>
import { computed, ref } from 'vue';
import { beginAssignedPrint, finishAssignedPrint } from '../../../printerConnection';
import { showToast } from '../../../toast';

const props = defineProps({
    shift: { type: Object, required: true },
});

const printing = ref(false);
const totals = computed(() => {
    const report = props.shift.z_report;

    if (!report) {
        return [];
    }

    return [
        ['Items sold', String(report.item_count)],
        ['Sales total', report.sales_total],
        ['Starting cash', report.starting_cash],
        ['Cash sales', report.cash_sales],
        ['Cash in', report.cash_in],
        ['Cash out', report.cash_out],
        ['Expected cash', report.expected_cash],
        ['Counted cash', report.counted_cash],
        ['Variance', report.variance],
        ['Amount to remit', report.remit_amount],
    ];
});

async function printZReport() {
    if (!props.shift.z_report_print_href) {
        return;
    }

    const session = beginAssignedPrint(props.shift.receipt_printer);
    printing.value = true;
    showToast(`Printing Z-Report for ${props.shift.branch}…`);

    try {
        const response = await fetch(props.shift.z_report_print_href, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Could not load the Z-Report for this shift.');
        }

        const payload = await response.json();
        const printer = payload.printer ?? props.shift.receipt_printer;
        await finishAssignedPrint(printer, payload.receipt ?? '', session);
        showToast(
            printer?.device_name
                ? `Sent Z-Report to ${printer.device_name}.`
                : 'Sent Z-Report to the assigned printer.',
        );
    } catch (error) {
        showToast(error instanceof Error ? error.message : 'Could not print on the assigned printer.', { tone: 'error' });
    } finally {
        printing.value = false;
    }
}
</script>

<template>
    <div v-if="!shift.z_report" class="text-sm text-ink/60 dark:text-foam/60">
        Close the shift to view a Z-Report.
    </div>
    <div v-else class="flex flex-col gap-4">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div class="flex flex-col gap-0.5">
                <dt class="text-ink/55 dark:text-foam/50">Opened by</dt>
                <dd>{{ shift.opened_by }} · {{ shift.opened_at }}</dd>
            </div>
            <div class="flex flex-col gap-0.5">
                <dt class="text-ink/55 dark:text-foam/50">Closed by</dt>
                <dd>{{ shift.closed_by }} · {{ shift.closed_at }}</dd>
            </div>
        </dl>

        <div class="overflow-x-auto rounded-2xl border border-mist dark:border-white/10">
            <table class="w-full text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Payment method</th>
                        <th class="px-4 py-3 text-right font-medium">Sales</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="shift.z_report.payments.length === 0">
                        <td class="px-4 py-6 text-ink/60 dark:text-foam/60" colspan="3">No sales this shift.</td>
                    </tr>
                    <tr
                        v-for="payment in shift.z_report.payments"
                        :key="payment.name"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3">{{ payment.name }}</td>
                        <td class="px-4 py-3 text-right">{{ payment.count }}</td>
                        <td class="px-4 py-3 text-right">{{ payment.total }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <dl class="overflow-hidden rounded-2xl border border-mist text-sm dark:border-white/10">
            <div
                v-for="[label, value] in totals"
                :key="label"
                class="flex items-center justify-between gap-3 border-b border-mist px-4 py-3 last:border-b-0 dark:border-white/10"
            >
                <dt>{{ label }}</dt>
                <dd class="font-medium">{{ value }}</dd>
            </div>
        </dl>

        <div class="flex justify-end">
            <button
                class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-70"
                type="button"
                :disabled="printing || !shift.z_report_print_href"
                @click="printZReport"
            >
                {{ printing ? 'Printing…' : 'Print Z-Report' }}
            </button>
        </div>
    </div>
</template>
