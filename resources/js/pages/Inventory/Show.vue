<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    locations: {
        type: Array,
        required: true,
    },
    purchases: {
        type: Array,
        required: true,
    },
    sales: {
        type: Array,
        required: true,
    },
    history: {
        type: Array,
        required: true,
    },
    repack_dates: {
        type: Array,
        required: true,
    },
});

const copies = ref(1);

const labelHref = computed(
    () => `/backoffice/inventory/products/${props.product.id}/labels?copies=${Math.max(1, Number(copies.value) || 1)}`,
);

const stats = computed(() => [
    { label: 'On hand', value: props.summary.on_hand, hint: 'Units across locations' },
    { label: 'Inventory value', value: props.summary.value, hint: 'At latest purchase price' },
    { label: 'Purchased', value: props.summary.purchased, hint: props.summary.last_purchased || 'No deliveries yet' },
    { label: 'Sold', value: props.summary.sold, hint: 'POS sales are not recorded here yet' },
]);
</script>

<template>
    <Head :title="product.name" />

    <Teleport defer to="#admin-header-actions">
        <a
            class="inline-flex h-10 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
            :href="`${labelHref}&download=1`"
        >
            Download labels
        </a>
        <a
            class="inline-flex h-10 items-center rounded-xl bg-pine px-4 text-sm font-medium text-foam"
            :href="labelHref"
            target="_blank"
        >
            Print labels
        </a>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-col gap-1">
            <Link class="text-sm font-medium text-pine dark:text-sand" href="/backoffice/inventory">Back to inventory</Link>
            <h1 class="text-2xl font-medium">{{ product.name }}</h1>
            <p class="text-sm text-ink/60 dark:text-foam/60">
                {{ product.variant }}
                <span v-if="product.sku"> · {{ product.sku }}</span>
                <span v-if="product.barcode"> · {{ product.barcode }}</span>
            </p>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Product inventory report">
            <article
                v-for="stat in stats"
                :key="stat.label"
                class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
            >
                <p class="text-sm text-ink/60 dark:text-foam/60">{{ stat.label }}</p>
                <p class="text-3xl font-medium">{{ stat.value }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">{{ stat.hint }}</p>
            </article>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-lg font-medium">Pricing</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="flex flex-col gap-1">
                        <dt class="text-ink/50 dark:text-foam/50">Purchase price</dt>
                        <dd class="font-medium">{{ product.purchase_price }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-ink/50 dark:text-foam/50">Retail price</dt>
                        <dd class="font-medium">{{ product.retail_price }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-ink/50 dark:text-foam/50">Markup</dt>
                        <dd class="font-medium">{{ product.markup }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-ink/50 dark:text-foam/50">Last repacked</dt>
                        <dd class="font-medium">{{ summary.last_repacked || '—' }}</dd>
                    </div>
                    <div v-if="repack_dates.length" class="col-span-2 flex flex-col gap-1">
                        <dt class="text-ink/50 dark:text-foam/50">Repack dates</dt>
                        <dd class="font-medium">{{ repack_dates.join(', ') }}</dd>
                    </div>
                </dl>
            </article>
            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-lg font-medium">Location / Qty</h2>
                <ul v-if="locations.length" class="flex flex-col gap-2 text-sm">
                    <li v-for="location in locations" :key="location.name" class="flex justify-between gap-4">
                        <span>{{ location.name }}</span>
                        <span class="font-medium tabular-nums">{{ location.quantity }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-ink/60 dark:text-foam/60">No branch stock assigned yet.</p>
                <div class="flex flex-col gap-2 border-t border-mist pt-4 dark:border-white/10">
                    <label class="text-sm font-medium" for="label-copies">Label copies</label>
                    <div class="flex flex-wrap gap-2">
                        <input
                            id="label-copies"
                            v-model="copies"
                            class="h-11 w-28 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="1"
                            max="48"
                            type="number"
                        />
                        <a
                            class="inline-flex h-11 items-center rounded-xl bg-pine px-4 text-sm font-medium text-foam"
                            :href="labelHref"
                            target="_blank"
                        >
                            Generate labels
                        </a>
                    </div>
                    <p class="text-sm text-ink/55 dark:text-foam/50">
                        Includes barcode, retail price, name, variant, and repack dates.
                    </p>
                </div>
            </article>
        </section>

        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-medium">Purchases</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="py-3 pr-4 font-medium">Received</th>
                            <th class="py-3 pr-4 font-medium">PO</th>
                            <th class="py-3 pr-4 font-medium">Supplier</th>
                            <th class="py-3 pr-4 font-medium">Qty</th>
                            <th class="py-3 pr-4 font-medium">Cost</th>
                            <th class="py-3 font-medium">DR / SI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="purchases.length === 0">
                            <td class="py-8 text-ink/60 dark:text-foam/60" colspan="6">No purchases recorded yet.</td>
                        </tr>
                        <tr v-for="purchase in purchases" :key="purchase.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="py-3 pr-4">{{ purchase.occurred_at }}</td>
                            <td class="py-3 pr-4">{{ purchase.reference }}</td>
                            <td class="py-3 pr-4">{{ purchase.supplier || '—' }}</td>
                            <td class="py-3 pr-4 tabular-nums">{{ purchase.quantity }}</td>
                            <td class="py-3 pr-4">{{ purchase.unit_cost }}</td>
                            <td class="py-3">{{ purchase.delivery_receipt || '—' }} / {{ purchase.sales_invoice || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-medium">Sales</h2>
            <p class="text-sm text-ink/70 dark:text-foam/70">
                No sales have been recorded for this product. Point-of-sale transactions will show here once they are connected.
            </p>
        </section>

        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-medium">Stock history</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="py-3 pr-4 font-medium">When</th>
                            <th class="py-3 pr-4 font-medium">Type</th>
                            <th class="py-3 pr-4 font-medium">Qty</th>
                            <th class="py-3 pr-4 font-medium">Location</th>
                            <th class="py-3 pr-4 font-medium">Reference</th>
                            <th class="py-3 font-medium">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="history.length === 0">
                            <td class="py-8 text-ink/60 dark:text-foam/60" colspan="6">No stock movements yet.</td>
                        </tr>
                        <tr v-for="row in history" :key="row.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="py-3 pr-4">{{ row.occurred_at }}</td>
                            <td class="py-3 pr-4">{{ row.type }}</td>
                            <td class="py-3 pr-4 tabular-nums">{{ row.quantity }}</td>
                            <td class="py-3 pr-4">{{ row.location }}</td>
                            <td class="py-3 pr-4">{{ row.reference || '—' }}</td>
                            <td class="py-3">{{ row.detail }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
