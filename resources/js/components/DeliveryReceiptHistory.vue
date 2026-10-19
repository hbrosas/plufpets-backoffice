<script setup>
defineProps({
    receipts: {
        type: Array,
        default: () => [],
    },
    showHeading: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <h2 v-if="showHeading" class="text-lg font-medium">Transaction history</h2>
        <p v-if="receipts.length === 0" class="text-sm text-ink/70 dark:text-foam/70">
            No deliveries recorded yet.
        </p>
        <article
            v-for="receipt in receipts"
            :key="receipt.id"
            class="rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wide text-ink/50 uppercase dark:text-foam/50">Received</p>
                    <p class="text-sm font-medium">{{ receipt.received_at }}</p>
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wide text-ink/50 uppercase dark:text-foam/50">Received by</p>
                    <p class="text-sm font-medium">
                        {{ receipt.received_by }}
                        <span v-if="receipt.received_by_employee_number" class="font-normal text-ink/60 dark:text-foam/60">
                            #{{ receipt.received_by_employee_number }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wide text-ink/50 uppercase dark:text-foam/50">
                        Delivery receipt
                    </p>
                    <p class="text-sm font-medium">{{ receipt.delivery_receipt_number || '—' }}</p>
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wide text-ink/50 uppercase dark:text-foam/50">Sales invoice</p>
                    <p class="text-sm font-medium">{{ receipt.sales_invoice_number || '—' }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-mist dark:border-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-3 py-2 font-medium">Product</th>
                            <th class="px-3 py-2 font-medium">Qty received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in receipt.items"
                            :key="item.id"
                            class="border-b border-mist last:border-b-0 dark:border-white/10"
                        >
                            <td class="px-3 py-2 font-medium">
                                {{ item.product }}
                                <span v-if="item.variant" class="font-normal text-ink/60 dark:text-foam/60">
                                    · {{ item.variant }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ item.quantity }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</template>
