<script setup>
import { ref } from 'vue';
import { beginAssignedPrint, finishAssignedPrint } from '../../../printerConnection';
import { showToast } from '../../../toast';

const props = defineProps({
    shift: { type: Object, required: true },
});

const printing = ref(false);

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
        showToast(error instanceof Error ? error.message : 'Could not print on the assigned printer.');
    } finally {
        printing.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-medium">{{ shift.branch }}</h1>
                    <p class="text-sm text-ink/60 dark:text-foam/60">{{ shift.opened_at }}</p>
                    <span
                        class="mt-1 w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            shift.status === 'Open'
                                ? 'bg-pine/10 text-pine dark:bg-sand/15 dark:text-sand'
                                : 'bg-mist text-ink/50 dark:bg-white/10 dark:text-foam/50'
                        "
                    >
                        {{ shift.status }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        v-if="shift.z_report_href"
                        class="inline-flex h-10 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                        :href="shift.z_report_href"
                        target="_blank"
                        rel="noreferrer"
                    >
                        View
                    </a>
                    <button
                        v-else
                        class="h-10 cursor-not-allowed rounded-xl border border-mist px-4 text-sm font-medium opacity-40 dark:border-white/15"
                        type="button"
                        disabled
                        title="Close the shift to view a Z-Report"
                    >
                        View
                    </button>
                    <button
                        v-if="shift.z_report_print_href"
                        class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-70"
                        type="button"
                        :disabled="printing"
                        @click="printZReport"
                    >
                        {{ printing ? 'Printing…' : 'Print Z-Report' }}
                    </button>
                    <button
                        v-else
                        class="h-10 cursor-not-allowed rounded-xl bg-pine px-4 text-sm font-medium text-foam opacity-40"
                        type="button"
                        disabled
                        title="Close the shift to print a Z-Report"
                    >
                        Print Z-Report
                    </button>
                </div>
            </div>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="flex flex-col gap-0.5">
                    <dt class="text-ink/55 dark:text-foam/50">Opened by</dt>
                    <dd>{{ shift.opened_by }}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-ink/55 dark:text-foam/50">Closed</dt>
                    <dd>{{ shift.closed_at ? `${shift.closed_at} · ${shift.closed_by}` : '—' }}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-ink/55 dark:text-foam/50">Receipts</dt>
                    <dd>{{ shift.receipts }}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-ink/55 dark:text-foam/50">Sales</dt>
                    <dd>{{ shift.sales_total }}</dd>
                </div>
            </dl>
        </section>

        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-medium">Transactions</h2>
            <p v-if="shift.transactions.length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                No transactions on this shift.
            </p>
            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="row in shift.transactions"
                    :key="row.key"
                    class="flex items-start justify-between gap-3 rounded-xl border border-mist px-3 py-3 text-sm dark:border-white/10"
                >
                    <div class="min-w-0">
                        <p class="font-medium">{{ row.title }}</p>
                        <p class="text-xs text-ink/50">{{ row.type }} · {{ row.time }} · {{ row.actor }}</p>
                        <p v-if="row.detail" class="mt-1 text-xs text-ink/55 dark:text-foam/55">{{ row.detail }}</p>
                    </div>
                    <p class="shrink-0 font-medium">{{ row.amount }}</p>
                </li>
            </ul>
        </section>
    </div>
</template>
