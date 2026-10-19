<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BarcodeInput from '../../components/BarcodeInput.vue';
import { formatPeso } from '../../formatPeso';
import PosLayout from '../../layouts/PosLayout.vue';
import { openAssignedDrawer } from '../../printerConnection';
import { showToast } from '../../toast';

defineOptions({
    layout: PosLayout,
});

const props = defineProps({
    branch: { type: Object, required: true },
    shift: { type: Object, default: null },
    denominations: { type: Array, required: true },
    categories: { type: Array, required: true },
    variants: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    deliveryMethods: { type: Array, required: true },
    taxFees: { type: Array, required: true },
    todayTransactions: { type: Array, required: true },
    todayTransactionsTotal: { type: String, required: true },
    openOrders: { type: Array, default: () => [] },
    printers: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const employee = computed(() => page.props.employee);
const canOpenDrawer = computed(() => (employee.value?.permissions ?? []).includes('pos.open-drawer'));
const receiptPrinter = computed(
    () => props.printers.find((printer) => printer.role === 'receipt') ?? null,
);
const openingDrawer = ref(false);

const selectedCategoryId = ref(null);
const search = ref('');
const cart = ref([]);
const salesOpen = ref(false);
const cashOpen = ref(false);
const closeOpen = ref(false);
const customizeOpen = ref(false);
const customizing = ref(null);
const customQty = ref(1);
const customNotes = ref('');
const customSelections = ref({});
const editingLineKey = ref(null);
const chargeOpen = ref(false);
const openOrdersOpen = ref(false);
const saveOpen = ref(false);
const activeOpenOrderId = ref(null);
const activeOpenOrderName = ref('');
let cartLineSeq = 0;

const registerPath = computed(() => `/backoffice/pos/register/${props.branch.id}`);
const soldQuantities = ref({});
const registerPartial = {
    preserveScroll: true,
    preserveState: true,
    preserveUrl: true,
    showProgress: false,
    only: ['shift', 'todayTransactions', 'todayTransactionsTotal', 'openOrders', 'flash'],
};

watch(
    () => props.variants,
    () => {
        soldQuantities.value = {};
    },
);

const openForm = useForm({
    denominations: emptyDenominations(props.denominations),
});

const saleForm = useForm({
    items: [],
    payments: [],
    delivery_method_id: '',
    delivery_fee: '',
    pos_open_order_id: null,
});

const checkoutPayments = ref([]);
const splitCheckout = ref(false);
const checkoutSuccess = ref(null);
const draftPaymentMethodId = ref(props.paymentMethods.find((method) => method.is_cash)?.id ?? props.paymentMethods[0]?.id ?? '');
const draftAmount = ref('');
const draftTendered = ref('');
const draftReference = ref('');
const checkoutError = ref('');

const openOrderForm = useForm({
    name: '',
    items: [],
});

const deleteOpenOrderForm = useForm({});

const cashForm = useForm({
    type: 'cash_in',
    amount: '',
    reason: '',
});

const closeForm = useForm({
    denominations: emptyDenominations(props.denominations),
    remit_amount: '',
    confirm_variance: false,
});

const draftPaymentMethod = computed(() =>
    props.paymentMethods.find((method) => String(method.id) === String(draftPaymentMethodId.value)),
);

const selectedDelivery = computed(() =>
    props.deliveryMethods.find((method) => String(method.id) === String(saleForm.delivery_method_id)),
);

const filteredVariants = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.variants.filter((variant) => {
        if (!query) {
            if (selectedCategoryId.value === 'none' && variant.pos_category_id) {
                return false;
            }

            if (selectedCategoryId.value && selectedCategoryId.value !== 'none' && variant.pos_category_id !== selectedCategoryId.value) {
                return false;
            }

            return true;
        }

        return [variant.product_name, variant.variant_name, variant.sku, variant.barcode]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query));
    });
});

const cartSubtotal = computed(() =>
    cart.value.reduce((sum, line) => sum + Number(line.price) * line.quantity, 0),
);

const deliveryFee = computed(() => {
    if (!selectedDelivery.value) {
        return 0;
    }

    if (saleForm.delivery_fee === '' || saleForm.delivery_fee === null) {
        return selectedDelivery.value.fee === null ? 0 : Number(selectedDelivery.value.fee);
    }

    return Number(saleForm.delivery_fee);
});

const taxTotal = computed(() => {
    const base = cartSubtotal.value + deliveryFee.value;

    return props.taxFees.reduce((sum, tax) => {
        if (tax.type === 'percent') {
            return sum + (base * Number(tax.rate)) / 100;
        }

        return sum + Number(tax.rate);
    }, 0);
});

const saleTotal = computed(() => cartSubtotal.value + deliveryFee.value + taxTotal.value);

const paidTotal = computed(() =>
    checkoutPayments.value.reduce((sum, payment) => sum + Number(payment.amount), 0),
);

const remainingTotal = computed(() => Math.max(0, Math.round((saleTotal.value - paidTotal.value) * 100) / 100));

const checkoutBillValues = computed(() =>
    [...props.denominations]
        .map((value) => Number(value))
        .filter((value) => value >= 20)
        .sort((a, b) => b - a),
);

const draftChange = computed(() => {
    if (!draftPaymentMethod.value?.is_cash) {
        return 0;
    }

    const tendered = Number(draftTendered.value);
    const amount = Number(draftAmount.value || remainingTotal.value);

    if (!tendered || tendered < amount) {
        return 0;
    }

    return Math.round((tendered - amount) * 100) / 100;
});

const startingCash = computed(() =>
    openForm.denominations.reduce((sum, row) => sum + Number(row.denomination) * Number(row.quantity || 0), 0),
);

const countedCash = computed(() =>
    closeForm.denominations.reduce((sum, row) => sum + Number(row.denomination) * Number(row.quantity || 0), 0),
);

const variance = computed(() => countedCash.value - Number(props.shift?.expected_cash ?? 0));
const cashCountComplete = computed(() => Math.abs(variance.value) < 0.005);

function emptyDenominations(values) {
    return values.map((value) => ({ denomination: value, quantity: 0 }));
}

function changeDenominationQty(rows, index, delta) {
    const quantity = Math.floor(Number(rows[index].quantity) || 0);

    rows[index].quantity = Math.max(0, quantity + delta);
}

function changeOpenQty(index, delta) {
    changeDenominationQty(openForm.denominations, index, delta);
}

function changeCloseQty(index, delta) {
    changeDenominationQty(closeForm.denominations, index, delta);
}

function selectInputValue(event) {
    const input = event.target;

    requestAnimationFrame(() => {
        input.select();
    });
}

function defaultPaymentMethodId() {
    return props.paymentMethods.find((method) => method.is_cash)?.id ?? props.paymentMethods[0]?.id ?? '';
}

function liveQuantity(variant) {
    if (!variant.is_product || variant.quantity === null) {
        return variant.quantity;
    }

    return Math.max(0, Number(variant.quantity) - Number(soldQuantities.value[variant.id] ?? 0));
}

function isInStock(variant) {
    return !variant.is_product || Number(liveQuantity(variant)) > 0;
}

function stockLabel(variant) {
    if (!isInStock(variant)) {
        return 'Out of Stock';
    }

    if (variant.is_product) {
        return `In Stock ${liveQuantity(variant)}`;
    }

    return 'In Stock';
}

function applySoldQuantities(lines) {
    const next = { ...soldQuantities.value };

    lines.forEach((line) => {
        if (!line.is_product) {
            return;
        }

        next[line.id] = Number(next[line.id] ?? 0) + Number(line.quantity);
    });

    soldQuantities.value = next;
}

async function openDrawer(prompt = false) {
    if (prompt && !canOpenDrawer.value) {
        return;
    }

    openingDrawer.value = true;

    try {
        await openAssignedDrawer(receiptPrinter.value, prompt);

        if (prompt) {
            showToast(
                receiptPrinter.value?.device_name
                    ? `Opened drawer on ${receiptPrinter.value.device_name}.`
                    : 'Opened the cash drawer.',
            );
        }
    } catch (error) {
        if (prompt) {
            showToast(error instanceof Error ? error.message : 'Could not open the cash drawer.');
        }
    } finally {
        openingDrawer.value = false;
    }
}

function openShift() {
    openForm.post(`${registerPath.value}/shifts`, {
        preserveScroll: true,
        showProgress: false,
    });
}

function hasModifiers(variant) {
    return (variant.modifiers ?? []).length > 0;
}

function addVariant(variant) {
    if (variant.is_product && liveQuantity(variant) !== null && liveQuantity(variant) < 1) {
        return;
    }

    if (hasModifiers(variant)) {
        openCustomize(variant);
        return;
    }

    pushCartLine(variant, 1, '', []);
}

function openCustomize(variant) {
    customizing.value = variant;
    customQty.value = 1;
    customNotes.value = '';
    customSelections.value = Object.fromEntries((variant.modifiers ?? []).map((modifier) => [modifier.id, '']));
    customizeOpen.value = true;
}

function closeCustomize() {
    customizeOpen.value = false;
    customizing.value = null;
    editingLineKey.value = null;
}

function selectOption(modifierId, optionName) {
    const current = customSelections.value[modifierId];

    customSelections.value = {
        ...customSelections.value,
        [modifierId]: current === optionName ? '' : optionName,
    };
}

function selectedModifierRows() {
    const variant = customizing.value;

    if (!variant) {
        return [];
    }

    return (variant.modifiers ?? []).flatMap((modifier) => {
        const optionName = customSelections.value[modifier.id];

        if (!optionName) {
            return [];
        }

        const option = (modifier.options ?? []).find((row) => row.name === optionName);

        return [
            {
                modifier_id: modifier.id,
                name: modifier.name,
                option: optionName,
                price: option?.price ?? '0.00',
            },
        ];
    });
}

const customUnitPrice = computed(() => {
    const variant = customizing.value;

    if (!variant) {
        return 0;
    }

    const extra = selectedModifierRows().reduce((sum, row) => sum + Number(row.price), 0);

    return Number(variant.price) + extra;
});

function confirmCustomize() {
    const variant = customizing.value;

    if (!variant) {
        return;
    }

    const qty = Math.max(1, Number(customQty.value) || 1);

    if (variant.is_product && liveQuantity(variant) !== null && qty > liveQuantity(variant)) {
        return;
    }

    const modifiers = selectedModifierRows();

    if (editingLineKey.value !== null) {
        const line = cart.value.find((item) => item.key === editingLineKey.value);

        if (line) {
            const extra = modifiers.reduce((sum, row) => sum + Number(row.price), 0);
            line.notes = customNotes.value.trim();
            line.modifiers = modifiers;
            line.price = Number(variant.price) + extra;
            line.quantity = qty;
        }

        closeCustomize();
        return;
    }

    pushCartLine(variant, qty, customNotes.value.trim(), modifiers);
    closeCustomize();
}

function pushCartLine(variant, quantity, notes, modifiers) {
    const signature = JSON.stringify(modifiers);
    const existing = cart.value.find(
        (line) => line.id === variant.id && line.notes === notes && JSON.stringify(line.modifiers) === signature,
    );

    if (existing) {
        const next = existing.quantity + quantity;

        if (variant.is_product && existing.available !== null && next > existing.available) {
            return;
        }

        existing.quantity = next;
        return;
    }

    const extra = modifiers.reduce((sum, row) => sum + Number(row.price), 0);

    cartLineSeq += 1;
    cart.value.push({
        key: cartLineSeq,
        id: variant.id,
        product_name: variant.product_name,
        variant_name: variant.variant_name,
        price: Number(variant.price) + extra,
        quantity,
        notes,
        modifiers,
        is_product: variant.is_product,
        available: liveQuantity(variant),
    });
}

function onBarcode(value) {
    const match = props.variants.find((variant) => variant.barcode && variant.barcode === value);

    if (match) {
        addVariant(match);
        search.value = '';
    }
}

function changeQty(line, delta) {
    setQty(line, line.quantity + delta);
}

function setQty(line, value) {
    const qty = Math.floor(Number(value) || 0);

    if (qty < 1) {
        line.quantity = 1;
        return;
    }

    if (line.is_product && line.available !== null && qty > line.available) {
        line.quantity = line.available;
        return;
    }

    line.quantity = qty;
}

function removeLine(line) {
    cart.value = cart.value.filter((item) => item.key !== line.key);
}

function variantForLine(line) {
    const live = props.variants.find((variant) => variant.id === line.id);

    if (live) {
        return live;
    }

    return {
        id: line.id,
        product_name: line.product_name,
        variant_name: line.variant_name,
        price: Number(line.price) - (line.modifiers ?? []).reduce((sum, row) => sum + Number(row.price), 0),
        is_product: line.is_product,
        quantity: line.available,
        modifiers: (line.modifiers ?? []).map((modifier) => ({
            id: modifier.modifier_id,
            name: modifier.name,
            options: [
                {
                    name: modifier.option,
                    price: modifier.price,
                    price_label: formatPeso(modifier.price),
                },
            ],
        })),
    };
}

function openEditLine(line) {
    const variant = variantForLine(line);

    editingLineKey.value = line.key;
    customizing.value = variant;
    customQty.value = line.quantity;
    customNotes.value = line.notes ?? '';
    customSelections.value = Object.fromEntries(
        (variant.modifiers ?? []).map((modifier) => {
            const selected = (line.modifiers ?? []).find((row) => row.modifier_id === modifier.id);

            return [modifier.id, selected?.option ?? ''];
        }),
    );
    customizeOpen.value = true;
}

function saleItems() {
    return cart.value.map((line) => ({
        catalog_item_variant_id: line.id,
        quantity: line.quantity,
        notes: line.notes || null,
        modifiers: (line.modifiers ?? []).map((row) => ({
            modifier_id: row.modifier_id,
            option: row.option,
        })),
    }));
}

function openOrderItems() {
    return cart.value.map((line) => ({
        catalog_item_variant_id: line.id,
        product_name: line.product_name,
        variant_name: line.variant_name,
        price: Number(line.price).toFixed(2),
        quantity: line.quantity,
        notes: line.notes || null,
        is_product: Boolean(line.is_product),
        modifiers: (line.modifiers ?? []).map((row) => ({
            modifier_id: row.modifier_id,
            name: row.name,
            option: row.option,
            price: Number(row.price).toFixed(2),
        })),
    }));
}

function resetDraftPayment() {
    draftPaymentMethodId.value = defaultPaymentMethodId();
    draftAmount.value = remainingTotal.value > 0 ? remainingTotal.value.toFixed(2) : '';
    draftTendered.value = '';
    draftReference.value = '';
    checkoutError.value = '';
}

function closeCheckout() {
    chargeOpen.value = false;
    splitCheckout.value = false;
    checkoutSuccess.value = null;
    checkoutPayments.value = [];
    checkoutError.value = '';
}

function openCharge() {
    if (cart.value.length === 0) {
        return;
    }

    checkoutSuccess.value = null;
    splitCheckout.value = false;
    checkoutPayments.value = [];
    resetDraftPayment();
    chargeOpen.value = true;
}

function toggleSplitCheckout() {
    splitCheckout.value = !splitCheckout.value;
    checkoutPayments.value = [];
    resetDraftPayment();
}

function addCheckoutPayment() {
    const method = draftPaymentMethod.value;
    const amount = Math.round(Number(draftAmount.value || remainingTotal.value) * 100) / 100;

    checkoutError.value = '';

    if (!method) {
        checkoutError.value = 'Choose a payment method.';
        return false;
    }

    if (!amount || amount < 0.01) {
        checkoutError.value = 'Enter a payment amount.';
        return false;
    }

    if (amount - remainingTotal.value > 0.005) {
        checkoutError.value = 'Payment is more than the remaining total.';
        return false;
    }

    if (method.is_cash) {
        const tendered = Number(draftTendered.value);

        if (!tendered) {
            checkoutError.value = 'Enter the amount tendered.';
            return false;
        }

        if (tendered + 0.005 < amount) {
            checkoutError.value = 'Amount tendered is less than the payment.';
            return false;
        }

        checkoutPayments.value.push({
            payment_method_id: method.id,
            name: method.name,
            is_cash: true,
            amount: amount.toFixed(2),
            amount_tendered: tendered.toFixed(2),
            change_due: (Math.round((tendered - amount) * 100) / 100).toFixed(2),
            reference_number: null,
        });
    } else {
        const reference = draftReference.value.trim();

        if (!reference) {
            checkoutError.value = 'Enter the reference number.';
            return false;
        }

        checkoutPayments.value.push({
            payment_method_id: method.id,
            name: method.name,
            is_cash: false,
            amount: amount.toFixed(2),
            amount_tendered: amount.toFixed(2),
            change_due: '0.00',
            reference_number: reference,
        });
    }

    resetDraftPayment();

    return true;
}

function removeCheckoutPayment(index) {
    checkoutPayments.value.splice(index, 1);
    resetDraftPayment();
}

function payWithDenomination(value) {
    const remaining = remainingTotal.value;

    if (splitCheckout.value && value + 0.005 < remaining) {
        draftAmount.value = value.toFixed(2);
        draftTendered.value = value.toFixed(2);
        addCheckoutPayment();
        return;
    }

    if (value + 0.005 < remaining) {
        checkoutError.value = 'Amount tendered is less than the payment.';
        return;
    }

    draftAmount.value = remaining.toFixed(2);
    draftTendered.value = value.toFixed(2);

    if (splitCheckout.value) {
        addCheckoutPayment();
        return;
    }

    charge();
}

function charge() {
    if (cart.value.length === 0) {
        return;
    }

    if (splitCheckout.value && remainingTotal.value > 0.005) {
        checkoutError.value = 'Cover the remaining total before charging.';
        return;
    }

    if (remainingTotal.value > 0.005) {
        if (!splitCheckout.value) {
            draftAmount.value = remainingTotal.value.toFixed(2);
        }

        if (!addCheckoutPayment() || remainingTotal.value > 0.005) {
            return;
        }
    }

    saleForm.items = saleItems();
    saleForm.payments = checkoutPayments.value.map((payment) => ({
        payment_method_id: payment.payment_method_id,
        amount: payment.amount,
        amount_tendered: payment.is_cash ? payment.amount_tendered : null,
        reference_number: payment.reference_number,
    }));
    saleForm.pos_open_order_id = activeOpenOrderId.value;

    if (saleForm.delivery_method_id === '') {
        saleForm.delivery_method_id = null;
    }

    saleForm.post(`${registerPath.value}/sales`, {
        ...registerPartial,
        onSuccess: (page) => {
            const success = page.props.flash?.checkout_success;

            if (success) {
                checkoutSuccess.value = success;
                chargeOpen.value = true;
            }

            applySoldQuantities(cart.value);
            cart.value = [];
            checkoutPayments.value = [];
            splitCheckout.value = false;
            activeOpenOrderId.value = null;
            activeOpenOrderName.value = '';
            saleForm.reset();
        },
    });
}

function openSave() {
    if (cart.value.length === 0) {
        return;
    }

    openOrderForm.name = activeOpenOrderName.value;
    saveOpen.value = true;
}

function saveOpenOrder() {
    if (cart.value.length === 0) {
        return;
    }

    openOrderForm.items = openOrderItems();

    const options = {
        ...registerPartial,
        onSuccess: () => {
            cart.value = [];
            activeOpenOrderId.value = null;
            activeOpenOrderName.value = '';
            saveOpen.value = false;
            openOrderForm.reset();
        },
    };

    if (activeOpenOrderId.value) {
        openOrderForm.put(`${registerPath.value}/open-orders/${activeOpenOrderId.value}`, options);
        return;
    }

    openOrderForm.post(`${registerPath.value}/open-orders`, options);
}

function resumeOpenOrder(order) {
    if (cart.value.length > 0) {
        return;
    }

    cart.value = (order.items ?? []).map((item) => {
        cartLineSeq += 1;
        const variant = props.variants.find((row) => row.id === item.catalog_item_variant_id);

        return {
            key: cartLineSeq,
            id: item.catalog_item_variant_id,
            product_name: item.product_name,
            variant_name: item.variant_name,
            price: Number(item.price),
            quantity: item.quantity,
            notes: item.notes ?? '',
            modifiers: item.modifiers ?? [],
            is_product: item.is_product,
            available: variant?.quantity ?? null,
        };
    });
    activeOpenOrderId.value = order.id;
    activeOpenOrderName.value = order.name ?? '';
    openOrdersOpen.value = false;
}

function removeOpenOrder(order) {
    deleteOpenOrderForm.delete(`${registerPath.value}/open-orders/${order.id}`, {
        ...registerPartial,
        onSuccess: () => {
            if (activeOpenOrderId.value === order.id) {
                activeOpenOrderId.value = null;
                activeOpenOrderName.value = '';
            }
        },
    });
}

function submitCash() {
    cashForm.post(`${registerPath.value}/cash-movements`, {
        ...registerPartial,
        onSuccess: () => {
            cashOpen.value = false;
            cashForm.reset();
            cashForm.type = 'cash_in';
        },
    });
}

function submitClose() {
    if (closeForm.remit_amount === '') {
        closeForm.remit_amount = countedCash.value.toFixed(2);
    }

    closeForm.post(`${registerPath.value}/shifts/${props.shift.id}/close`, {
        showProgress: false,
    });
}

watch(
    () => flash.value.open_drawer,
    (value) => {
        if (value) {
            openDrawer(false);
        }
    },
    { immediate: true },
);

watch(
    () => flash.value.checkout_success,
    (value) => {
        if (value) {
            checkoutSuccess.value = value;
            chargeOpen.value = true;
        }
    },
    { immediate: true },
);

watch(
    () => saleForm.delivery_method_id,
    (value) => {
        const method = props.deliveryMethods.find((item) => String(item.id) === String(value));
        saleForm.delivery_fee = method?.fee ?? '';
    },
);

watch(countedCash, (value) => {
    if (!closeOpen.value) {
        return;
    }

    closeForm.remit_amount = value.toFixed(2);
});
</script>

<template>
    <Head title="POS" />

    <Teleport defer to="#pos-header-search">
        <BarcodeInput
            v-if="shift"
            v-model="search"
            id="pos-search"
            placeholder="Search or scan barcode"
            @scanned="onBarcode"
        />
    </Teleport>

    <Teleport defer to="#admin-header-actions">
        <template v-if="!shift">
            <button
                class="h-10 shrink-0 rounded-xl bg-pine px-3 text-sm font-medium whitespace-nowrap text-foam disabled:opacity-70"
                form="open-shift-form"
                type="submit"
                :disabled="openForm.processing"
            >
                {{ openForm.processing ? 'Opening…' : 'Open Shift' }}
            </button>
        </template>
        <template v-else-if="shift && closeOpen">
            <button
                class="h-10 shrink-0 rounded-xl border border-mist px-3 text-sm font-medium whitespace-nowrap dark:border-white/15 disabled:opacity-70"
                form="close-shift-form"
                type="submit"
                :disabled="closeForm.processing"
            >
                {{ closeForm.processing ? 'Closing…' : 'Close Shift' }}
            </button>
            <button
                class="h-10 shrink-0 rounded-xl px-3 text-sm font-medium whitespace-nowrap"
                type="button"
                @click="closeOpen = false"
            >
                Cancel
            </button>
        </template>
        <template v-else-if="shift">
            <button
                class="h-10 shrink-0 rounded-xl border border-mist px-3 text-sm font-medium whitespace-nowrap dark:border-white/15"
                type="button"
                @click="closeOpen = true"
            >
                Close Shift
            </button>
        </template>
    </Teleport>

    <Teleport defer to="#pos-header-menu-items">
        <template v-if="shift">
            <button
                v-if="canOpenDrawer"
                class="px-4 py-2.5 text-left text-sm font-medium hover:bg-mist/70 disabled:opacity-70 dark:hover:bg-white/10"
                type="button"
                :disabled="openingDrawer"
                @click="openDrawer(true)"
            >
                {{ openingDrawer ? 'Opening…' : 'Open Drawer' }}
            </button>
            <button
                class="px-4 py-2.5 text-left text-sm font-medium hover:bg-mist/70 dark:hover:bg-white/10"
                type="button"
                @click="cashOpen = true"
            >
                Cash In / Out
            </button>
            <button
                class="px-4 py-2.5 text-left text-sm font-medium hover:bg-mist/70 dark:hover:bg-white/10"
                type="button"
                @click="salesOpen = true"
            >
                Today's Transactions
            </button>
        </template>
    </Teleport>

    <div class="relative flex min-h-0 flex-1 flex-col gap-3 sm:gap-4">
        <form
            v-if="!shift"
            id="open-shift-form"
            class="grid min-h-0 flex-1 grid-cols-2 overflow-hidden rounded-2xl border border-mist bg-foam dark:border-white/10 dark:bg-ink"
            @submit.prevent="openShift"
        >
            <section class="flex min-h-0 flex-col gap-6 overflow-y-auto border-r border-mist p-4 sm:p-6 dark:border-white/10">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">Open Shift · {{ branch.name }}</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">
                        Count the starting cash. Saving this will open the cash drawer.
                    </p>
                </div>
                <div class="flex flex-col gap-2 border-t border-mist pt-6 dark:border-white/10">
                    <p class="text-sm text-ink/60 dark:text-foam/60">Starting cash</p>
                    <p class="text-4xl font-medium sm:text-5xl">{{ formatPeso(startingCash) }}</p>
                </div>
                <p v-if="openForm.errors.denominations" class="text-sm text-danger">{{ openForm.errors.denominations }}</p>
                <p v-if="openForm.errors.branch_id" class="text-sm text-danger">{{ openForm.errors.branch_id }}</p>
            </section>
            <section class="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 sm:p-6">
                <p class="text-sm font-medium">Cash count</p>
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr>
                            <th class="py-2 font-medium">Denomination</th>
                            <th class="py-2 font-medium">Quantity</th>
                            <th class="py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in openForm.denominations" :key="row.denomination">
                            <td class="py-2">{{ formatPeso(row.denomination) }}</td>
                            <td class="py-2">
                                <div class="flex items-center gap-1">
                                    <input
                                        v-model.number="openForm.denominations[index].quantity"
                                        class="h-10 w-20 rounded-xl border border-mist bg-white px-3 text-center dark:border-white/10 dark:bg-white/5"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        type="text"
                                        @focus="selectInputValue"
                                        @click="selectInputValue"
                                    />
                                    <div class="flex flex-col gap-0.5">
                                        <button
                                            class="flex h-5 w-7 items-center justify-center rounded-md border border-mist text-xs font-medium dark:border-white/15"
                                            type="button"
                                            aria-label="Increase quantity"
                                            @click="changeOpenQty(index, 1)"
                                        >
                                            ▲
                                        </button>
                                        <button
                                            class="flex h-5 w-7 items-center justify-center rounded-md border border-mist text-xs font-medium disabled:opacity-40 dark:border-white/15"
                                            type="button"
                                            aria-label="Decrease quantity"
                                            :disabled="Number(row.quantity || 0) <= 0"
                                            @click="changeOpenQty(index, -1)"
                                        >
                                            ▼
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2 text-right">
                                {{ formatPeso(Number(row.denomination) * Number(row.quantity || 0)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </form>

        <div
            v-else
            class="grid min-h-0 flex-1 grid-cols-1 grid-rows-[minmax(0,1fr)_minmax(14rem,48dvh)] gap-3 sm:gap-4 min-[48rem]:grid-cols-[minmax(0,1fr)_minmax(16rem,22rem)] min-[48rem]:grid-rows-none"
        >
            <section class="@container flex min-h-0 flex-col gap-3">
                <div class="flex shrink-0 gap-2 overflow-x-auto pb-0.5">
                    <button
                        class="h-10 shrink-0 rounded-xl px-3 text-sm font-medium whitespace-nowrap"
                        :class="selectedCategoryId === null ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                        type="button"
                        @click="selectedCategoryId = null"
                    >
                        All
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        class="h-10 shrink-0 rounded-xl px-3 text-sm font-medium whitespace-nowrap"
                        :class="selectedCategoryId === category.id ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                        type="button"
                        @click="selectedCategoryId = category.id"
                    >
                        {{ category.name }}
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="grid grid-cols-2 gap-3 @min-[21rem]:grid-cols-3 @min-[40rem]:grid-cols-4">
                    <button
                        v-for="variant in filteredVariants"
                        :key="variant.id"
                        class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-2.5 text-left sm:gap-3 sm:p-3 dark:border-white/10 dark:bg-white/5"
                        type="button"
                        @click="addVariant(variant)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 truncate text-xs font-medium text-ink/55 dark:text-foam/55">
                                {{ variant.shop_category_name }}
                            </p>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    isInStock(variant)
                                        ? 'bg-pine/10 text-pine dark:bg-pine/20 dark:text-sand'
                                        : 'bg-danger/10 text-danger'
                                "
                            >
                                {{ stockLabel(variant) }}
                            </span>
                        </div>
                        <div class="aspect-square overflow-hidden rounded-xl bg-mist/70 dark:bg-white/10">
                            <img
                                v-if="variant.image_url"
                                :src="variant.image_url"
                                :alt="variant.variant_name"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <p class="truncate font-medium">{{ variant.product_name }}</p>
                            <p class="truncate text-xs text-ink/50 dark:text-foam/50">{{ variant.variant_name }}</p>
                            <p class="text-sm font-medium">{{ variant.price_label }}</p>
                        </div>
                    </button>
                    <p v-if="filteredVariants.length === 0" class="col-span-full text-sm text-ink/60 dark:text-foam/60">No variants match.</p>
                </div>
                </div>
            </section>

            <div class="flex min-h-0 flex-col gap-3 overflow-hidden">
                <aside class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                    <div class="shrink-0 border-b border-mist px-4 py-3 sm:px-5 dark:border-white/10">
                        <p class="truncate text-sm font-medium">{{ branch.name }}</p>
                        <p class="truncate text-xs text-ink/55 dark:text-foam/55">
                            {{ employee?.name }} · #{{ employee?.employee_number }}
                        </p>
                    </div>
                    <div v-if="cart.length === 0" class="min-h-0 flex-1 overflow-y-auto px-4 py-6 text-sm text-ink/60 sm:px-5">
                        Add a variant to the cart.
                    </div>
                    <ul v-else class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto px-4 py-3 sm:px-5">
                        <li v-for="line in cart" :key="line.key" class="flex items-start justify-between gap-2">
                            <button class="min-w-0 flex-1 text-left" type="button" @click="openEditLine(line)">
                                <p class="truncate text-sm font-medium">{{ line.product_name }}</p>
                                <p class="truncate text-xs text-ink/50">{{ line.variant_name }}</p>
                                <p
                                    v-for="modifier in line.modifiers"
                                    :key="`${line.key}-${modifier.modifier_id}`"
                                    class="truncate text-xs text-ink/50"
                                >
                                    {{ modifier.name }}: {{ modifier.option }}
                                </p>
                                <p v-if="line.notes" class="truncate text-xs text-ink/50">{{ line.notes }}</p>
                                <p class="text-xs">{{ formatPeso(line.price) }}</p>
                            </button>
                            <div class="flex shrink-0 items-center gap-1.5" @click.stop>
                                <button class="h-8 w-8 rounded-lg border border-mist" type="button" @click="changeQty(line, -1)">-</button>
                                <input
                                    :value="line.quantity"
                                    class="h-8 w-12 rounded-lg border border-mist bg-white text-center text-sm dark:border-white/10 dark:bg-white/5"
                                    min="1"
                                    :max="line.is_product && line.available !== null ? line.available : undefined"
                                    type="number"
                                    @change="setQty(line, $event.target.value)"
                                />
                                <button class="h-8 w-8 rounded-lg border border-mist" type="button" @click="changeQty(line, 1)">+</button>
                                <button
                                    class="h-8 w-8 rounded-lg border border-mist text-sm font-medium"
                                    type="button"
                                    aria-label="Remove item"
                                    @click="removeLine(line)"
                                >
                                    ×
                                </button>
                            </div>
                        </li>
                    </ul>
                </aside>
                <aside class="flex shrink-0 flex-col gap-3 rounded-2xl border border-mist bg-white p-4 sm:p-5 dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium">Order Summary</p>
                    <div class="flex flex-col gap-1 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span>{{ formatPeso(cartSubtotal) }}</span></div>
                        <div class="flex justify-between"><span>Delivery</span><span>{{ formatPeso(deliveryFee) }}</span></div>
                        <div class="flex justify-between"><span>Tax</span><span>{{ formatPeso(taxTotal) }}</span></div>
                        <div class="flex justify-between font-medium"><span>Total</span><span>{{ formatPeso(saleTotal) }}</span></div>
                    </div>
                    <p v-if="openOrderForm.errors.items" class="text-sm text-danger">{{ openOrderForm.errors.items }}</p>
                    <p v-if="openOrderForm.errors.status" class="text-sm text-danger">{{ openOrderForm.errors.status }}</p>
                    <div class="flex overflow-hidden rounded-xl border border-mist dark:border-white/15">
                        <button
                            class="h-11 flex-1 text-sm font-medium"
                            type="button"
                            @click="cart.length > 0 ? openSave() : (openOrdersOpen = true)"
                        >
                            {{ cart.length > 0 ? 'Save' : 'Open Orders' }}
                        </button>
                        <button
                            class="h-11 flex-1 bg-pine text-sm font-medium text-foam disabled:opacity-50"
                            type="button"
                            :disabled="cart.length === 0"
                            @click="openCharge"
                        >
                            Charge
                        </button>
                    </div>
                </aside>
            </div>
        </div>

        <form
            v-if="closeOpen && shift"
            id="close-shift-form"
            class="absolute inset-0 z-40 grid grid-cols-2 overflow-hidden rounded-2xl border border-mist bg-foam dark:border-white/10 dark:bg-ink"
            @submit.prevent="submitClose"
        >
            <section class="flex min-h-0 flex-col gap-6 overflow-y-auto border-r border-mist p-4 sm:p-6 dark:border-white/10">
                <p class="text-sm font-medium">Cash breakdown</p>
                <dl class="flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink/60 dark:text-foam/60">Starting</dt>
                        <dd>{{ shift.starting_cash_label }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink/60 dark:text-foam/60">Cash sales</dt>
                        <dd>{{ shift.cash_sales_total_label }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink/60 dark:text-foam/60">Cash in</dt>
                        <dd>{{ shift.cash_in_total_label }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink/60 dark:text-foam/60">Cash out</dt>
                        <dd>{{ shift.cash_out_total_label }}</dd>
                    </div>
                </dl>
                <div class="flex flex-col gap-2 border-t border-mist pt-6 dark:border-white/10">
                    <p class="text-sm text-ink/60 dark:text-foam/60">Expected</p>
                    <p class="text-4xl font-medium sm:text-5xl">{{ shift.expected_cash_label }}</p>
                </div>
                <div class="flex flex-col gap-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <p class="text-sm text-ink/60 dark:text-foam/60">Counted</p>
                        <p class="text-2xl font-medium">{{ formatPeso(countedCash) }}</p>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <p class="text-sm text-ink/60 dark:text-foam/60">Variance</p>
                        <p class="text-2xl font-medium" :class="cashCountComplete ? '' : 'text-danger'">{{ formatPeso(variance) }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="remit-amount">Amount to remit</label>
                    <input
                        id="remit-amount"
                        v-model="closeForm.remit_amount"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm dark:border-white/10 dark:bg-white/5"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                </div>
                <p v-if="!cashCountComplete" class="text-sm text-danger">Cash count is not complete. Confirm the variance to close.</p>
                <label v-if="!cashCountComplete" class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="closeForm.confirm_variance" class="h-4 w-4 accent-pine" type="checkbox" />
                    Variance is correct
                </label>
                <p v-if="closeForm.errors.counted_cash" class="text-sm text-danger">{{ closeForm.errors.counted_cash }}</p>
                <p v-if="closeForm.errors.remit_amount" class="text-sm text-danger">{{ closeForm.errors.remit_amount }}</p>
            </section>
            <section class="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 sm:p-6">
                <p class="text-sm font-medium">Cash count</p>
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr>
                            <th class="py-2 font-medium">Denomination</th>
                            <th class="py-2 font-medium">Quantity</th>
                            <th class="py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in closeForm.denominations" :key="row.denomination">
                            <td class="py-2">{{ formatPeso(row.denomination) }}</td>
                            <td class="py-2">
                                <div class="flex items-center gap-1">
                                    <input
                                        v-model.number="closeForm.denominations[index].quantity"
                                        class="h-10 w-20 rounded-xl border border-mist bg-white px-3 text-center dark:border-white/10 dark:bg-white/5"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        type="text"
                                        @focus="selectInputValue"
                                        @click="selectInputValue"
                                    />
                                    <div class="flex flex-col gap-0.5">
                                        <button
                                            class="flex h-5 w-7 items-center justify-center rounded-md border border-mist text-xs font-medium dark:border-white/15"
                                            type="button"
                                            aria-label="Increase quantity"
                                            @click="changeCloseQty(index, 1)"
                                        >
                                            ▲
                                        </button>
                                        <button
                                            class="flex h-5 w-7 items-center justify-center rounded-md border border-mist text-xs font-medium disabled:opacity-40 dark:border-white/15"
                                            type="button"
                                            aria-label="Decrease quantity"
                                            :disabled="Number(row.quantity || 0) <= 0"
                                            @click="changeCloseQty(index, -1)"
                                        >
                                            ▼
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2 text-right">
                                {{ formatPeso(Number(row.denomination) * Number(row.quantity || 0)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </form>
    </div>

    <div v-if="customizeOpen && customizing" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[90dvh] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-medium">{{ customizing.product_name }}</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">{{ customizing.variant_name }}</p>
                </div>
                <button class="text-sm font-medium" type="button" @click="closeCustomize">Close</button>
            </div>

            <section v-for="modifier in customizing.modifiers" :key="modifier.id" class="flex flex-col gap-2">
                <p class="text-sm font-medium">{{ modifier.name }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in modifier.options"
                        :key="option.name"
                        class="h-10 rounded-xl px-3 text-sm font-medium"
                        :class="
                            customSelections[modifier.id] === option.name
                                ? 'bg-pine text-foam'
                                : 'border border-mist dark:border-white/15'
                        "
                        type="button"
                        @click="selectOption(modifier.id, option.name)"
                    >
                        {{ option.name }}
                        <span v-if="Number(option.price) > 0" class="text-xs">{{ option.price_label }}</span>
                    </button>
                </div>
            </section>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="pos-item-notes">Notes</label>
                <textarea
                    id="pos-item-notes"
                    v-model="customNotes"
                    class="min-h-20 rounded-xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5"
                    maxlength="500"
                    placeholder="Add notes"
                />
            </div>

            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium">Quantity</p>
                <div class="flex items-center gap-2">
                    <button class="h-8 w-8 rounded-lg border border-mist" type="button" @click="customQty = Math.max(1, customQty - 1)">
                        -
                    </button>
                    <input
                        v-model.number="customQty"
                        class="h-10 w-16 rounded-xl border border-mist bg-white text-center text-sm dark:border-white/10 dark:bg-white/5"
                        min="1"
                        :max="customizing.is_product && customizing.quantity ? customizing.quantity : undefined"
                        type="number"
                    />
                    <button
                        class="h-8 w-8 rounded-lg border border-mist"
                        type="button"
                        @click="
                            customQty =
                                customizing.is_product && customizing.quantity
                                    ? Math.min(customizing.quantity, customQty + 1)
                                    : customQty + 1
                        "
                    >
                        +
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium">{{ formatPeso(customUnitPrice * Math.max(1, Number(customQty) || 1)) }}</p>
                <button class="h-11 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="confirmCustomize">
                    {{ editingLineKey ? 'Update item' : 'Add to cart' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="chargeOpen" class="fixed inset-0 z-50 flex flex-col bg-foam dark:bg-ink">
        <div class="flex shrink-0 items-center justify-between gap-4 border-b border-mist px-4 py-4 sm:px-6 dark:border-white/10">
            <h2 class="text-lg font-medium">Checkout · {{ checkoutSuccess ? checkoutSuccess.total : formatPeso(saleTotal) }}</h2>
            <button class="text-sm font-medium" type="button" @click="closeCheckout">Close</button>
        </div>
        <div class="grid min-h-0 flex-1 grid-cols-2">
            <section class="min-h-0 overflow-y-auto border-r border-mist p-4 sm:p-6 dark:border-white/10">
                <p class="text-sm font-medium">Order</p>
                <p v-if="checkoutSuccess" class="mt-6 text-sm text-ink/60">This sale is complete.</p>
                <ul v-else class="mt-3 flex flex-col gap-3">
                    <li v-for="line in cart" :key="line.key" class="flex items-start justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ line.product_name }}</p>
                            <p class="truncate text-xs text-ink/50">{{ line.quantity }} × {{ line.variant_name }}</p>
                        </div>
                        <p class="shrink-0">{{ formatPeso(Number(line.price) * line.quantity) }}</p>
                    </li>
                </ul>
                <template v-if="!checkoutSuccess">
                    <div class="mt-6 flex flex-col gap-2">
                        <p class="text-sm font-medium">Delivery</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                class="h-11 rounded-xl px-3 text-sm font-medium"
                                :class="saleForm.delivery_method_id === '' || saleForm.delivery_method_id === null
                                    ? 'bg-pine text-foam'
                                    : 'border border-mist dark:border-white/15'"
                                type="button"
                                @click="saleForm.delivery_method_id = ''"
                            >
                                None
                            </button>
                            <button
                                v-for="method in deliveryMethods"
                                :key="method.id"
                                class="h-11 rounded-xl px-3 text-sm font-medium"
                                :class="String(saleForm.delivery_method_id) === String(method.id)
                                    ? 'bg-pine text-foam'
                                    : 'border border-mist dark:border-white/15'"
                                type="button"
                                @click="saleForm.delivery_method_id = method.id"
                            >
                                {{ method.name }}
                            </button>
                        </div>
                        <input
                            v-if="saleForm.delivery_method_id"
                            v-model="saleForm.delivery_fee"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm"
                            min="0"
                            step="0.01"
                            type="number"
                            placeholder="Delivery fee"
                        />
                    </div>
                    <div class="mt-6 flex flex-col gap-1 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span>{{ formatPeso(cartSubtotal) }}</span></div>
                        <div class="flex justify-between"><span>Delivery</span><span>{{ formatPeso(deliveryFee) }}</span></div>
                        <div class="flex justify-between"><span>Tax</span><span>{{ formatPeso(taxTotal) }}</span></div>
                        <div class="flex justify-between font-medium"><span>Total</span><span>{{ formatPeso(saleTotal) }}</span></div>
                        <template v-if="splitCheckout">
                            <div class="flex justify-between"><span>Paid</span><span>{{ formatPeso(paidTotal) }}</span></div>
                            <div class="flex justify-between font-medium"><span>Remaining</span><span>{{ formatPeso(remainingTotal) }}</span></div>
                        </template>
                    </div>
                </template>
            </section>
            <section class="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 sm:p-6">
                <div v-if="checkoutSuccess" class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                    <p class="text-sm font-medium text-pine dark:text-sand">Payment successful</p>
                    <p class="text-4xl font-medium sm:text-5xl">{{ checkoutSuccess.total }}</p>
                    <p v-if="checkoutSuccess.change" class="text-lg">Change {{ checkoutSuccess.change }}</p>
                    <button
                        class="mt-4 h-11 rounded-xl bg-pine px-6 text-sm font-medium text-foam"
                        type="button"
                        @click="closeCheckout"
                    >
                        Done
                    </button>
                </div>
                <template v-else>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium">Payments</p>
                        <button
                            class="h-10 rounded-xl px-3 text-sm font-medium"
                            :class="splitCheckout ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                            type="button"
                            @click="toggleSplitCheckout"
                        >
                            Split Payment
                        </button>
                    </div>
                    <p class="text-center text-4xl font-medium sm:text-5xl">
                        {{ formatPeso(splitCheckout ? remainingTotal : saleTotal) }}
                    </p>
                    <p class="text-center text-sm text-ink/55 dark:text-foam/55">
                        {{ splitCheckout ? 'Remaining' : 'Amount due' }}
                    </p>
                    <ul v-if="splitCheckout && checkoutPayments.length > 0" class="flex flex-col gap-2">
                        <li
                            v-for="(payment, index) in checkoutPayments"
                            :key="`${payment.payment_method_id}-${index}`"
                            class="flex items-start justify-between gap-3 rounded-xl border border-mist px-3 py-2 text-sm dark:border-white/10"
                        >
                            <div class="min-w-0">
                                <p class="font-medium">{{ payment.name }} · {{ formatPeso(payment.amount) }}</p>
                                <p v-if="payment.is_cash" class="text-xs text-ink/50">
                                    Cash given {{ formatPeso(payment.amount_tendered) }} · Change {{ formatPeso(payment.change_due) }}
                                </p>
                                <p v-else class="text-xs text-ink/50">Ref {{ payment.reference_number }}</p>
                            </div>
                            <button class="text-sm font-medium" type="button" @click="removeCheckoutPayment(index)">Remove</button>
                        </li>
                    </ul>
                    <div v-if="remainingTotal > 0.005" class="flex flex-col gap-3">
                        <div class="flex flex-col gap-2">
                            <p class="text-sm font-medium">Method</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    class="h-11 rounded-xl px-3 text-sm font-medium"
                                    :class="String(draftPaymentMethodId) === String(method.id)
                                        ? 'bg-pine text-foam'
                                        : 'border border-mist dark:border-white/15'"
                                    type="button"
                                    @click="draftPaymentMethodId = method.id"
                                >
                                    {{ method.name }}
                                </button>
                            </div>
                        </div>
                        <p v-if="draftPaymentMethod?.instructions" class="text-sm text-ink/60">{{ draftPaymentMethod.instructions }}</p>
                        <div v-if="splitCheckout" class="flex flex-col gap-2">
                            <label class="text-sm font-medium" for="pos-pay-amount">This payment</label>
                            <input
                                id="pos-pay-amount"
                                v-model="draftAmount"
                                class="h-11 rounded-xl border border-mist bg-white px-4 text-sm"
                                min="0.01"
                                step="0.01"
                                type="number"
                                placeholder="Amount"
                            />
                        </div>
                        <div v-if="draftPaymentMethod?.is_cash" class="flex flex-col gap-2">
                            <label class="text-sm font-medium" for="pos-tendered">Cash given</label>
                            <div class="flex gap-2">
                                <input
                                    id="pos-tendered"
                                    v-model="draftTendered"
                                    class="h-11 min-w-0 flex-1 rounded-xl border border-mist bg-white px-4 text-sm"
                                    min="0"
                                    step="0.01"
                                    type="number"
                                    placeholder="Cash given"
                                />
                                <button
                                    class="h-11 shrink-0 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                                    type="button"
                                    :disabled="saleForm.processing"
                                    @click="splitCheckout ? addCheckoutPayment() : charge()"
                                >
                                    {{ saleForm.processing ? 'Saving…' : (splitCheckout ? 'Add' : 'Charge') }}
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="bill in checkoutBillValues"
                                    :key="bill"
                                    class="h-14 rounded-xl border border-mist text-sm font-medium dark:border-white/15"
                                    type="button"
                                    :disabled="saleForm.processing"
                                    @click="payWithDenomination(bill)"
                                >
                                    {{ formatPeso(bill) }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="flex flex-col gap-2">
                            <label class="text-sm font-medium" for="pos-reference">Reference number</label>
                            <div class="flex gap-2">
                                <input
                                    id="pos-reference"
                                    v-model="draftReference"
                                    class="h-11 min-w-0 flex-1 rounded-xl border border-mist bg-white px-4 text-sm"
                                    type="text"
                                    placeholder="Reference number"
                                />
                                <button
                                    class="h-11 shrink-0 rounded-xl bg-pine px-5 text-sm font-medium text-foam disabled:opacity-70"
                                    type="button"
                                    :disabled="saleForm.processing"
                                    @click="splitCheckout ? addCheckoutPayment() : charge()"
                                >
                                    {{ saleForm.processing ? 'Saving…' : (splitCheckout ? 'Add' : 'Charge') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="checkoutError" class="text-sm text-danger">{{ checkoutError }}</p>
                    <p v-if="saleForm.errors.items" class="text-sm text-danger">{{ saleForm.errors.items }}</p>
                    <p v-if="saleForm.errors.status" class="text-sm text-danger">{{ saleForm.errors.status }}</p>
                    <p v-if="saleForm.errors.payments" class="text-sm text-danger">{{ saleForm.errors.payments }}</p>
                    <p v-if="saleForm.errors['payments.0.reference_number']" class="text-sm text-danger">
                        {{ saleForm.errors['payments.0.reference_number'] }}
                    </p>
                    <p v-if="saleForm.errors['payments.0.amount_tendered']" class="text-sm text-danger">
                        {{ saleForm.errors['payments.0.amount_tendered'] }}
                    </p>
                    <button
                        v-if="splitCheckout && remainingTotal <= 0.005 && checkoutPayments.length > 0"
                        class="mt-auto h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                        type="button"
                        :disabled="saleForm.processing"
                        @click="charge"
                    >
                        {{ saleForm.processing ? 'Saving…' : 'Charge' }}
                    </button>
                </template>
            </section>
        </div>
    </div>

    <div v-if="saveOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ activeOpenOrderId ? 'Update open order' : 'Save open order' }}</h2>
                <button class="text-sm font-medium" type="button" @click="saveOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="saveOpenOrder">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="open-order-name">Name</label>
                    <input
                        id="open-order-name"
                        v-model="openOrderForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm dark:border-white/10 dark:bg-white/5"
                        maxlength="255"
                        required
                        type="text"
                        placeholder="Table 4"
                    />
                    <p v-if="openOrderForm.errors.name" class="text-sm text-danger">{{ openOrderForm.errors.name }}</p>
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="openOrderForm.processing"
                >
                    {{ openOrderForm.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="openOrdersOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[90dvh] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Open Orders</h2>
                <button class="text-sm font-medium" type="button" @click="openOrdersOpen = false">Close</button>
            </div>
            <p class="text-sm text-ink/60">Select an open order to load it into the cart.</p>
            <p v-if="openOrders.length === 0" class="text-sm text-ink/60">No open orders.</p>
            <article
                v-for="order in openOrders"
                :key="order.id"
                class="flex flex-col gap-2 border-b border-mist pb-3 dark:border-white/10"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium">{{ order.name }} · {{ order.subtotal_label }}</p>
                        <p class="text-xs text-ink/50">{{ order.item_count }} items · {{ order.saved_at }} · {{ order.saved_by_name }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button
                            class="h-9 rounded-xl border border-mist px-3 text-sm font-medium disabled:opacity-50"
                            type="button"
                            :disabled="cart.length > 0"
                            @click="resumeOpenOrder(order)"
                        >
                            Resume
                        </button>
                        <button
                            class="h-9 rounded-xl border border-mist px-3 text-sm font-medium"
                            type="button"
                            @click="removeOpenOrder(order)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </div>

    <div v-if="salesOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[80vh] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Today's Transactions</h2>
                <div class="flex items-center gap-4">
                    <p class="text-lg font-medium">{{ todayTransactionsTotal }}</p>
                    <button class="text-sm font-medium" type="button" @click="salesOpen = false">Close</button>
                </div>
            </div>
            <p v-if="todayTransactions.length === 0" class="text-sm text-ink/60">No transactions yet today.</p>
            <article v-for="transaction in todayTransactions" :key="transaction.key" class="border-b border-mist pb-3 dark:border-white/10">
                <div class="flex items-start justify-between gap-3">
                    <p class="min-w-0 truncate font-medium">{{ transaction.title }}</p>
                    <p class="shrink-0 font-medium">{{ transaction.amount_label }}</p>
                </div>
                <p class="text-xs text-ink/50">
                    {{ transaction.time }} · {{ transaction.actor_name }}
                    <span v-if="transaction.detail"> · {{ transaction.detail }}</span>
                </p>
                <p v-for="(payment, index) in transaction.payments" :key="`${transaction.key}-pay-${index}`" class="text-xs text-ink/50">
                    {{ payment.name }} {{ payment.amount_label }}
                    <span v-if="payment.reference_number"> · Ref {{ payment.reference_number }}</span>
                </p>
                <p
                    v-for="item in transaction.items"
                    :key="item.id"
                    class="mt-1 text-xs text-ink/60"
                >
                    {{ item.quantity }} × {{ item.label }}
                    <span v-if="item.modifiers?.length">
                        · {{ item.modifiers.map((modifier) => `${modifier.name}: ${modifier.option}`).join(', ') }}
                    </span>
                    <span v-if="item.notes"> · {{ item.notes }}</span>
                </p>
            </article>
        </div>
    </div>

    <div v-if="cashOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Cash In & Cash Out</h2>
                <button class="text-sm font-medium" type="button" @click="cashOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitCash">
                <select
                    v-model="cashForm.type"
                    class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm dark:border-white/10 dark:bg-white/5"
                >
                    <option value="cash_in">Cash in</option>
                    <option value="cash_out">Cash out</option>
                </select>
                <input
                    v-model="cashForm.amount"
                    class="h-11 rounded-xl border border-mist bg-white px-4 text-sm"
                    min="0.01"
                    required
                    step="0.01"
                    type="number"
                    placeholder="Amount"
                />
                <input
                    v-model="cashForm.reason"
                    class="h-11 rounded-xl border border-mist bg-white px-4 text-sm"
                    required
                    type="text"
                    placeholder="Reason"
                />
                <p v-if="cashForm.errors.amount" class="text-sm text-danger">{{ cashForm.errors.amount }}</p>
                <p v-if="cashForm.errors.reason" class="text-sm text-danger">{{ cashForm.errors.reason }}</p>
                <button class="h-11 rounded-xl bg-pine text-sm font-medium text-foam" type="submit" :disabled="cashForm.processing">
                    Save and open drawer
                </button>
            </form>
        </div>
    </div>
</template>
