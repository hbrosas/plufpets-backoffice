<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { formatPeso } from '../../formatPeso';
import SiteLayout from '../../layouts/SiteLayout.vue';

defineOptions({
    layout: SiteLayout,
});

const props = defineProps({
    branches: {
        type: Array,
        required: true,
    },
    branch: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        required: true,
    },
    products: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    banner: {
        type: Object,
        default: null,
    },
    promotion: {
        type: Object,
        default: null,
    },
});

const query = ref('');
const filtersOpen = ref(false);
const sortOpen = ref(false);
const sort = ref('name-asc');
const draftCategories = ref([]);
const draftBrands = ref([]);
const draftStock = ref([]);
const appliedCategories = ref([]);
const appliedBrands = ref([]);
const appliedStock = ref([]);
const stockOptions = [
    { id: 'in', name: 'In Stock' },
    { id: 'out', name: 'Out of Stock' },
];
const promotionClosed = ref(readPromotionClosed());
const cartOpen = ref(false);
const cart = ref([]);
const addedNotice = ref('');
const logoMissing = ref(false);
const missingImages = ref({});
const missingBrandLogos = ref({});
let addedNoticeTimer = null;

const cartCount = computed(() => cart.value.reduce((total, line) => total + line.quantity, 0));
const cartTotal = computed(() => cart.value.reduce((total, line) => total + Number(line.price) * line.quantity, 0));
const categoryOptions = computed(() => optionsFrom(props.products, 'category_id', 'category_name'));
const brandOptions = computed(() => optionsFrom(props.products, 'brand_id', 'brand_name', 'brand_logo_url'));
const visibleProducts = computed(() => {
    const term = query.value.trim().toLowerCase();

    return [...props.products]
        .filter((product) => {
            if (term !== '') {
                const haystack = [product.name, product.sku, product.barcode].filter(Boolean).join(' ').toLowerCase();

                if (!haystack.includes(term)) {
                    return false;
                }
            }

            if (appliedCategories.value.length > 0 && !appliedCategories.value.includes(product.category_id ?? 'none')) {
                return false;
            }

            if (appliedBrands.value.length > 0 && !appliedBrands.value.includes(product.brand_id ?? 'none')) {
                return false;
            }

            if (appliedStock.value.length > 0 && !appliedStock.value.includes(product.in_stock ? 'in' : 'out')) {
                return false;
            }

            return true;
        })
        .sort((left, right) => {
            if (sort.value === 'price-asc') {
                return Number(left.price) - Number(right.price) || left.name.localeCompare(right.name);
            }

            if (sort.value === 'price-desc') {
                return Number(right.price) - Number(left.price) || left.name.localeCompare(right.name);
            }

            if (sort.value === 'name-desc') {
                return right.name.localeCompare(left.name);
            }

            return left.name.localeCompare(right.name);
        });
});
const filterCount = computed(() => appliedCategories.value.length + appliedBrands.value.length + appliedStock.value.length);
const sortLabel = computed(() => ({
    'name-asc': 'Name: A to Z',
    'name-desc': 'Name: Z to A',
    'price-desc': 'Price: High to low',
    'price-asc': 'Price: Low to high',
}[sort.value]));

watch(
    () => props.filters.branch,
    () => {
        cart.value = readCart();
        draftCategories.value = [];
        draftBrands.value = [];
        draftStock.value = [];
        appliedCategories.value = [];
        appliedBrands.value = [];
        appliedStock.value = [];
    },
    { immediate: true },
);

watch(
    cart,
    () => {
        writeCart();
    },
    { deep: true },
);

function readPromotionClosed() {
    try {
        return sessionStorage.getItem('shop-promotion-closed') === '1';
    } catch {
        return false;
    }
}

function closePromotion() {
    promotionClosed.value = true;
    sessionStorage.setItem('shop-promotion-closed', '1');
}

function storageKey() {
    return `shop-cart-${props.filters.branch ?? 'none'}`;
}

function readCart() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey()) || '[]');

        return Array.isArray(saved) ? saved : [];
    } catch {
        return [];
    }
}

function writeCart() {
    sessionStorage.setItem(storageKey(), JSON.stringify(cart.value));
}

function optionsFrom(products, idKey, nameKey, logoKey = null) {
    const options = new Map();

    products.forEach((product) => {
        const id = product[idKey];

        if (id === null || id === undefined || options.has(id)) {
            return;
        }

        options.set(id, {
            id,
            name: product[nameKey],
            logo: logoKey ? product[logoKey] : null,
        });
    });

    return [...options.values()].sort((left, right) => left.name.localeCompare(right.name));
}

function changeBranch(branchId) {
    router.get('/', { branch: branchId }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function toggleDraft(kind, id) {
    const lists = {
        category: draftCategories,
        brand: draftBrands,
        stock: draftStock,
    };
    const list = lists[kind];
    const selected = list.value.includes(id);

    list.value = selected ? list.value.filter((value) => value !== id) : [...list.value, id];
}

function toggleFilters() {
    if (filtersOpen.value) {
        filtersOpen.value = false;

        return;
    }

    draftCategories.value = [...appliedCategories.value];
    draftBrands.value = [...appliedBrands.value];
    draftStock.value = [...appliedStock.value];
    sortOpen.value = false;
    filtersOpen.value = true;
}

function applyFilters() {
    appliedCategories.value = [...draftCategories.value];
    appliedBrands.value = [...draftBrands.value];
    appliedStock.value = [...draftStock.value];
    filtersOpen.value = false;
}

function clearFilters() {
    draftCategories.value = [];
    draftBrands.value = [];
    draftStock.value = [];
    appliedCategories.value = [];
    appliedBrands.value = [];
    appliedStock.value = [];
}

function chooseSort(value) {
    sort.value = value;
    sortOpen.value = false;
}

function cartQuantity(productId) {
    return cart.value.find((line) => line.id === productId)?.quantity ?? 0;
}

function canPreorder(product) {
    return product.is_product && !product.in_stock && product.allows_preorder;
}

function quantityLimit(product) {
    if (!product.is_product || product.stock === null || canPreorder(product)) {
        return 99;
    }

    if (product.stock < 1) {
        return 0;
    }

    return product.stock;
}

function showAddedNotice(name) {
    addedNotice.value = `${name} added to cart.`;
    clearTimeout(addedNoticeTimer);
    addedNoticeTimer = setTimeout(() => {
        addedNotice.value = '';
    }, 2500);
}

function addProduct(product) {
    if (cartQuantity(product.id) > 0 || (product.is_product && !product.in_stock && !product.allows_preorder)) {
        return;
    }

    cart.value.push({
        id: product.id,
        name: product.name,
        price: product.price,
        price_label: product.price_label,
        image_url: product.image_url,
        quantity: 1,
        stock: product.stock,
        is_product: product.is_product,
        preorder: canPreorder(product),
    });
    showAddedNotice(product.name);
}

onUnmounted(() => {
    clearTimeout(addedNoticeTimer);
});

function lineQuantityLimit(line) {
    if (line.preorder || !line.is_product || line.stock === null) {
        return 99;
    }

    return line.stock;
}

function changeQuantity(line, next) {
    const quantity = Number(next);

    if (!Number.isInteger(quantity) || quantity < 1) {
        removeLine(line.id);

        return;
    }

    line.quantity = Math.min(quantity, lineQuantityLimit(line));
}

function changeProductQuantity(product, next) {
    const line = cart.value.find((item) => item.id === product.id);

    if (!line) {
        return;
    }

    changeQuantity(line, next);
}

function removeLine(productId) {
    cart.value = cart.value.filter((line) => line.id !== productId);
}

</script>

<template>
    <div>
        <div
            v-if="promotion && !promotionClosed"
            class="relative"
            :style="{ backgroundColor: promotion.background_color, color: promotion.text_color }"
        >
            <p class="mx-auto max-w-6xl px-12 py-3 text-center text-sm leading-5 break-words sm:text-base">
                {{ promotion.text }}
            </p>
            <button
                type="button"
                class="absolute top-1/2 right-3 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full"
                aria-label="Close"
                @click="closePromotion"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M5 5l10 10M15 5 5 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </button>
        </div>
        <header class="sticky top-0 z-30 border-b border-black/10 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-2 px-4 py-3">
                <div class="order-1 shrink-0 sm:order-none">
                    <img
                        v-if="$page.props.businessLogoUrl && !logoMissing"
                        :src="$page.props.businessLogoUrl"
                        alt=""
                        class="h-9 w-9 shrink-0 rounded-lg bg-white object-contain"
                        @error="logoMissing = true"
                    />
                    <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-ember text-sm font-semibold text-black">
                        PP
                    </span>
                </div>

                <div class="order-3 flex w-full min-w-0 items-center gap-2 sm:order-none sm:w-auto">
                    <label class="shrink-0 text-sm whitespace-nowrap text-black/70" for="shop-branch">You're ordering from</label>
                    <select
                        id="shop-branch"
                        class="h-10 min-w-0 flex-1 rounded-xl border border-black/15 bg-white px-3 text-sm text-black sm:w-40 sm:flex-none"
                        :value="filters.branch ?? ''"
                        :disabled="branches.length === 0"
                        @change="changeBranch(Number($event.target.value) || null)"
                    >
                        <option v-if="branches.length === 0" value="">No branches</option>
                        <option v-for="option in branches" :key="option.id" :value="option.id">
                            {{ option.name }}
                        </option>
                    </select>
                </div>

                <form class="relative order-4 w-full min-w-0 sm:order-none sm:w-auto sm:flex-1" @submit.prevent>
                    <label class="sr-only" for="shop-search">Search</label>
                    <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-black/50" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.5" />
                        <path d="M12.5 12.5 17 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    <input
                        id="shop-search"
                        v-model="query"
                        type="search"
                        placeholder="Search"
                        class="h-10 w-full rounded-xl border border-black/15 bg-white pr-3 pl-9 text-sm text-black placeholder:text-black/50"
                    />
                </form>

                <div class="order-2 ml-auto flex shrink-0 items-center gap-2 sm:order-none sm:ml-0">
                    <Link
                        href="/track"
                        class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-navy/20 bg-white px-2.5 text-sm font-medium whitespace-nowrap text-navy shadow-sm sm:px-3"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M3.5 6.5 10 3.5l6.5 3v7L10 16.5l-6.5-3v-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M3.5 6.5 10 9.5l6.5-3M10 9.5v7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Track Order
                    </Link>
                    <button
                        type="button"
                        class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl bg-navy text-white shadow-sm"
                        :aria-label="`Cart, ${cartCount}`"
                        @click="cartOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M3.5 5h13l-1.2 8.5a1 1 0 0 1-1 .8H6.2a1 1 0 0 1-1-.8L3.5 5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M3.5 5 2.5 2.5H1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            <circle cx="8" cy="16.5" r="1" fill="currentColor" />
                            <circle cx="13.5" cy="16.5" r="1" fill="currentColor" />
                        </svg>
                        <span class="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-xs font-medium text-navy">{{ cartCount }}</span>
                    </button>
                </div>
            </div>

            <div v-if="addedNotice" class="pointer-events-none absolute inset-x-0 top-full z-50">
                <div class="mx-auto flex max-w-6xl justify-end px-4">
                    <p
                        class="pointer-events-auto mt-2 w-fit max-w-[min(22rem,calc(100vw-2rem))] rounded-xl bg-navy px-4 py-3 text-sm text-white shadow-lg"
                        role="status"
                    >
                        {{ addedNotice }}
                    </p>
                </div>
            </div>
        </header>

        <section v-if="banner?.image_url" class="border-b border-black/10 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-3 sm:py-4">
                <img
                    :src="banner.image_url"
                    alt=""
                    class="block aspect-[1600/500] w-full rounded-2xl object-cover"
                />
            </div>
        </section>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <button v-if="filtersOpen || sortOpen" type="button" class="fixed inset-0 z-10" aria-label="Close menu" @click="filtersOpen = false; sortOpen = false" />

            <div class="relative z-20 mb-5 flex items-center gap-2">
                <div class="relative">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-black/15 bg-white px-3 text-sm font-medium text-black"
                        @click="toggleFilters"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M3 4.5h14L12 11v4.5L8 17.5V11L3 4.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        </svg>
                        Filters
                        <span v-if="filterCount > 0" class="rounded-full bg-ember px-2 py-0.5 text-xs">{{ filterCount }}</span>
                    </button>
                    <div v-if="filtersOpen" class="absolute left-0 z-20 mt-2 max-h-[70vh] w-[min(18rem,calc(100vw-2rem))] overflow-y-auto rounded-2xl border border-black/10 bg-white p-4 shadow-lg">
                        <p class="text-xs font-medium tracking-wide text-black/50 uppercase">Categories</p>
                        <p v-if="categoryOptions.length === 0" class="mt-2 text-sm text-black/60">No categories.</p>
                        <label v-for="category in categoryOptions" :key="category.id" class="mt-2 flex items-center gap-2 text-sm">
                            <input
                                class="h-4 w-4 accent-ember"
                                type="checkbox"
                                :checked="draftCategories.includes(category.id)"
                                @change="toggleDraft('category', category.id)"
                            />
                            {{ category.name }}
                        </label>

                        <p class="mt-4 text-xs font-medium tracking-wide text-black/50 uppercase">Brands</p>
                        <p v-if="brandOptions.length === 0" class="mt-2 text-sm text-black/60">No brands.</p>
                        <label v-for="brand in brandOptions" :key="brand.id" class="mt-2 flex items-center gap-2 text-sm">
                            <input
                                class="h-4 w-4 accent-ember"
                                type="checkbox"
                                :checked="draftBrands.includes(brand.id)"
                                @change="toggleDraft('brand', brand.id)"
                            />
                            <img v-if="brand.logo" :src="brand.logo" alt="" class="h-6 w-6 rounded object-contain" />
                            <span v-else class="h-6 w-6 rounded bg-cream" />
                            {{ brand.name }}
                        </label>

                        <p class="mt-4 text-xs font-medium tracking-wide text-black/50 uppercase">Stock</p>
                        <label v-for="status in stockOptions" :key="status.id" class="mt-2 flex items-center gap-2 text-sm">
                            <input
                                class="h-4 w-4 accent-ember"
                                type="checkbox"
                                :checked="draftStock.includes(status.id)"
                                @change="toggleDraft('stock', status.id)"
                            />
                            {{ status.name }}
                        </label>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="h-10 rounded-xl bg-ember px-4 text-sm font-medium text-black" @click="applyFilters">
                                Apply
                            </button>
                            <button type="button" class="h-10 rounded-xl border border-black/15 bg-white px-4 text-sm font-medium text-black" @click="clearFilters">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <div class="relative ml-auto sm:ml-0">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-black/15 bg-white px-3 text-sm font-medium text-black"
                        @click="sortOpen = !sortOpen; filtersOpen = false"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M6 4v12M6 4 3.5 6.5M6 4l2.5 2.5M14 16V4M14 16l-2.5-2.5M14 16l2.5-2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ sortLabel }}
                    </button>
                    <div v-if="sortOpen" class="absolute right-0 z-20 mt-2 w-52 rounded-2xl border border-black/10 bg-white p-2 shadow-lg sm:left-0 sm:right-auto">
                        <button
                            v-for="option in [
                                { value: 'name-asc', label: 'Name: A to Z' },
                                { value: 'name-desc', label: 'Name: Z to A' },
                                { value: 'price-desc', label: 'Price: High to low' },
                                { value: 'price-asc', label: 'Price: Low to high' },
                            ]"
                            :key="option.value"
                            type="button"
                            class="flex h-10 w-full items-center rounded-xl px-3 text-left text-sm"
                            :class="sort === option.value ? 'bg-ember/20 font-medium' : ''"
                            @click="chooseSort(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="visibleProducts.length === 0" class="rounded-2xl border border-dashed border-navy/20 px-4 py-12 text-center text-sm text-black">
                {{ products.length === 0 ? 'No products yet.' : 'No products match.' }}
            </p>

            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <article
                    v-for="product in visibleProducts"
                    :key="product.id"
                    class="flex flex-col overflow-hidden rounded-2xl border border-navy/15 bg-white"
                >
                    <div class="relative">
                        <img
                            v-if="product.image_url && !missingImages[product.id]"
                            :src="product.image_url"
                            :alt="product.name"
                            class="aspect-square w-full bg-cream object-cover"
                            @error="missingImages[product.id] = true"
                        />
                        <div
                            v-else
                            class="flex aspect-square w-full items-center justify-center bg-cream text-sm text-black/50"
                        >
                            No photo
                        </div>
                        <span
                            v-if="product.badge"
                            class="absolute top-2 left-2 rounded-full bg-ember px-2 py-1 text-xs font-medium text-black"
                        >
                            {{ product.badge }}
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col gap-1 p-4">
                        <div v-if="product.brand_name" class="flex items-center gap-2">
                            <img
                                v-if="product.brand_logo_url && !missingBrandLogos[product.id]"
                                :src="product.brand_logo_url"
                                alt=""
                                class="h-6 w-6 rounded object-contain"
                                @error="missingBrandLogos[product.id] = true"
                            />
                            <p class="text-xs font-medium text-black/70">{{ product.brand_name }}</p>
                        </div>
                        <h2 class="text-sm font-medium leading-5 text-black">{{ product.item_name }}</h2>
                        <p v-if="product.variant_name" class="text-xs text-black/60">{{ product.variant_name }}</p>
                        <p class="text-sm font-medium text-ember">{{ product.price_label }}</p>
                        <div v-if="cartQuantity(product.id) > 0" class="mt-auto flex h-10 items-center justify-between rounded-xl border border-black/15 bg-white px-1">
                            <button type="button" class="h-8 w-8 rounded-lg text-lg" aria-label="Decrease quantity" @click="changeProductQuantity(product, cartQuantity(product.id) - 1)">
                                −
                            </button>
                            <span class="text-sm font-medium">{{ cartQuantity(product.id) }}</span>
                            <button
                                type="button"
                                class="h-8 w-8 rounded-lg text-lg"
                                aria-label="Increase quantity"
                                :disabled="cartQuantity(product.id) >= quantityLimit(product)"
                                @click="changeProductQuantity(product, cartQuantity(product.id) + 1)"
                            >
                                +
                            </button>
                        </div>
                        <button
                            v-else
                            type="button"
                            class="mt-auto h-10 rounded-xl text-sm font-medium disabled:cursor-not-allowed"
                            :class="product.in_stock ? 'bg-green-600 text-white' : (canPreorder(product) ? 'bg-yellow-400 text-black' : 'bg-black/10 text-black/50')"
                            :disabled="product.is_product && !product.in_stock && !product.allows_preorder"
                            @click="addProduct(product)"
                        >
                            {{ product.in_stock ? 'Add' : (canPreorder(product) ? 'Preorder' : 'Out of stock') }}
                        </button>
                    </div>
                </article>
            </div>
        </main>

        <div v-if="cartOpen" class="fixed inset-0 z-40 flex justify-end bg-black/40">
            <button type="button" class="hidden flex-1 sm:block" aria-label="Close cart" @click="cartOpen = false" />
            <section class="flex h-full w-full max-w-md flex-col bg-cream text-black">
                <div class="flex items-center justify-between border-b border-navy/15 px-4 py-3 text-black">
                    <h2 class="text-base font-semibold">Cart</h2>
                    <button type="button" class="h-10 rounded-xl px-3 text-sm" @click="cartOpen = false">
                        Close
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4">
                    <p v-if="cart.length === 0" class="text-sm text-black">
                        Your cart is empty.
                    </p>
                    <ul v-else class="space-y-4">
                        <li v-for="line in cart" :key="line.id" class="flex gap-3">
                            <img
                                v-if="line.image_url"
                                :src="line.image_url"
                                alt=""
                                class="h-16 w-16 rounded-xl object-cover"
                            />
                            <div v-else class="h-16 w-16 rounded-xl bg-white" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-black">{{ line.name }}</p>
                                <p class="text-sm font-medium text-ember">{{ line.price_label }}</p>
                                <p v-if="line.preorder" class="text-xs font-medium text-navy">Preorder</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="button" class="h-8 w-8 rounded-lg border border-navy/20 bg-white text-black" @click="changeQuantity(line, line.quantity - 1)">
                                        −
                                    </button>
                                    <span class="w-6 text-center text-sm text-black">{{ line.quantity }}</span>
                                    <button
                                        type="button"
                                        class="h-8 w-8 rounded-lg border border-navy/20 bg-white text-black"
                                        :disabled="line.quantity >= lineQuantityLimit(line)"
                                        @click="changeQuantity(line, line.quantity + 1)"
                                    >
                                        +
                                    </button>
                                    <button type="button" class="ml-auto text-sm text-navy" @click="removeLine(line.id)">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="space-y-3 border-t border-navy/15 px-4 py-4">
                    <p class="flex items-center justify-between text-sm font-medium">
                        <span>Total</span>
                        <span>{{ formatPeso(cartTotal) }}</span>
                    </p>
                    <Link
                        v-if="cart.length > 0 && filters.branch"
                        :href="`/checkout?branch=${filters.branch}`"
                        class="flex h-11 w-full items-center justify-center rounded-xl bg-navy text-sm font-medium text-white"
                    >
                        Checkout
                    </Link>
                    <p v-else class="text-sm text-black/60">Add a product to check out.</p>
                </div>
            </section>
        </div>
    </div>
</template>
