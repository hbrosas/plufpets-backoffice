<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InstructionPhoto from '../../components/InstructionPhoto.vue';
import PreferredSlotButtons from '../../components/PreferredSlotButtons.vue';
import SiteLayout from '../../layouts/SiteLayout.vue';

defineOptions({
    layout: SiteLayout,
});

const props = defineProps({
    branch: {
        type: Object,
        default: null,
    },
    deliveryMethods: {
        type: Array,
        required: true,
    },
    paymentMethods: {
        type: Array,
        required: true,
    },
    deliverySchedules: {
        type: Array,
        default: () => [],
    },
});

const cart = ref([]);
const priced = ref(null);
const quoteError = ref('');
const voucherDraft = ref('');
const appliedVoucher = ref('');
const voucherMessage = ref('');
const voucherError = ref('');
const applyingVoucher = ref(false);

const form = useForm({
    branch_id: props.branch?.id ?? null,
    name: '',
    phone: '',
    delivery_method_id: null,
    delivery_schedule_id: '',
    preferred_on: '',
    payment_method_id: null,
    voucher_code: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    province: '',
    change_for: '',
    items: [],
});

const selectedDelivery = computed(() => props.deliveryMethods.find((method) => method.id === Number(form.delivery_method_id)) ?? null);
const selectedPayment = computed(() => props.paymentMethods.find((method) => method.id === Number(form.payment_method_id)) ?? null);
const asksForAddress = computed(() => Boolean(selectedDelivery.value?.asks_for_address));
const usesDeliverySchedule = computed(() => Boolean(selectedDelivery.value?.uses_delivery_schedule));
const asksForChange = computed(() => Boolean(selectedPayment.value?.asks_for_change));

watch(() => form.delivery_method_id, () => {
    form.delivery_schedule_id = '';
    form.preferred_on = '';
});

watch(() => form.payment_method_id, () => {
    form.change_for = '';
});

function chooseSlot(slot) {
    form.delivery_schedule_id = slot.id;
    form.preferred_on = slot.on;
    form.clearErrors('delivery_schedule_id');
}

function peso(amount) {
    const value = Number.isFinite(Number(amount)) ? Number(amount) : 0;

    return `₱${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const summary = computed(() => {
    const cartSubtotal = cart.value.reduce((sum, line) => sum + Number(line.price) * Number(line.quantity), 0);
    const subtotal = priced.value ? Number(priced.value.subtotal) : cartSubtotal;
    const discount = appliedVoucher.value && priced.value ? Number(priced.value.discount) : 0;
    const deliveryFee = Number(selectedDelivery.value?.fee ?? 0);
    const paymentFee = Number(selectedPayment.value?.fee ?? 0);
    const total = Math.max(0, subtotal + deliveryFee + paymentFee - discount);

    return {
        subtotal_label: peso(subtotal),
        delivery_fee_label: peso(deliveryFee),
        payment_fee_label: peso(paymentFee),
        discount_label: peso(discount),
        total_label: peso(total),
    };
});
function storageKey() {
    return `shop-cart-${props.branch?.id ?? 'none'}`;
}

function readCart() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey()) || '[]');

        cart.value = Array.isArray(saved) ? saved : [];
    } catch {
        cart.value = [];
    }

    form.items = cart.value.map((line) => ({
        catalog_item_variant_id: line.id,
        quantity: line.quantity,
    }));
}

function csrfToken() {
    const match = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : '';
}

let quoteRequest = 0;

async function refreshQuote() {
    const requestId = ++quoteRequest;
    quoteError.value = '';

    if (!props.branch || form.items.length === 0 || !form.delivery_method_id || !form.payment_method_id) {
        return;
    }

    const response = await fetch('/checkout/quote', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            branch_id: props.branch.id,
            delivery_method_id: form.delivery_method_id,
            payment_method_id: form.payment_method_id,
            voucher_code: appliedVoucher.value,
            items: form.items,
        }),
    });
    const payload = await response.json();

    if (requestId !== quoteRequest) {
        return;
    }

    if (!response.ok) {
        if (payload.errors?.voucher_code?.[0]) {
            voucherError.value = payload.errors.voucher_code[0];
            voucherMessage.value = '';
            appliedVoucher.value = '';

            return;
        }

        quoteError.value = payload.errors?.items?.[0]
            ?? payload.message
            ?? 'Could not update the total.';

        return;
    }

    priced.value = {
        subtotal: Number(payload.subtotal),
        discount: Number(payload.discount),
    };
}

async function applyVoucher() {
    voucherError.value = '';
    voucherMessage.value = '';
    const code = voucherDraft.value.trim();

    if (code === '') {
        appliedVoucher.value = '';
        voucherError.value = 'Enter a voucher code.';

        return;
    }

    applyingVoucher.value = true;

    try {
        const response = await fetch('/checkout/voucher', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                branch_id: props.branch?.id,
                voucher_code: code,
            }),
        });
        const payload = await response.json();

        if (!response.ok) {
            appliedVoucher.value = '';
            voucherError.value = payload.errors?.voucher_code?.[0] ?? 'That voucher is not valid.';

            return;
        }

        appliedVoucher.value = payload.code;
        voucherDraft.value = payload.code;
        voucherMessage.value = `Voucher ${payload.code} applied.`;
    } finally {
        applyingVoucher.value = false;
    }
}

function removeVoucher() {
    appliedVoucher.value = '';
    voucherDraft.value = '';
    voucherMessage.value = '';
    voucherError.value = '';
    form.clearErrors('voucher_code');
}

function placeOrder() {
    form.branch_id = props.branch?.id ?? null;
    form.voucher_code = appliedVoucher.value;
    form.post('/checkout', {
        onSuccess: () => sessionStorage.removeItem(storageKey()),
    });
}

readCart();

let quoteTimer = null;

watch(voucherDraft, (value) => {
    voucherError.value = '';

    if (value.trim().toUpperCase() !== appliedVoucher.value) {
        appliedVoucher.value = '';
        voucherMessage.value = '';
    }
});

watch(
    () => `${appliedVoucher.value}|${JSON.stringify(form.items)}|${form.delivery_method_id && form.payment_method_id ? 1 : 0}`,
    () => {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(() => {
            refreshQuote();
        }, 300);
    },
);
</script>

<template>
    <Head title="Checkout" />

    <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6">
        <Link :href="branch ? `/?branch=${branch.id}` : '/'" class="inline-flex w-fit items-center gap-1 text-sm font-medium text-navy">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M12.5 4.5 7 10l5.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Back to Shop
        </Link>

        <p v-if="branch" class="text-2xl font-semibold tracking-tight">
            You're ordering from <span class="text-navy">{{ branch.name }}</span>
        </p>

        <p v-if="!branch" class="rounded-2xl border border-dashed border-navy/20 px-4 py-8 text-center text-sm">
            No branch is open for orders.
        </p>
        <p v-else-if="cart.length === 0" class="rounded-2xl border border-dashed border-navy/20 px-4 py-8 text-center text-sm">
            Your cart is empty.
        </p>

        <form v-else class="grid items-start gap-6 lg:grid-cols-2" @submit.prevent="placeOrder">
            <section class="flex flex-col gap-3 rounded-2xl border border-black/10 bg-white p-4">
                <h2 class="text-base font-semibold">Your Order</h2>
                <ul class="flex flex-col gap-2">
                    <li v-for="line in cart" :key="line.id" class="flex items-start justify-between gap-3 text-sm">
                        <span>{{ line.name }} × {{ line.quantity }}</span>
                        <span class="font-medium text-ember">{{ line.price_label }}</span>
                    </li>
                </ul>
                <div class="flex flex-col gap-1 border-t border-black/10 pt-3 text-sm">
                    <p class="flex justify-between"><span>Subtotal</span><span>{{ summary.subtotal_label }}</span></p>
                    <p class="flex justify-between"><span>Delivery fee</span><span>{{ summary.delivery_fee_label }}</span></p>
                    <p class="flex justify-between"><span>Payment fee</span><span>{{ summary.payment_fee_label }}</span></p>
                    <p v-if="appliedVoucher" class="flex items-center justify-between gap-3">
                        <span>Voucher {{ appliedVoucher }}</span>
                        <span class="flex items-center gap-3">
                            <span>-{{ summary.discount_label }}</span>
                            <button class="font-medium text-danger" type="button" @click="removeVoucher">Remove</button>
                        </span>
                    </p>
                    <p class="flex justify-between pt-1 text-base font-semibold"><span>Total</span><span>{{ summary.total_label }}</span></p>
                </div>
            </section>

            <div class="flex flex-col gap-4">
                <div>
                    <label class="mb-1 block text-sm" for="checkout-name">Name</label>
                    <input id="checkout-name" v-model="form.name" autocomplete="name" class="h-11 w-full rounded-xl border border-black/15 bg-white px-3 text-sm" required type="text" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-danger">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm" for="checkout-phone">Contact number</label>
                    <input id="checkout-phone" v-model="form.phone" autocomplete="tel" class="h-11 w-full rounded-xl border border-black/15 bg-white px-3 text-sm" inputmode="tel" placeholder="09171234567" required type="tel" />
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-danger">{{ form.errors.phone }}</p>
                </div>

                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-sm">Delivery method</legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="method in deliveryMethods"
                            :key="method.id"
                            type="button"
                            class="h-10 rounded-xl border px-3 text-sm font-medium"
                            :class="Number(form.delivery_method_id) === method.id ? 'border-navy bg-navy text-white' : 'border-black/15 bg-white text-black'"
                            @click="form.delivery_method_id = method.id"
                        >
                            {{ method.name }} · {{ method.fee_label }}
                        </button>
                    </div>
                    <p v-if="deliveryMethods.length === 0" class="text-sm text-black/60">No delivery methods.</p>
                    <div v-if="selectedDelivery?.instructions || selectedDelivery?.instruction_images?.length" class="flex flex-col gap-3 rounded-xl border border-navy/20 bg-navy/10 px-4 py-3 text-sm" role="alert">
                        <p v-if="selectedDelivery.instructions">{{ selectedDelivery.instructions }}</p>
                        <InstructionPhoto
                            v-for="(image, index) in selectedDelivery.instruction_images"
                            :key="image"
                            :src="image"
                            :alt="`Delivery instruction photo ${index + 1}`"
                        />
                    </div>
                    <p v-if="form.errors.delivery_method_id" class="text-sm text-danger">{{ form.errors.delivery_method_id }}</p>
                    <div v-if="usesDeliverySchedule" class="flex flex-col gap-2">
                        <p class="text-sm">Preferred slot</p>
                        <PreferredSlotButtons
                            :slots="deliverySchedules"
                            :schedule-id="form.delivery_schedule_id"
                            :preferred-on="form.preferred_on"
                            @select="chooseSlot"
                        />
                        <p v-if="deliverySchedules.length === 0" class="text-sm text-black/60">No open delivery slots for this branch.</p>
                        <p v-if="form.errors.delivery_schedule_id" class="text-sm text-danger">{{ form.errors.delivery_schedule_id }}</p>
                    </div>
                </fieldset>

                <div v-if="asksForAddress" class="flex flex-col gap-3 rounded-2xl border border-black/10 bg-white p-4">
                    <p class="text-sm font-medium">Delivery address</p>
                    <div>
                        <label class="mb-1 block text-sm" for="checkout-address-1">Address 1</label>
                        <input id="checkout-address-1" v-model="form.address_line_1" autocomplete="address-line1" class="h-11 w-full rounded-xl border border-black/15 px-3 text-sm" required type="text" />
                        <p v-if="form.errors.address_line_1" class="mt-1 text-sm text-danger">{{ form.errors.address_line_1 }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm" for="checkout-address-2">Address 2</label>
                        <input id="checkout-address-2" v-model="form.address_line_2" autocomplete="address-line2" class="h-11 w-full rounded-xl border border-black/15 px-3 text-sm" type="text" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm" for="checkout-city">City</label>
                            <input id="checkout-city" v-model="form.city" autocomplete="address-level2" class="h-11 w-full rounded-xl border border-black/15 px-3 text-sm" required type="text" />
                            <p v-if="form.errors.city" class="mt-1 text-sm text-danger">{{ form.errors.city }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm" for="checkout-province">Province</label>
                            <input id="checkout-province" v-model="form.province" autocomplete="address-level1" class="h-11 w-full rounded-xl border border-black/15 px-3 text-sm" required type="text" />
                            <p v-if="form.errors.province" class="mt-1 text-sm text-danger">{{ form.errors.province }}</p>
                        </div>
                    </div>
                </div>

                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-sm">Payment method</legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="method in paymentMethods"
                            :key="method.id"
                            type="button"
                            class="h-10 rounded-xl border px-3 text-sm font-medium"
                            :class="Number(form.payment_method_id) === method.id ? 'border-navy bg-navy text-white' : 'border-black/15 bg-white text-black'"
                            @click="form.payment_method_id = method.id"
                        >
                            {{ method.name }} · {{ method.fee_label }}
                        </button>
                    </div>
                    <p v-if="paymentMethods.length === 0" class="text-sm text-black/60">No payment methods.</p>
                    <div v-if="selectedPayment?.instructions || selectedPayment?.instruction_images?.length" class="flex flex-col gap-3 rounded-xl border border-navy/20 bg-navy/10 px-4 py-3 text-sm" role="alert">
                        <p v-if="selectedPayment.instructions">{{ selectedPayment.instructions }}</p>
                        <InstructionPhoto
                            v-for="(image, index) in selectedPayment.instruction_images"
                            :key="image"
                            :src="image"
                            :alt="`Payment instruction photo ${index + 1}`"
                        />
                    </div>
                    <p v-if="form.errors.payment_method_id" class="text-sm text-danger">{{ form.errors.payment_method_id }}</p>
                    <div v-if="asksForChange">
                        <label class="mb-1 block text-sm" for="checkout-change-for">Change for</label>
                        <input id="checkout-change-for" v-model="form.change_for" class="h-11 w-full rounded-xl border border-black/15 bg-white px-3 text-sm" min="0.01" step="0.01" required type="number" />
                        <p v-if="form.errors.change_for" class="mt-1 text-sm text-danger">{{ form.errors.change_for }}</p>
                    </div>
                </fieldset>

                <div>
                    <label class="mb-1 block text-sm" for="checkout-voucher">Voucher</label>
                    <div class="flex gap-2">
                        <input id="checkout-voucher" v-model="voucherDraft" class="h-11 min-w-0 flex-1 rounded-xl border border-black/15 bg-white px-3 text-sm uppercase" placeholder="Code" type="text" @keydown.enter.prevent="applyVoucher" />
                        <button class="h-11 shrink-0 rounded-xl bg-navy px-4 text-sm font-medium text-white disabled:opacity-60" type="button" :disabled="applyingVoucher" @click="applyVoucher">
                            {{ applyingVoucher ? 'Checking…' : 'Apply' }}
                        </button>
                    </div>
                    <p v-if="voucherMessage" class="mt-1 text-sm text-navy">{{ voucherMessage }}</p>
                    <p v-if="form.errors.voucher_code || voucherError" class="mt-1 text-sm text-danger">{{ form.errors.voucher_code || voucherError }}</p>
                </div>

                <p v-if="quoteError" class="text-sm text-danger">{{ quoteError }}</p>
                <p v-if="form.errors.items" class="text-sm text-danger">{{ form.errors.items }}</p>
                <button class="h-11 rounded-xl bg-ember text-sm font-medium text-black disabled:opacity-60" type="submit" :disabled="form.processing || !form.delivery_method_id || !form.payment_method_id">
                    {{ form.processing ? 'Placing order…' : 'Place Order' }}
                </button>
            </div>
        </form>
    </main>
</template>
