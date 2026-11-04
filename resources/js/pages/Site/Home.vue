<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatPeso } from '../../formatPeso';

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
});

const page = usePage();
const query = ref(props.filters.q ?? '');
const cartOpen = ref(false);
const cart = ref([]);
const logoMissing = ref(false);
const missingImages = ref({});

const form = useForm({
    branch_id: props.filters.branch,
    name: '',
    phone: '',
    items: [],
});

const confirmation = computed(() => page.props.flash?.checkout_success ?? null);
const cartCount = computed(() => cart.value.reduce((total, line) => total + line.quantity, 0));
const cartTotal = computed(() => cart.value.reduce((total, line) => total + Number(line.price) * line.quantity, 0));

watch(
    () => props.filters.q,
    (value) => {
        query.value = value ?? '';
    },
);

watch(
    () => props.filters.branch,
    () => {
        cart.value = readCart();
        form.branch_id = props.filters.branch;
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

watch(confirmation, (value) => {
    if (value) {
        cart.value = [];
        sessionStorage.removeItem(storageKey());
        cartOpen.value = false;
    }
}, { immediate: true });

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

function visit(changes = {}) {
    router.get('/', {
        branch: props.filters.branch,
        q: query.value,
        category: props.filters.category,
        ...changes,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function cartQuantity(productId) {
    return cart.value.find((line) => line.id === productId)?.quantity ?? 0;
}

function canAdd(product) {
    if (!product.in_stock) {
        return false;
    }

    if (!product.is_product || product.stock === null) {
        return true;
    }

    return cartQuantity(product.id) < product.stock;
}

function addProduct(product) {
    if (!canAdd(product)) {
        return;
    }

    const existing = cart.value.find((line) => line.id === product.id);

    if (existing) {
        existing.quantity += 1;

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
    });
    cartOpen.value = true;
}

function changeQuantity(line, next) {
    const quantity = Number(next);

    if (!Number.isInteger(quantity) || quantity < 1) {
        return;
    }

    if (line.is_product && line.stock !== null && quantity > line.stock) {
        line.quantity = line.stock;

        return;
    }

    line.quantity = Math.min(quantity, 99);
}

function removeLine(productId) {
    cart.value = cart.value.filter((line) => line.id !== productId);
}

function checkout() {
    form.branch_id = props.filters.branch;
    form.items = cart.value.map((line) => ({
        catalog_item_variant_id: line.id,
        quantity: line.quantity,
    }));
    form.post('/checkout', {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            sessionStorage.removeItem(storageKey());
            cartOpen.value = false;
            form.reset('name', 'phone', 'items');
        },
    });
}
</script>

<template>
    <div>
        <header class="sticky top-0 z-30 border-b border-black/10 bg-white">
            <div class="mx-auto flex max-w-6xl items-center gap-2 px-3 py-3">
                <div class="flex shrink-0 items-center gap-2">
                    <img
                        v-if="$page.props.businessLogoUrl && !logoMissing"
                        :src="$page.props.businessLogoUrl"
                        alt=""
                        class="h-9 w-9 rounded-lg bg-white object-contain"
                        @error="logoMissing = true"
                    />
                    <span v-else class="flex h-9 w-9 items-center justify-center rounded-lg bg-ember text-sm font-semibold text-black">
                        PP
                    </span>
                    <label class="sr-only" for="shop-branch">Branch</label>
                    <select
                        id="shop-branch"
                        class="h-10 w-28 rounded-xl border border-black/15 bg-white px-3 text-sm text-black sm:w-40"
                        :value="filters.branch ?? ''"
                        :disabled="branches.length === 0"
                        @change="visit({ branch: Number($event.target.value) || null, category: '' })"
                    >
                        <option v-if="branches.length === 0" value="">No branches</option>
                        <option v-for="option in branches" :key="option.id" :value="option.id">
                            {{ option.name }}
                        </option>
                    </select>
                </div>

                <form class="min-w-0 flex-1" @submit.prevent="visit()">
                    <label class="sr-only" for="shop-search">Search</label>
                    <input
                        id="shop-search"
                        v-model="query"
                        type="search"
                        placeholder="Search"
                        class="h-10 w-full rounded-xl border border-black/15 bg-white px-3 text-sm text-black placeholder:text-black/50"
                    />
                </form>

                <button
                    type="button"
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-ember px-3 text-sm font-medium text-black"
                    @click="cartOpen = true"
                >
                    Cart
                    <span class="rounded-full bg-white px-2 py-0.5 text-xs text-black">{{ cartCount }}</span>
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <p
                v-if="confirmation"
                class="mb-5 rounded-2xl border border-navy/20 bg-ember/15 px-4 py-3 text-sm text-black"
            >
                Order {{ confirmation.number }} is saved for {{ confirmation.branch }}. Total {{ confirmation.total }}. Pay when you pick it up.
            </p>

            <div class="mb-5 flex gap-2 overflow-x-auto pb-1">
                <button
                    type="button"
                    class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium"
                    :class="filters.category === '' ? 'bg-ember text-black' : 'border border-navy/20 bg-white text-black'"
                    @click="visit({ category: '' })"
                >
                    All
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium"
                    :class="filters.category === category.id ? 'bg-ember text-black' : 'border border-navy/20 bg-white text-black'"
                    @click="visit({ category: category.id })"
                >
                    {{ category.name }}
                </button>
            </div>

            <p v-if="products.length === 0" class="rounded-2xl border border-dashed border-navy/20 px-4 py-12 text-center text-sm text-black">
                {{ filters.q || filters.category ? 'No products match that search.' : 'No products yet.' }}
            </p>

            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <article
                    v-for="product in products"
                    :key="product.id"
                    class="flex flex-col overflow-hidden rounded-2xl border border-navy/15 bg-white"
                >
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
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <h2 class="text-sm font-medium leading-5 text-black">{{ product.name }}</h2>
                        <p class="text-sm font-medium text-ember">{{ product.price_label }}</p>
                        <p class="text-xs font-medium" :class="product.in_stock ? 'text-navy' : 'text-black/50'">
                            {{ product.stock_label }}
                        </p>
                        <button
                            type="button"
                            class="mt-auto h-10 rounded-xl text-sm font-medium"
                            :class="canAdd(product) ? 'bg-ember text-black' : 'bg-black/10 text-black/40'"
                            :disabled="!canAdd(product)"
                            @click="addProduct(product)"
                        >
                            {{ product.in_stock ? 'Add' : 'Out of stock' }}
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
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="button" class="h-8 w-8 rounded-lg border border-navy/20 bg-white text-black" @click="line.quantity === 1 ? removeLine(line.id) : changeQuantity(line, line.quantity - 1)">
                                        −
                                    </button>
                                    <span class="w-6 text-center text-sm text-black">{{ line.quantity }}</span>
                                    <button
                                        type="button"
                                        class="h-8 w-8 rounded-lg border border-navy/20 bg-white text-black"
                                        :disabled="line.is_product && line.stock !== null && line.quantity >= line.stock"
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

                <form class="space-y-3 border-t border-navy/15 px-4 py-4" @submit.prevent="checkout">
                    <p class="flex items-center justify-between text-sm font-medium">
                        <span>Total</span>
                        <span>{{ formatPeso(cartTotal) }}</span>
                    </p>
                    <div>
                        <label class="mb-1 block text-sm" for="shop-name">Name</label>
                        <input
                            id="shop-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            class="h-10 w-full rounded-xl border border-navy/20 bg-white px-3 text-sm text-black"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-danger">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm" for="shop-phone">Phone</label>
                        <input
                            id="shop-phone"
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            class="h-10 w-full rounded-xl border border-navy/20 bg-white px-3 text-sm text-black"
                        />
                        <p v-if="form.errors.phone" class="mt-1 text-sm text-danger">{{ form.errors.phone }}</p>
                    </div>
                    <p v-if="form.errors.items" class="text-sm text-danger">{{ form.errors.items }}</p>
                    <p v-if="form.errors.branch_id" class="text-sm text-danger">{{ form.errors.branch_id }}</p>
                    <button
                        type="submit"
                        class="h-11 w-full rounded-xl bg-ember text-sm font-medium text-black disabled:opacity-60"
                        :disabled="cart.length === 0 || form.processing"
                    >
                        Place order
                    </button>
                    <p class="text-xs text-black">
                        Pay at {{ branch?.name ?? 'the branch' }} when you pick it up.
                    </p>
                </form>
            </section>
        </div>
    </div>
</template>
