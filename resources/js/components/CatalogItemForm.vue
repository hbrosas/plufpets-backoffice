<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BarcodeInput from './BarcodeInput.vue';
import FilePicker from './FilePicker.vue';

const props = defineProps({
    types: {
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
    modifiers: {
        type: Array,
        default: () => [],
    },
    item: {
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

function emptyBranchPrices(existing = []) {
    const current = Object.fromEntries((existing ?? []).map((price) => [price.branch_id, price.price]));

    return props.branches.map((branch) => ({
        branch_id: branch.id,
        price: current[branch.id] ?? '',
    }));
}

const emptyVariant = () => ({
    name: 'Default',
    sku: '',
    barcode: '',
    image: null,
    remove_image: false,
    preview: null,
    price: '',
    is_active: true,
    branch_prices: emptyBranchPrices(),
});

const form = useForm({
    ...(props.method === 'put' ? { _method: 'put' } : {}),
    type: props.item?.type ?? 'product',
    name: props.item?.name ?? '',
    category_name: props.item?.category_name ?? '',
    brand_name: props.item?.brand_name ?? '',
    description: props.item?.description ?? '',
    is_active: props.item?.is_active ?? true,
    has_branch_pricing: props.item?.has_branch_pricing ?? false,
    modifier_ids: (props.item?.modifier_ids ?? []).map(Number),
    modifier_prices: [],
    variants: props.item?.variants?.length
        ? props.item.variants.map((variant) => ({
              id: variant.id,
              name: variant.name,
              sku: variant.sku ?? '',
              barcode: variant.barcode ?? '',
              image: null,
              remove_image: false,
              preview: variant.image_url ?? null,
              price: variant.price,
              is_active: variant.is_active,
              branch_prices: emptyBranchPrices(variant.branch_prices),
          }))
        : [emptyVariant()],
});

form.transform((data) => {
    const variants = data.variants.map(({ preview, image, ...fields }) =>
        image instanceof File ? { ...fields, image } : fields,
    );

    return {
        ...data,
        variants,
    };
});

const page = usePage();
const isProduct = computed(() => form.type === 'product');
const categoryModalOpen = ref(false);
const brandModalOpen = ref(false);
const brandPreview = ref(null);

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

watch(
    () => page.props.flash?.created_category,
    (name) => {
        if (!name) {
            return;
        }

        form.category_name = name;
        categoryModalOpen.value = false;
        categoryForm.reset();
    },
);

watch(
    () => page.props.flash?.created_brand,
    (name) => {
        if (!name) {
            return;
        }

        form.brand_name = name;
        brandModalOpen.value = false;
        brandForm.reset();
        brandPreview.value = null;
    },
);

function variantImageHint(variant) {
    if (variant.preview && !variant.image) {
        return 'Using current image';
    }

    return 'JPG, PNG, or WebP · no file chosen';
}

function onVariantImageChange(index, event) {
    const file = event.target.files[0] ?? null;
    const variant = form.variants[index];

    variant.image = file;
    variant.remove_image = false;
    variant.preview = file ? URL.createObjectURL(file) : (props.item?.variants?.[index]?.image_url ?? null);
}

function clearVariantImage(index) {
    const variant = form.variants[index];

    variant.image = null;
    variant.remove_image = true;
    variant.preview = null;
}

function addVariant() {
    form.variants.push({
        name: '',
        sku: '',
        barcode: '',
        image: null,
        remove_image: false,
        preview: null,
        price: '',
        is_active: true,
        branch_prices: emptyBranchPrices(),
    });
}

function existingOptionPrice(modifierId, option) {
    return (props.item?.modifier_prices ?? []).find(
        (price) => Number(price.modifier_id) === Number(modifierId) && price.option === option,
    );
}

function makeOptionPrice(modifierId, option, existing = null) {
    return {
        modifier_id: Number(modifierId),
        option,
        price: existing?.price ?? '0.00',
        branch_prices: emptyBranchPrices(existing?.branch_prices),
    };
}

function syncModifierPrices(modifierIds) {
    const rows = [];

    modifierIds.forEach((id) => {
        const modifier = props.modifiers.find((item) => Number(item.id) === Number(id));

        (modifier?.options ?? []).forEach((option) => {
            const current = form.modifier_prices.find(
                (price) => Number(price.modifier_id) === Number(id) && price.option === option,
            );

            rows.push(current ?? makeOptionPrice(id, option, existingOptionPrice(id, option)));
        });
    });

    form.modifier_prices = rows;
}

syncModifierPrices(form.modifier_ids);

function isModifierEnabled(id) {
    return form.modifier_ids.map(Number).includes(Number(id));
}

function toggleModifier(id) {
    const value = Number(id);
    const current = form.modifier_ids.map(Number);

    if (current.includes(value)) {
        form.modifier_ids = current.filter((item) => item !== value);
        syncModifierPrices(form.modifier_ids);
        return;
    }

    form.modifier_ids = [...current, value];
    syncModifierPrices(form.modifier_ids);
}

function optionPrice(modifierId, option) {
    return form.modifier_prices.find(
        (price) => Number(price.modifier_id) === Number(modifierId) && price.option === option,
    );
}

function optionPriceError(modifierId, option, field) {
    const index = form.modifier_prices.findIndex(
        (price) => Number(price.modifier_id) === Number(modifierId) && price.option === option,
    );

    if (index < 0) {
        return null;
    }

    return form.errors[`modifier_prices.${index}.${field}`];
}

function optionBranchPriceError(modifierId, option, priceIndex) {
    const index = form.modifier_prices.findIndex(
        (price) => Number(price.modifier_id) === Number(modifierId) && price.option === option,
    );

    if (index < 0) {
        return null;
    }

    return form.errors[`modifier_prices.${index}.branch_prices.${priceIndex}.price`];
}

function branchName(branchId) {
    return props.branches.find((branch) => branch.id === branchId)?.name ?? 'Branch';
}

function branchPriceError(variantIndex, priceIndex) {
    return form.errors[`variants.${variantIndex}.branch_prices.${priceIndex}.price`];
}

function removeVariant(index) {
    if (form.variants.length === 1) {
        return;
    }

    form.variants.splice(index, 1);
}

function variantError(index, field) {
    return form.errors[`variants.${index}.${field}`];
}

function submit() {
    const hasFiles = form.variants.some((variant) => variant.image instanceof File);
    const options = {
        preserveScroll: true,
        forceFormData: hasFiles,
    };

    if (props.method === 'put' && hasFiles) {
        form.post(props.submitUrl, options);
        return;
    }

    if (props.method === 'put') {
        form.put(props.submitUrl, options);
        return;
    }

    form.post(props.submitUrl, options);
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
</script>

<template>
    <form class="flex max-w-4xl flex-col gap-6" @submit.prevent="submit">
        <section class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">Step 1</p>
                <h2 class="text-lg font-medium">Item details</h2>
                <p class="text-sm text-ink/70 dark:text-foam/70">Start with the type, name, and a short description.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="type">Type</label>
                    <select
                        id="type"
                        v-model="form.type"
                        class="h-12 rounded-xl border border-mist bg-white pr-11 pl-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                        required
                    >
                        <option v-for="type in types" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.type" class="text-sm text-danger">{{ form.errors.type }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="name">Name</label>
                    <input
                        id="name"
                        v-model="form.name"
                        class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="description">Description</label>
                <textarea
                    id="description"
                    v-model="form.description"
                    class="min-h-28 rounded-xl border border-mist bg-white px-4 py-3 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                />
                <p v-if="form.errors.description" class="text-sm text-danger">{{ form.errors.description }}</p>
            </div>

            <label class="flex items-center gap-3 text-sm font-medium">
                <input v-model="form.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                Active in the shop
            </label>
            <p v-if="form.errors.is_active" class="text-sm text-danger">{{ form.errors.is_active }}</p>
        </section>

        <section class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">Step 2</p>
                <h2 class="text-lg font-medium">Category and brand</h2>
                <p class="text-sm text-ink/70 dark:text-foam/70">
                    {{ isProduct ? 'Products need a category. Brand is optional.' : 'Optional for services. Use New if the option is not in the list.' }}
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="category_name">Category</label>
                    <div class="flex gap-2">
                        <select
                            id="category_name"
                            v-model="form.category_name"
                            class="h-12 min-w-0 flex-1 rounded-xl border border-mist bg-white pr-11 pl-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                            :required="isProduct"
                        >
                            <option value="">Select category</option>
                            <option v-for="category in categories" :key="category" :value="category">
                                {{ category }}
                            </option>
                        </select>
                        <button
                            class="h-12 shrink-0 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                            type="button"
                            @click="openCategoryModal"
                        >
                            New
                        </button>
                    </div>
                    <p v-if="form.errors.category_name" class="text-sm text-danger">{{ form.errors.category_name }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="brand_name">Brand</label>
                    <div class="flex gap-2">
                        <select
                            id="brand_name"
                            v-model="form.brand_name"
                            class="h-12 min-w-0 flex-1 rounded-xl border border-mist bg-white pr-11 pl-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                        >
                            <option value="">No brand</option>
                            <option v-for="brand in brands" :key="brand" :value="brand">
                                {{ brand }}
                            </option>
                        </select>
                        <button
                            class="h-12 shrink-0 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
                            type="button"
                            @click="openBrandModal"
                        >
                            New
                        </button>
                    </div>
                    <p v-if="form.errors.brand_name" class="text-sm text-danger">{{ form.errors.brand_name }}</p>
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">Step 3</p>
                    <h2 class="text-lg font-medium">Pricing and variants</h2>
                    <p class="text-sm text-ink/70 dark:text-foam/70">
                        Use one variant for a single price, or add more for sizes, packs, or service options. Click a barcode field, then scan. Enter from the scanner will not save the form.
                    </p>
                    <label class="mt-2 flex items-center gap-3 text-sm font-medium">
                        <input v-model="form.has_branch_pricing" class="h-4 w-4 accent-pine" type="checkbox" />
                        Prices can differ by branch
                    </label>
                    <p class="text-sm text-ink/70 dark:text-foam/70">
                        When this is on, each branch can use the default price or its own price.
                    </p>
                </div>
                <button
                    class="h-11 shrink-0 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                    type="button"
                    @click="addVariant"
                >
                    Add variant
                </button>
            </div>
            <p v-if="form.errors.variants" class="text-sm text-danger">{{ form.errors.variants }}</p>

            <div
                v-for="(variant, index) in form.variants"
                :key="variant.id ?? `new-${index}`"
                class="grid gap-3 rounded-2xl border border-mist p-4 sm:grid-cols-2 dark:border-white/10"
            >
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" :for="`variant-name-${index}`">Variant name</label>
                    <input
                        :id="`variant-name-${index}`"
                        v-model="variant.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none transition focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="variantError(index, 'name')" class="text-sm text-danger">{{ variantError(index, 'name') }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" :for="`variant-sku-${index}`">SKU</label>
                    <input
                        :id="`variant-sku-${index}`"
                        v-model="variant.sku"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none transition focus:border-pine dark:border-white/10 dark:bg-white/5"
                        type="text"
                    />
                    <p v-if="variantError(index, 'sku')" class="text-sm text-danger">{{ variantError(index, 'sku') }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" :for="`variant-barcode-${index}`">Barcode</label>
                    <BarcodeInput
                        :id="`variant-barcode-${index}`"
                        v-model="variant.barcode"
                        placeholder="Scan barcode"
                    />
                    <p v-if="variantError(index, 'barcode')" class="text-sm text-danger">{{ variantError(index, 'barcode') }}</p>
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <p class="text-sm font-medium">Photo</p>
                    <FilePicker
                        :id="`variant-image-${index}`"
                        button-label="Choose image"
                        :file-name="variant.image?.name ?? ''"
                        :hint="variantImageHint(variant)"
                        @change="onVariantImageChange(index, $event)"
                    />
                    <p v-if="variantError(index, 'image')" class="text-sm text-danger">{{ variantError(index, 'image') }}</p>
                    <div v-if="variant.preview" class="flex items-center gap-4">
                        <img :src="variant.preview" alt="" class="h-20 w-20 rounded-xl object-cover" />
                        <button class="text-sm font-medium text-danger" type="button" @click="clearVariantImage(index)">
                            Remove image
                        </button>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" :for="`variant-price-${index}`">
                        {{ form.has_branch_pricing ? 'Default price' : 'Price' }}
                    </label>
                    <input
                        :id="`variant-price-${index}`"
                        v-model="variant.price"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none transition focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                    <p v-if="variantError(index, 'price')" class="text-sm text-danger">{{ variantError(index, 'price') }}</p>
                </div>
                <div class="flex items-end justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="variant.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                        Active
                    </label>
                    <button
                        class="h-11 rounded-xl border border-danger/30 bg-danger/10 px-4 text-sm font-medium text-danger disabled:cursor-not-allowed disabled:opacity-40"
                        type="button"
                        :disabled="form.variants.length === 1"
                        @click="removeVariant(index)"
                    >
                        Remove
                    </button>
                </div>
                <div
                    v-if="form.has_branch_pricing && variant.branch_prices.length > 0"
                    class="flex flex-col gap-3 sm:col-span-2"
                >
                    <p class="text-sm font-medium">Branch prices</p>
                    <p class="text-xs text-ink/60 dark:text-foam/60">Leave a branch blank to use the default price.</p>
                    <div
                        v-for="(branchPrice, priceIndex) in variant.branch_prices"
                        :key="`${index}-${branchPrice.branch_id}`"
                        class="grid gap-2 sm:grid-cols-[1fr_8rem] sm:items-center"
                    >
                        <label class="text-sm" :for="`variant-${index}-branch-${branchPrice.branch_id}`">
                            {{ branchName(branchPrice.branch_id) }}
                        </label>
                        <input
                            :id="`variant-${index}-branch-${branchPrice.branch_id}`"
                            v-model="branchPrice.price"
                            class="h-11 rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="0"
                            step="0.01"
                            type="number"
                            :placeholder="variant.price || 'Default'"
                        />
                        <p
                            v-if="branchPriceError(index, priceIndex)"
                            class="text-sm text-danger sm:col-span-2"
                        >
                            {{ branchPriceError(index, priceIndex) }}
                        </p>
                    </div>
                </div>
                <p
                    v-else-if="form.has_branch_pricing && variant.branch_prices.length === 0"
                    class="text-sm text-ink/60 sm:col-span-2 dark:text-foam/60"
                >
                    Add a branch first to set a different price there.
                </p>
            </div>
        </section>

        <section class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">Step 4</p>
                    <h2 class="text-lg font-medium">Modifiers</h2>
                    <p class="text-sm text-ink/70 dark:text-foam/70">
                        Enable a modifier, then set a price for each option. If branch pricing is on, each option can also have a different extra price per branch.
                    </p>
                </div>
                <Link
                    class="inline-flex h-11 shrink-0 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                    href="/backoffice/shop/modifiers"
                >
                    Manage modifiers
                </Link>
            </div>

            <p v-if="modifiers.length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                No modifiers yet. Add them on the Modifiers page, then enable them here.
            </p>

            <div
                v-for="modifier in modifiers"
                :key="modifier.id"
                class="flex flex-col gap-4 rounded-2xl border border-mist p-4 dark:border-white/10"
            >
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        class="mt-1 h-4 w-4 accent-pine"
                        type="checkbox"
                        :checked="isModifierEnabled(modifier.id)"
                        @change="toggleModifier(modifier.id)"
                    />
                    <span class="flex flex-col gap-1">
                        <span class="text-sm font-medium">{{ modifier.name }}</span>
                        <span class="text-sm text-ink/60 dark:text-foam/60">
                            {{ (modifier.options ?? []).join(' · ') || 'No options' }}
                            <span v-if="!modifier.is_active"> · Inactive</span>
                        </span>
                    </span>
                </label>

                <div v-if="isModifierEnabled(modifier.id)" class="flex flex-col gap-4 pl-7">
                    <p v-if="(modifier.options ?? []).length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                        This modifier has no options yet.
                    </p>
                    <div
                        v-for="option in modifier.options ?? []"
                        :key="`${modifier.id}-${option}`"
                        class="flex flex-col gap-3 rounded-xl border border-mist p-3 dark:border-white/10"
                    >
                        <div class="grid gap-3 sm:grid-cols-[1fr_8rem] sm:items-end">
                            <p class="text-sm font-medium">{{ option }}</p>
                            <div v-if="optionPrice(modifier.id, option)" class="flex flex-col gap-1">
                                <label class="text-xs text-ink/50 dark:text-foam/50" :for="`modifier-${modifier.id}-${option}-price`">
                                    {{ form.has_branch_pricing ? 'Default extra' : 'Extra price' }}
                                </label>
                                <input
                                    :id="`modifier-${modifier.id}-${option}-price`"
                                    v-model="optionPrice(modifier.id, option).price"
                                    class="h-11 rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/10 dark:bg-white/5"
                                    min="0"
                                    required
                                    step="0.01"
                                    type="number"
                                />
                                <p v-if="optionPriceError(modifier.id, option, 'price')" class="text-sm text-danger">
                                    {{ optionPriceError(modifier.id, option, 'price') }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="form.has_branch_pricing && optionPrice(modifier.id, option)?.branch_prices.length > 0"
                            class="flex flex-col gap-3"
                        >
                            <p class="text-xs text-ink/60 dark:text-foam/60">
                                Leave a branch blank to use the default extra price.
                            </p>
                            <div
                                v-for="(branchPrice, priceIndex) in optionPrice(modifier.id, option).branch_prices"
                                :key="`${modifier.id}-${option}-${branchPrice.branch_id}`"
                                class="grid gap-2 sm:grid-cols-[1fr_8rem] sm:items-center"
                            >
                                <label class="text-sm" :for="`modifier-${modifier.id}-${option}-branch-${branchPrice.branch_id}`">
                                    {{ branchName(branchPrice.branch_id) }}
                                </label>
                                <input
                                    :id="`modifier-${modifier.id}-${option}-branch-${branchPrice.branch_id}`"
                                    v-model="branchPrice.price"
                                    class="h-11 rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/10 dark:bg-white/5"
                                    min="0"
                                    step="0.01"
                                    type="number"
                                    :placeholder="optionPrice(modifier.id, option).price || 'Default'"
                                />
                                <p
                                    v-if="optionBranchPriceError(modifier.id, option, priceIndex)"
                                    class="text-sm text-danger sm:col-span-2"
                                >
                                    {{ optionBranchPriceError(modifier.id, option, priceIndex) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p v-if="form.errors.modifier_ids" class="text-sm text-danger">{{ form.errors.modifier_ids }}</p>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button
                class="h-12 rounded-xl bg-pine px-5 text-base font-medium text-foam transition hover:bg-pine/90 disabled:cursor-wait disabled:opacity-70"
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Save' }}
            </button>
            <Link class="text-sm font-medium text-pine dark:text-sand" href="/backoffice/shop/catalog">Cancel</Link>
        </div>
    </form>

    <div v-if="categoryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Add category</h2>
                <button class="text-sm font-medium" type="button" @click="categoryModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitCategory">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="new-category-name">Name</label>
                    <input
                        id="new-category-name"
                        v-model="categoryForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="categoryForm.errors.name" class="text-sm text-danger">{{ categoryForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="new-category-pos">POS category</label>
                    <select
                        id="new-category-pos"
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

    <div v-if="brandModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Add brand</h2>
                <button class="text-sm font-medium" type="button" @click="brandModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitBrand">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="new-brand-name">Name</label>
                    <input
                        id="new-brand-name"
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
                        id="new-brand-image"
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
