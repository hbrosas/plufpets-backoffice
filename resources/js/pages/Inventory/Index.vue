<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SearchableSelect from '../../components/SearchableSelect.vue';
import { formatPeso } from '../../formatPeso';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    rows: {
        type: Array,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    branches: {
        type: Array,
        required: true,
    },
    variants: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const transferOpen = ref(false);
const convertOpen = ref(false);
const activeRow = ref(null);
const expandedIds = ref([]);

const filters = useForm({
    search: props.filters.search,
    branch_id: props.filters.branch_id,
});

const transferForm = useForm({
    from_branch_id: '',
    to_branch_id: '',
    catalog_item_variant_id: '',
    quantity: 1,
    notes: '',
});

const convertForm = useForm({
    branch_id: '',
    from_catalog_item_variant_id: '',
    to_catalog_item_variant_id: '',
    quantity: 1,
    pieces_per_unit: 1,
    notes: '',
});

const transferSourceBranches = computed(() =>
    (activeRow.value?.locations ?? []).filter((location) => location.branch_id && location.quantity > 0),
);

const convertSourceBranches = computed(() => transferSourceBranches.value);

const convertTargets = computed(() =>
    props.variants.filter((variant) => String(variant.id) !== String(activeRow.value?.variant_id)),
);

const convertedPieces = computed(
    () => Number(convertForm.quantity || 0) * Number(convertForm.pieces_per_unit || 0),
);

const sourcePurchasePrice = computed(() => {
    const amount = Number(activeRow.value?.purchase_price_amount);

    return Number.isFinite(amount) && amount > 0 ? amount : null;
});

const convertedPurchasePrice = computed(() => {
    const piecesPerUnit = Number(convertForm.pieces_per_unit || 0);

    if (sourcePurchasePrice.value === null || piecesPerUnit < 1) {
        return null;
    }

    return Math.round((sourcePurchasePrice.value / piecesPerUnit) * 100) / 100;
});

function applyFilters() {
    filters.get('/backoffice/inventory', {
        preserveState: true,
        replace: true,
    });
}

function isExpanded(rowId) {
    return expandedIds.value.includes(rowId);
}

function toggleRow(rowId) {
    if (isExpanded(rowId)) {
        expandedIds.value = expandedIds.value.filter((id) => id !== rowId);

        return;
    }

    expandedIds.value = [...expandedIds.value, rowId];
}

function openTransfer(row) {
    activeRow.value = row;
    transferForm.clearErrors();
    transferForm.from_branch_id = row.locations.find((location) => location.branch_id && location.quantity > 0)?.branch_id ?? '';
    transferForm.to_branch_id = '';
    transferForm.catalog_item_variant_id = row.variant_id;
    transferForm.quantity = 1;
    transferForm.notes = '';
    transferOpen.value = true;
}

function openConvert(row) {
    activeRow.value = row;
    convertForm.clearErrors();
    convertForm.branch_id = row.locations.find((location) => location.branch_id && location.quantity > 0)?.branch_id ?? '';
    convertForm.from_catalog_item_variant_id = row.variant_id;
    convertForm.to_catalog_item_variant_id = '';
    convertForm.quantity = 1;
    convertForm.pieces_per_unit = 1;
    convertForm.notes = '';
    convertOpen.value = true;
}

function submitTransfer() {
    transferForm.post('/backoffice/inventory/transfers', {
        preserveScroll: true,
        onSuccess: () => {
            transferOpen.value = false;
            transferForm.reset();
            activeRow.value = null;
        },
    });
}

function submitConvert() {
    convertForm.post('/backoffice/inventory/conversions', {
        preserveScroll: true,
        onSuccess: () => {
            convertOpen.value = false;
            convertForm.reset();
            activeRow.value = null;
        },
    });
}
</script>

<template>
    <Head title="Inventory" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="grid gap-4 sm:grid-cols-2" aria-label="Inventory totals">
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Total stock</p>
                <p class="text-3xl font-medium">{{ summary.quantity }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">Units across all locations</p>
            </article>
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Inventory value</p>
                <p class="text-3xl font-medium">{{ summary.value }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">At latest purchase price</p>
            </article>
        </section>

        <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="applyFilters">
            <input
                v-model="filters.search"
                class="h-11 flex-1 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                placeholder="Search by product, SKU, or barcode"
                type="text"
            />
            <select
                v-model="filters.branch_id"
                class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
            >
                <option value="">All locations</option>
                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
            </select>
            <button class="h-11 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15" type="submit">
                Filter
            </button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[64rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="w-10 px-4 py-3 font-medium"><span class="sr-only">Expand</span></th>
                        <th class="px-4 py-3 font-medium">Product</th>
                        <th class="px-4 py-3 font-medium">Variant</th>
                        <th class="px-4 py-3 font-medium">SKU</th>
                        <th class="px-4 py-3 font-medium">Qty</th>
                        <th class="px-4 py-3 font-medium">Purchase Price</th>
                        <th class="px-4 py-3 font-medium">Retail Price</th>
                        <th class="px-4 py-3 font-medium">Markup</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="9">
                            No stock yet. Receive a purchase order, then assign it to a branch.
                        </td>
                    </tr>
                    <template v-for="row in rows" :key="row.id">
                        <tr class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="px-2 py-3">
                                <button
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-ink/60 hover:bg-mist/70 dark:text-foam/60 dark:hover:bg-white/10"
                                    type="button"
                                    :aria-expanded="isExpanded(row.id)"
                                    :aria-label="isExpanded(row.id) ? 'Hide supply details' : 'Show supply details'"
                                    @click="toggleRow(row.id)"
                                >
                                    <span
                                        class="inline-block text-xs transition-transform"
                                        :class="isExpanded(row.id) ? 'rotate-90' : ''"
                                    >
                                        ▶
                                    </span>
                                </button>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ row.product }}</td>
                            <td class="px-4 py-3">{{ row.variant }}</td>
                            <td class="px-4 py-3">{{ row.sku || '—' }}</td>
                            <td class="px-4 py-3 font-medium tabular-nums">{{ row.total_quantity }}</td>
                            <td class="px-4 py-3">{{ row.purchase_price }}</td>
                            <td class="px-4 py-3">{{ row.retail_price }}</td>
                            <td class="px-4 py-3">{{ row.markup }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        class="inline-flex h-9 items-center rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                                        :href="`/backoffice/inventory/products/${row.variant_id}`"
                                    >
                                        View
                                    </Link>
                                    <template v-if="row.can_move">
                                        <button
                                            class="h-9 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                                            type="button"
                                            @click="openTransfer(row)"
                                        >
                                            Stock Transfer
                                        </button>
                                        <button
                                            class="h-9 rounded-xl bg-pine px-3 text-sm font-medium text-foam"
                                            type="button"
                                            @click="openConvert(row)"
                                        >
                                            Convert Stock
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="isExpanded(row.id)" class="border-b border-mist bg-mist/40 dark:border-white/10 dark:bg-white/5">
                            <td class="px-4 py-4" colspan="9">
                                <p class="mb-3 text-xs font-medium tracking-wide text-ink/50 uppercase dark:text-foam/50">
                                    Supply details
                                </p>
                                <table class="w-full min-w-[24rem] text-left text-sm">
                                    <thead class="text-ink/50 dark:text-foam/50">
                                        <tr>
                                            <th class="pb-2 pr-4 font-medium">Location</th>
                                            <th class="pb-2 pr-4 font-medium">Quantity</th>
                                            <th class="pb-2 font-medium">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="location in row.locations" :key="location.id">
                                            <td class="py-1.5 pr-4">{{ location.name }}</td>
                                            <td class="py-1.5 pr-4 tabular-nums">{{ location.quantity }}</td>
                                            <td class="py-1.5">{{ location.price }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="transferOpen && activeRow" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-lg flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">Stock Transfer</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">
                        {{ activeRow.product }}{{ activeRow.variant !== 'Default' ? ` · ${activeRow.variant}` : '' }}
                    </p>
                </div>
                <button class="text-sm font-medium" type="button" @click="transferOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitTransfer">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">From</label>
                        <select
                            v-model="transferForm.from_branch_id"
                            class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            required
                        >
                            <option value="">Choose branch</option>
                            <option
                                v-for="location in transferSourceBranches"
                                :key="`from-${location.branch_id}`"
                                :value="location.branch_id"
                            >
                                {{ location.name }} ({{ location.quantity }})
                            </option>
                        </select>
                        <p v-if="transferForm.errors.from_branch_id" class="text-sm text-danger">{{ transferForm.errors.from_branch_id }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">To</label>
                        <select
                            v-model="transferForm.to_branch_id"
                            class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            required
                        >
                            <option value="">Choose branch</option>
                            <option v-for="branch in branches" :key="`to-${branch.id}`" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                        <p v-if="transferForm.errors.to_branch_id" class="text-sm text-danger">{{ transferForm.errors.to_branch_id }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Quantity</label>
                    <input
                        v-model="transferForm.quantity"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="1"
                        required
                        type="number"
                    />
                    <p v-if="transferForm.errors.quantity" class="text-sm text-danger">{{ transferForm.errors.quantity }}</p>
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="transferForm.processing"
                >
                    {{ transferForm.processing ? 'Transferring…' : 'Transfer' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="convertOpen && activeRow" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-lg flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">Convert Stock</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">
                        {{ activeRow.product }}{{ activeRow.variant !== 'Default' ? ` · ${activeRow.variant}` : '' }}
                    </p>
                </div>
                <button class="text-sm font-medium" type="button" @click="convertOpen = false">Close</button>
            </div>
            <p class="text-sm text-ink/65 dark:text-foam/65">
                Convert bulk units into pieces of another product. Example: 1 sack of dry food (7kg) becomes 7 packs of repacked dry food (1kg).
            </p>
            <form class="flex flex-col gap-4" @submit.prevent="submitConvert">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Location</label>
                    <select
                        v-model="convertForm.branch_id"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                    >
                        <option value="">Choose location</option>
                        <option
                            v-for="location in convertSourceBranches"
                            :key="`convert-${location.branch_id}`"
                            :value="location.branch_id"
                        >
                            {{ location.name }} ({{ location.quantity }})
                        </option>
                    </select>
                    <p v-if="convertForm.errors.branch_id" class="text-sm text-danger">{{ convertForm.errors.branch_id }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="convert-to">Convert into</label>
                    <SearchableSelect
                        id="convert-to"
                        v-model="convertForm.to_catalog_item_variant_id"
                        :options="convertTargets"
                        placeholder="Search product"
                    />
                    <p v-if="convertForm.errors.to_catalog_item_variant_id" class="text-sm text-danger">
                        {{ convertForm.errors.to_catalog_item_variant_id }}
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Quantity to convert</label>
                        <input
                            v-model="convertForm.quantity"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="1"
                            required
                            type="number"
                        />
                        <p v-if="convertForm.errors.quantity" class="text-sm text-danger">{{ convertForm.errors.quantity }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Pieces per unit</label>
                        <input
                            v-model="convertForm.pieces_per_unit"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="1"
                            required
                            type="number"
                        />
                        <p v-if="convertForm.errors.pieces_per_unit" class="text-sm text-danger">{{ convertForm.errors.pieces_per_unit }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-1 rounded-xl bg-mist/70 px-4 py-3 dark:bg-white/5">
                    <p class="text-sm font-medium">Purchase price</p>
                    <p v-if="sourcePurchasePrice !== null && convertedPurchasePrice !== null" class="text-sm text-ink/70 dark:text-foam/70">
                        {{ formatPeso(sourcePurchasePrice) }} ÷ {{ convertForm.pieces_per_unit || 0 }} pieces =
                        <span class="font-medium text-ink dark:text-foam">{{ formatPeso(convertedPurchasePrice) }}</span>
                        per piece
                    </p>
                    <p v-else class="text-sm text-ink/60 dark:text-foam/60">
                        This product has no purchase price yet. Receive it on a purchase order first.
                    </p>
                </div>
                <p class="text-sm text-ink/60 dark:text-foam/60">Creates {{ convertedPieces }} piece{{ convertedPieces === 1 ? '' : 's' }}.</p>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="convertForm.processing"
                >
                    {{ convertForm.processing ? 'Converting…' : 'Convert' }}
                </button>
            </form>
        </div>
    </div>
</template>
