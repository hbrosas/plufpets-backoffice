<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatPeso } from '../../formatPeso';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    branches: {
        type: Array,
        required: true,
    },
    brands: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    lines: {
        type: Array,
        required: true,
    },
});

const bulkQuantity = ref('');

const filters = useForm({
    scope: props.filters.scope || 'all',
    brand_id: props.filters.brand_id || '',
    category_id: props.filters.category_id || '',
    branch_id: props.filters.branch_id || '',
});

const form = useForm({
    branch_id: props.filters.branch_id || '',
    scope: props.filters.scope || 'all',
    brand_id: props.filters.brand_id || '',
    category_id: props.filters.category_id || '',
    record_as_sale: false,
    lines: quantityLines(props.lines),
});

const salePreview = computed(() => {
    let units = 0;
    let amount = 0;
    let missingPrice = false;

    form.lines.forEach((line, index) => {
        const source = props.lines[index];
        const next = Number(line.quantity);
        const current = Number(source?.quantity ?? 0);

        if (!Number.isFinite(next) || next >= current) {
            return;
        }

        units += current - next;

        if (source?.retail_price_amount === null || source?.retail_price_amount === undefined) {
            missingPrice = true;

            return;
        }

        amount += (current - next) * Number(source.retail_price_amount);
    });

    return {
        units,
        amount,
        missingPrice,
    };
});

watch(() => props.lines, (lines) => {
    form.branch_id = props.filters.branch_id || '';
    form.scope = props.filters.scope || 'all';
    form.brand_id = props.filters.brand_id || '';
    form.category_id = props.filters.category_id || '';
    form.record_as_sale = false;
    form.lines = quantityLines(lines);
    form.clearErrors();
});

function quantityLines(lines) {
    return lines.map((line) => ({
        catalog_item_variant_id: line.variant_id,
        quantity: line.quantity,
    }));
}

function showProducts() {
    filters.get('/backoffice/inventory/adjustments', {
        preserveState: true,
        replace: true,
    });
}

function applyBulkQuantity() {
    if (bulkQuantity.value === '' || bulkQuantity.value === null) {
        return;
    }

    const quantity = Number(bulkQuantity.value);

    if (!Number.isInteger(quantity) || quantity < 0) {
        return;
    }

    form.lines.forEach((line) => {
        line.quantity = quantity;
    });
}

function submit() {
    form.post('/backoffice/inventory/adjustments');
}
</script>

<template>
    <Head title="Stock Adjustment" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-medium">Stock Adjustment</h1>
                <p class="mt-1 text-sm text-ink/60 dark:text-foam/60">
                    Update on-hand quantities for every product, one brand, or one category.
                </p>
            </div>
            <Link class="text-sm font-medium" href="/backoffice/inventory">Back to inventory</Link>
        </div>

        <form class="flex flex-col gap-3 sm:flex-row sm:flex-wrap" @submit.prevent="showProducts">
            <select
                v-model="filters.scope"
                class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine sm:w-48 dark:border-white/10 dark:bg-white/5"
                aria-label="Products"
            >
                <option value="all">All products</option>
                <option value="brand">By brand</option>
                <option value="category">By category</option>
            </select>
            <select
                v-if="filters.scope === 'brand'"
                v-model="filters.brand_id"
                class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine sm:w-56 dark:border-white/10 dark:bg-white/5"
                aria-label="Brand"
                required
            >
                <option value="">Choose brand</option>
                <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
            </select>
            <select
                v-if="filters.scope === 'category'"
                v-model="filters.category_id"
                class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine sm:w-56 dark:border-white/10 dark:bg-white/5"
                aria-label="Category"
                required
            >
                <option value="">Choose category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <select
                v-model="filters.branch_id"
                class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine sm:w-56 dark:border-white/10 dark:bg-white/5"
                aria-label="Location"
                required
            >
                <option value="">Choose location</option>
                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
            </select>
            <button class="h-11 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15" type="submit">
                Show products
            </button>
        </form>
        <p v-if="filters.errors.branch_id || filters.errors.brand_id || filters.errors.category_id" class="text-sm text-danger">
            {{ filters.errors.branch_id || filters.errors.brand_id || filters.errors.category_id }}
        </p>

        <form v-if="lines.length > 0" class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="bulk-quantity">Set every quantity to</label>
                    <input
                        id="bulk-quantity"
                        v-model="bulkQuantity"
                        class="h-11 w-48 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="0"
                        type="number"
                    />
                </div>
                <button class="h-11 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15" type="button" @click="applyBulkQuantity">
                    Apply to all
                </button>
            </div>

            <p class="text-sm text-ink/60 dark:text-foam/60">Sold and profit are the totals so far for each product.</p>
            <p v-if="form.errors.lines" class="text-sm text-danger">{{ form.errors.lines }}</p>

            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[52rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 font-medium">On hand</th>
                            <th class="px-4 py-3 font-medium">New quantity</th>
                            <th class="px-4 py-3 font-medium">Sold</th>
                            <th class="px-4 py-3 font-medium">Profit</th>
                            <th class="px-4 py-3 font-medium">Retail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in lines" :key="line.variant_id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ line.product }}</p>
                                <p class="text-ink/55 dark:text-foam/50">
                                    {{ line.variant !== 'Default' ? `${line.variant} · ` : '' }}{{ line.sku || 'No SKU' }}
                                </p>
                                <p v-if="form.errors[`lines.${index}.catalog_item_variant_id`] || form.errors[`lines.${index}.quantity`]" class="mt-1 text-sm text-danger">
                                    {{ form.errors[`lines.${index}.catalog_item_variant_id`] || form.errors[`lines.${index}.quantity`] }}
                                </p>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ line.quantity }}</td>
                            <td class="px-4 py-3">
                                <input
                                    v-model="form.lines[index].quantity"
                                    class="h-10 w-28 rounded-xl border border-mist bg-white px-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                                    min="0"
                                    required
                                    type="number"
                                    :aria-label="`New quantity for ${line.product}`"
                                />
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ line.sold }}</td>
                            <td class="px-4 py-3">{{ line.profit }}</td>
                            <td class="px-4 py-3">{{ line.retail_price }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-mist bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <input v-model="form.record_as_sale" class="mt-1 h-4 w-4 accent-pine" type="checkbox" />
                <span>
                    <span class="font-medium">Treat quantity decreases as a sale</span>
                    <span class="mt-1 block text-sm text-ink/65 dark:text-foam/65">
                        Reduced units are sold at the retail price and counted in sold and profit. Leave this unchecked to change the on-hand quantity only.
                    </span>
                    <span v-if="form.record_as_sale && salePreview.units > 0" class="mt-2 block text-sm font-medium">
                        Sale: {{ salePreview.units }} unit{{ salePreview.units === 1 ? '' : 's' }}
                        <template v-if="!salePreview.missingPrice"> · {{ formatPeso(salePreview.amount) }}</template>
                    </span>
                    <span v-if="form.record_as_sale && salePreview.missingPrice" class="mt-1 block text-sm text-danger">
                        A product being reduced has no retail price.
                    </span>
                </span>
            </label>
            <p v-if="form.errors.record_as_sale" class="text-sm text-danger">{{ form.errors.record_as_sale }}</p>

            <button
                class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Save adjustment' }}
            </button>
        </form>

        <p v-else class="text-sm text-ink/60 dark:text-foam/60">
            Choose a location, then show products. Sold and profit appear on every row before you save.
        </p>
    </div>
</template>
