<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FilePicker from './FilePicker.vue';
import SearchableSelect from './SearchableSelect.vue';
import { formatPeso } from '../formatPeso';

const props = defineProps({
    suppliers: {
        type: Array,
        required: true,
    },
    variants: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    brands: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    purchaseOrder: {
        type: Object,
        default: null,
    },
    submitUrl: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'post',
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const productOpen = ref(false);
const categoryModalOpen = ref(false);
const brandModalOpen = ref(false);
const brandPreview = ref(null);

const form = useForm({
    supplier_id: props.purchaseOrder?.supplier_id ?? '',
    branch_id: props.purchaseOrder?.branch_id ?? '',
    notes: props.purchaseOrder?.notes ?? '',
    items: props.purchaseOrder?.items?.length
        ? props.purchaseOrder.items.map((item) => ({
              catalog_item_variant_id: item.catalog_item_variant_id,
              quantity: item.quantity,
              unit_cost: item.unit_cost,
          }))
        : [{ catalog_item_variant_id: '', quantity: 1, unit_cost: '' }],
});

const productForm = useForm({
    name: '',
    category_name: '',
    brand_name: '',
    sku: '',
    barcode: '',
    selling_price: '',
    unit_cost: '',
});

const posCategories = computed(() => page.props.posCategories ?? []);
const categoryForm = useForm({
    name: '',
    pos_category_id: '',
});

const brandForm = useForm({
    name: '',
    image: null,
});
const brandFileName = computed(() => brandForm.image?.name ?? '');

const selectedSupplier = computed(() =>
    props.suppliers.find((supplier) => String(supplier.id) === String(form.supplier_id)),
);

const selectedBranch = computed(() => props.branches.find((branch) => String(branch.id) === String(form.branch_id)));

const totalQuantity = computed(() =>
    form.items.reduce((total, item) => total + Number(item.quantity || 0), 0),
);

const totalAmount = computed(() =>
    form.items.reduce((total, item) => total + Number(item.quantity || 0) * Number(item.unit_cost || 0), 0),
);

const variantOptions = computed(() => {
    const extras = [];

    if (flash.value.new_variant && !props.variants.some((variant) => variant.id === flash.value.new_variant.id)) {
        extras.push({ id: flash.value.new_variant.id, label: flash.value.new_variant.label, sku: null });
    }

    return [...extras, ...props.variants];
});

function addItem() {
    form.items.push({ catalog_item_variant_id: '', quantity: 1, unit_cost: '' });
}

function removeItem(index) {
    if (form.items.length === 1) {
        return;
    }

    form.items.splice(index, 1);
}

function submit() {
    form.submit(props.method, props.submitUrl);
}

function openProduct() {
    productForm.reset();
    productForm.clearErrors();
    productOpen.value = true;
}

function openCategoryModal() {
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryModalOpen.value = true;
}

function submitCategory() {
    categoryForm.post('/backoffice/shop/categories', {
        preserveScroll: true,
        preserveState: true,
    });
}

function openBrandModal() {
    brandForm.reset();
    brandForm.clearErrors();
    brandPreview.value = null;
    brandModalOpen.value = true;
}

function onBrandImageChange(event) {
    const file = event.target.files[0] ?? null;
    brandForm.image = file;
    brandPreview.value = file ? URL.createObjectURL(file) : null;
}

function submitBrand() {
    brandForm.post('/backoffice/shop/brands', {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
    });
}

function submitProduct() {
    productForm.post('/backoffice/inventory/purchase-orders/products', {
        preserveScroll: true,
        onSuccess: () => {
            productOpen.value = false;
            productForm.reset();
        },
    });
}

watch(
    () => flash.value.created_category,
    (name) => {
        if (!name) {
            return;
        }

        productForm.category_name = name;
        categoryModalOpen.value = false;
        categoryForm.reset();
    },
);

watch(
    () => flash.value.created_brand,
    (name) => {
        if (!name) {
            return;
        }

        productForm.brand_name = name;
        brandModalOpen.value = false;
        brandForm.reset();
        brandPreview.value = null;
    },
);

watch(
    () => flash.value.new_variant,
    (variant) => {
        if (!variant) {
            return;
        }

        const empty = form.items.find((item) => item.catalog_item_variant_id === '');

        if (empty) {
            empty.catalog_item_variant_id = variant.id;
            empty.unit_cost = variant.unit_cost;
            return;
        }

        form.items.push({
            catalog_item_variant_id: variant.id,
            quantity: 1,
            unit_cost: variant.unit_cost,
        });
    },
);
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="po-supplier">Supplier</label>
                <select
                    id="po-supplier"
                    v-model="form.supplier_id"
                    class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    required
                >
                    <option value="">Choose supplier</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                        {{ supplier.name }}
                    </option>
                </select>
                <p v-if="form.errors.supplier_id" class="text-sm text-danger">{{ form.errors.supplier_id }}</p>
                <div v-if="selectedSupplier" class="rounded-xl border border-mist px-4 py-3 text-sm dark:border-white/10">
                    <p class="font-medium">{{ selectedSupplier.name }}</p>
                    <p v-if="selectedSupplier.code" class="text-ink/70 dark:text-foam/70">{{ selectedSupplier.code }}</p>
                    <p v-if="selectedSupplier.contact_name" class="text-ink/70 dark:text-foam/70">
                        {{ selectedSupplier.contact_name }}
                    </p>
                    <p v-if="selectedSupplier.email" class="text-ink/70 dark:text-foam/70">{{ selectedSupplier.email }}</p>
                    <p v-if="selectedSupplier.phone" class="text-ink/70 dark:text-foam/70">{{ selectedSupplier.phone }}</p>
                    <p v-if="selectedSupplier.address" class="text-ink/70 dark:text-foam/70">{{ selectedSupplier.address }}</p>
                </div>
                <p v-if="suppliers.length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                    Add a supplier first in Inventory Management → Suppliers.
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="po-branch">Business / branch</label>
                <select
                    id="po-branch"
                    v-model="form.branch_id"
                    class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    required
                >
                    <option value="">Choose branch</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                        {{ branch.name }}
                    </option>
                </select>
                <p v-if="form.errors.branch_id" class="text-sm text-danger">{{ form.errors.branch_id }}</p>
                <div v-if="selectedBranch" class="rounded-xl border border-mist px-4 py-3 text-sm dark:border-white/10">
                    <p class="font-medium">{{ selectedBranch.registered_business_name || selectedBranch.name }}</p>
                    <p v-if="selectedBranch.address" class="text-ink/70 dark:text-foam/70">{{ selectedBranch.address }}</p>
                    <p v-if="selectedBranch.tin" class="text-ink/70 dark:text-foam/70">TIN {{ selectedBranch.tin }}</p>
                </div>
                <p v-if="branches.length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                    Add a branch first in Shop Management → Branches, including TIN and registered business name.
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-medium">Products</h2>
                <div class="flex flex-wrap gap-3">
                    <button
                        class="h-10 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                        type="button"
                        @click="openProduct"
                    >
                        New product
                    </button>
                    <button
                        class="h-10 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                        type="button"
                        @click="addItem"
                    >
                        Add line
                    </button>
                </div>
            </div>
            <p v-if="form.errors.items" class="text-sm text-danger">{{ form.errors.items }}</p>

            <div
                v-for="(item, index) in form.items"
                :key="index"
                class="grid gap-3 rounded-2xl border border-mist p-4 md:grid-cols-[1fr_8rem_8rem_auto] dark:border-white/10"
            >
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" :for="`po-product-${index}`">Product</label>
                    <SearchableSelect
                        :id="`po-product-${index}`"
                        v-model="item.catalog_item_variant_id"
                        :options="variantOptions"
                        placeholder="Type a product name"
                    />
                    <p v-if="form.errors[`items.${index}.catalog_item_variant_id`]" class="text-sm text-danger">
                        {{ form.errors[`items.${index}.catalog_item_variant_id`] }}
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Qty</label>
                    <input
                        v-model="item.quantity"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="1"
                        required
                        type="number"
                    />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Unit cost</label>
                    <input
                        v-model="item.unit_cost"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                </div>
                <button
                    class="h-11 self-end rounded-xl border border-danger/30 bg-danger/10 px-4 text-sm font-medium text-danger disabled:opacity-40"
                    type="button"
                    :disabled="form.items.length === 1"
                    @click="removeItem(index)"
                >
                    Remove
                </button>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-6 rounded-2xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">
            <p>Total quantity <span class="font-medium">{{ totalQuantity }}</span></p>
            <p>Total amount <span class="font-medium">{{ formatPeso(totalAmount) }}</span></p>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium" for="po-notes">Notes</label>
            <textarea
                id="po-notes"
                v-model="form.notes"
                class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
            />
        </div>

        <div class="flex items-center gap-3">
            <button
                class="h-11 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Save' }}
            </button>
            <Link class="text-sm font-medium text-sand" href="/backoffice/inventory/purchase-orders">Cancel</Link>
        </div>
    </form>

    <div v-if="productOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-medium">Add product</h2>
                    <p class="text-sm text-ink/70 dark:text-foam/70">Creates a catalog item, then adds it to this PO.</p>
                </div>
                <button class="text-sm font-medium" type="button" @click="productOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitProduct">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="new-product-name">Name</label>
                    <input
                        id="new-product-name"
                        v-model="productForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="productForm.errors.name" class="text-sm text-danger">{{ productForm.errors.name }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="new-product-category">Category</label>
                        <div class="flex gap-2">
                            <select
                                id="new-product-category"
                                v-model="productForm.category_name"
                                class="h-11 min-w-0 flex-1 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                                required
                            >
                                <option value="">Select category</option>
                                <option v-for="category in categories" :key="category" :value="category">
                                    {{ category }}
                                </option>
                            </select>
                            <button
                                class="h-11 shrink-0 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                                type="button"
                                @click="openCategoryModal"
                            >
                                New
                            </button>
                        </div>
                        <p v-if="productForm.errors.category_name" class="text-sm text-danger">{{ productForm.errors.category_name }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="new-product-brand">Brand</label>
                        <div class="flex gap-2">
                            <select
                                id="new-product-brand"
                                v-model="productForm.brand_name"
                                class="h-11 min-w-0 flex-1 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            >
                                <option value="">No brand</option>
                                <option v-for="brand in brands" :key="brand" :value="brand">
                                    {{ brand }}
                                </option>
                            </select>
                            <button
                                class="h-11 shrink-0 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                                type="button"
                                @click="openBrandModal"
                            >
                                New
                            </button>
                        </div>
                        <p v-if="productForm.errors.brand_name" class="text-sm text-danger">{{ productForm.errors.brand_name }}</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="new-product-sku">SKU</label>
                        <input
                            id="new-product-sku"
                            v-model="productForm.sku"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            type="text"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="new-product-barcode">Barcode</label>
                        <input
                            id="new-product-barcode"
                            v-model="productForm.barcode"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            type="text"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="new-product-cost">Unit cost</label>
                        <input
                            id="new-product-cost"
                            v-model="productForm.unit_cost"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="0"
                            required
                            step="0.01"
                            type="number"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="new-product-price">Selling price</label>
                        <input
                            id="new-product-price"
                            v-model="productForm.selling_price"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="0"
                            required
                            step="0.01"
                            type="number"
                        />
                    </div>
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="productForm.processing"
                >
                    {{ productForm.processing ? 'Adding…' : 'Add to catalog' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="categoryModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Add category</h2>
                <button class="text-sm font-medium" type="button" @click="categoryModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitCategory">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="po-new-category-name">Name</label>
                    <input
                        id="po-new-category-name"
                        v-model="categoryForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="categoryForm.errors.name" class="text-sm text-danger">{{ categoryForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="po-new-category-pos">POS category</label>
                    <select
                        id="po-new-category-pos"
                        v-model="categoryForm.pos_category_id"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                    >
                        <option value="" disabled>Choose a POS category</option>
                        <option v-for="posCategory in posCategories" :key="posCategory.id" :value="posCategory.id">
                            {{ posCategory.name }}
                        </option>
                    </select>
                    <p v-if="categoryForm.errors.pos_category_id" class="text-sm text-danger">
                        {{ categoryForm.errors.pos_category_id }}
                    </p>
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="categoryForm.processing"
                >
                    {{ categoryForm.processing ? 'Saving…' : 'Add category' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="brandModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Add brand</h2>
                <button class="text-sm font-medium" type="button" @click="brandModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitBrand">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="po-new-brand-name">Name</label>
                    <input
                        id="po-new-brand-name"
                        v-model="brandForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="brandForm.errors.name" class="text-sm text-danger">{{ brandForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium">Logo or image</p>
                    <FilePicker
                        id="po-new-brand-image"
                        button-label="Choose image"
                        :file-name="brandFileName"
                        hint="JPG, PNG, or WebP · no file chosen"
                        @change="onBrandImageChange"
                    />
                    <p v-if="brandForm.errors.image" class="text-sm text-danger">{{ brandForm.errors.image }}</p>
                    <img v-if="brandPreview" :src="brandPreview" alt="" class="h-16 w-16 rounded-xl object-cover" />
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="brandForm.processing"
                >
                    {{ brandForm.processing ? 'Saving…' : 'Add brand' }}
                </button>
            </form>
        </div>
    </div>
</template>
