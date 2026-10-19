<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    taxFees: {
        type: Array,
        required: true,
    },
    paymentMethods: {
        type: Array,
        required: true,
    },
    deliveryMethods: {
        type: Array,
        required: true,
    },
    taxFeeTypes: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const taxModalOpen = ref(false);
const editingTaxFee = ref(null);
const taxForm = useForm({
    name: '',
    type: 'percent',
    rate: '',
    is_active: true,
});

const paymentModalOpen = ref(false);
const editingPaymentMethod = ref(null);
const paymentForm = useForm({
    name: '',
    instructions: '',
    is_cash: false,
    is_active: true,
});

const deliveryModalOpen = ref(false);
const editingDeliveryMethod = ref(null);
const deliveryForm = useForm({
    name: '',
    fee: '',
    notes: '',
    is_active: true,
});

function openCreateTaxFee() {
    editingTaxFee.value = null;
    taxForm.reset();
    taxForm.type = 'percent';
    taxForm.is_active = true;
    taxForm.clearErrors();
    taxModalOpen.value = true;
}

function openEditTaxFee(taxFee) {
    editingTaxFee.value = taxFee;
    taxForm.name = taxFee.name;
    taxForm.type = taxFee.type;
    taxForm.rate = taxFee.rate;
    taxForm.is_active = taxFee.is_active;
    taxForm.clearErrors();
    taxModalOpen.value = true;
}

function submitTaxFee() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            taxModalOpen.value = false;
            taxForm.reset();
            editingTaxFee.value = null;
        },
    };

    if (editingTaxFee.value) {
        taxForm.put(`/backoffice/shop/tax-fees/${editingTaxFee.value.id}`, options);
        return;
    }

    taxForm.post('/backoffice/shop/tax-fees', options);
}

function deleteTaxFee(taxFee) {
    if (!window.confirm(`Delete ${taxFee.name}?`)) {
        return;
    }

    router.delete(`/backoffice/shop/tax-fees/${taxFee.id}`, {
        preserveScroll: true,
    });
}

function openCreatePaymentMethod() {
    editingPaymentMethod.value = null;
    paymentForm.reset();
    paymentForm.is_cash = false;
    paymentForm.is_active = true;
    paymentForm.clearErrors();
    paymentModalOpen.value = true;
}

function openEditPaymentMethod(paymentMethod) {
    editingPaymentMethod.value = paymentMethod;
    paymentForm.name = paymentMethod.name;
    paymentForm.instructions = paymentMethod.instructions ?? '';
    paymentForm.is_cash = paymentMethod.is_cash;
    paymentForm.is_active = paymentMethod.is_active;
    paymentForm.clearErrors();
    paymentModalOpen.value = true;
}

function submitPaymentMethod() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            paymentModalOpen.value = false;
            paymentForm.reset();
            editingPaymentMethod.value = null;
        },
    };

    if (editingPaymentMethod.value) {
        paymentForm.put(`/backoffice/shop/payment-methods/${editingPaymentMethod.value.id}`, options);
        return;
    }

    paymentForm.post('/backoffice/shop/payment-methods', options);
}

function deletePaymentMethod(paymentMethod) {
    if (!window.confirm(`Delete ${paymentMethod.name}?`)) {
        return;
    }

    router.delete(`/backoffice/shop/payment-methods/${paymentMethod.id}`, {
        preserveScroll: true,
    });
}

function openCreateDeliveryMethod() {
    editingDeliveryMethod.value = null;
    deliveryForm.reset();
    deliveryForm.is_active = true;
    deliveryForm.clearErrors();
    deliveryModalOpen.value = true;
}

function openEditDeliveryMethod(deliveryMethod) {
    editingDeliveryMethod.value = deliveryMethod;
    deliveryForm.name = deliveryMethod.name;
    deliveryForm.fee = deliveryMethod.fee ?? '';
    deliveryForm.notes = deliveryMethod.notes ?? '';
    deliveryForm.is_active = deliveryMethod.is_active;
    deliveryForm.clearErrors();
    deliveryModalOpen.value = true;
}

function submitDeliveryMethod() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            deliveryModalOpen.value = false;
            deliveryForm.reset();
            editingDeliveryMethod.value = null;
        },
    };

    if (editingDeliveryMethod.value) {
        deliveryForm.put(`/backoffice/shop/delivery-methods/${editingDeliveryMethod.value.id}`, options);
        return;
    }

    deliveryForm.post('/backoffice/shop/delivery-methods', options);
}

function deleteDeliveryMethod(deliveryMethod) {
    if (!window.confirm(`Delete ${deliveryMethod.name}?`)) {
        return;
    }

    router.delete(`/backoffice/shop/delivery-methods/${deliveryMethod.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Settings" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="grid gap-6 xl:grid-cols-3">
            <section class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-medium">Taxes & Fees</h2>
                    <button
                        class="h-10 shrink-0 rounded-xl bg-pine px-3 text-sm font-medium text-foam"
                        type="button"
                        @click="openCreateTaxFee"
                    >
                        Add
                    </button>
                </div>
                <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Amount</th>
                                <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="taxFees.length === 0">
                                <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="3">No taxes or fees yet.</td>
                            </tr>
                            <tr
                                v-for="taxFee in taxFees"
                                :key="taxFee.id"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ taxFee.name }}</p>
                                    <p class="text-xs text-ink/50 dark:text-foam/50">
                                        {{ taxFee.type_label }} · {{ taxFee.is_active ? 'Active' : 'Inactive' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ taxFee.amount_label }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <button class="font-medium text-pine dark:text-sand" type="button" @click="openEditTaxFee(taxFee)">
                                            Edit
                                        </button>
                                        <button class="font-medium text-danger" type="button" @click="deleteTaxFee(taxFee)">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-medium">Payment Methods</h2>
                    <button
                        class="h-10 shrink-0 rounded-xl bg-pine px-3 text-sm font-medium text-foam"
                        type="button"
                        @click="openCreatePaymentMethod"
                    >
                        Add
                    </button>
                </div>
                <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="paymentMethods.length === 0">
                                <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="2">No payment methods yet.</td>
                            </tr>
                            <tr
                                v-for="paymentMethod in paymentMethods"
                                :key="paymentMethod.id"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ paymentMethod.name }}</p>
                                    <p class="text-xs text-ink/50 dark:text-foam/50">
                                        {{ paymentMethod.is_active ? 'Active' : 'Inactive' }}
                                        · {{ paymentMethod.is_cash ? 'Cash' : 'Charge' }}
                                        <span v-if="paymentMethod.instructions"> · {{ paymentMethod.instructions }}</span>
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <button
                                            class="font-medium text-pine dark:text-sand"
                                            type="button"
                                            @click="openEditPaymentMethod(paymentMethod)"
                                        >
                                            Edit
                                        </button>
                                        <button class="font-medium text-danger" type="button" @click="deletePaymentMethod(paymentMethod)">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-medium">Delivery Methods</h2>
                    <button
                        class="h-10 shrink-0 rounded-xl bg-pine px-3 text-sm font-medium text-foam"
                        type="button"
                        @click="openCreateDeliveryMethod"
                    >
                        Add
                    </button>
                </div>
                <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Fee</th>
                                <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="deliveryMethods.length === 0">
                                <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="3">No delivery methods yet.</td>
                            </tr>
                            <tr
                                v-for="deliveryMethod in deliveryMethods"
                                :key="deliveryMethod.id"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ deliveryMethod.name }}</p>
                                    <p class="text-xs text-ink/50 dark:text-foam/50">
                                        {{ deliveryMethod.is_active ? 'Active' : 'Inactive' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ deliveryMethod.fee_label }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <button
                                            class="font-medium text-pine dark:text-sand"
                                            type="button"
                                            @click="openEditDeliveryMethod(deliveryMethod)"
                                        >
                                            Edit
                                        </button>
                                        <button class="font-medium text-danger" type="button" @click="deleteDeliveryMethod(deliveryMethod)">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div v-if="taxModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingTaxFee ? 'Edit tax or fee' : 'Add tax or fee' }}</h2>
                <button class="text-sm font-medium" type="button" @click="taxModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitTaxFee">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="tax-fee-name">Name</label>
                    <input
                        id="tax-fee-name"
                        v-model="taxForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="taxForm.errors.name" class="text-sm text-danger">{{ taxForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="tax-fee-type">Type</label>
                    <select
                        id="tax-fee-type"
                        v-model="taxForm.type"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    >
                        <option v-for="type in taxFeeTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <p v-if="taxForm.errors.type" class="text-sm text-danger">{{ taxForm.errors.type }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="tax-fee-rate">
                        {{ taxForm.type === 'percent' ? 'Rate (%)' : 'Amount' }}
                    </label>
                    <input
                        id="tax-fee-rate"
                        v-model="taxForm.rate"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="0"
                        required
                        step="0.01"
                        type="number"
                    />
                    <p v-if="taxForm.errors.rate" class="text-sm text-danger">{{ taxForm.errors.rate }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="taxForm.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="taxForm.processing"
                >
                    {{ taxForm.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="paymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingPaymentMethod ? 'Edit payment method' : 'Add payment method' }}</h2>
                <button class="text-sm font-medium" type="button" @click="paymentModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitPaymentMethod">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payment-method-name">Name</label>
                    <input
                        id="payment-method-name"
                        v-model="paymentForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="paymentForm.errors.name" class="text-sm text-danger">{{ paymentForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="payment-method-instructions">Instructions</label>
                    <textarea
                        id="payment-method-instructions"
                        v-model="paymentForm.instructions"
                        class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    />
                    <p v-if="paymentForm.errors.instructions" class="text-sm text-danger">{{ paymentForm.errors.instructions }}</p>
                </div>
                <fieldset class="flex flex-col gap-2">
                    <legend class="text-sm font-medium">Type</legend>
                    <div class="flex gap-2">
                        <label
                            class="flex h-11 flex-1 items-center justify-center rounded-xl border text-sm font-medium"
                            :class="paymentForm.is_cash ? 'border-pine bg-pine/10 text-pine dark:border-sand dark:text-sand' : 'border-mist dark:border-white/15'"
                        >
                            <input v-model="paymentForm.is_cash" class="sr-only" type="radio" :value="true" />
                            Cash
                        </label>
                        <label
                            class="flex h-11 flex-1 items-center justify-center rounded-xl border text-sm font-medium"
                            :class="!paymentForm.is_cash ? 'border-pine bg-pine/10 text-pine dark:border-sand dark:text-sand' : 'border-mist dark:border-white/15'"
                        >
                            <input v-model="paymentForm.is_cash" class="sr-only" type="radio" :value="false" />
                            Charge
                        </label>
                    </div>
                    <p v-if="paymentForm.errors.is_cash" class="text-sm text-danger">{{ paymentForm.errors.is_cash }}</p>
                </fieldset>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="paymentForm.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="paymentForm.processing"
                >
                    {{ paymentForm.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="deliveryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingDeliveryMethod ? 'Edit delivery method' : 'Add delivery method' }}</h2>
                <button class="text-sm font-medium" type="button" @click="deliveryModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitDeliveryMethod">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="delivery-method-name">Name</label>
                    <input
                        id="delivery-method-name"
                        v-model="deliveryForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="deliveryForm.errors.name" class="text-sm text-danger">{{ deliveryForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="delivery-method-fee">Fee</label>
                    <input
                        id="delivery-method-fee"
                        v-model="deliveryForm.fee"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        min="0"
                        step="0.01"
                        type="number"
                    />
                    <p class="text-xs text-ink/50 dark:text-foam/50">Leave blank to set the fee when adding to the cart.</p>
                    <p v-if="deliveryForm.errors.fee" class="text-sm text-danger">{{ deliveryForm.errors.fee }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="delivery-method-notes">Notes</label>
                    <textarea
                        id="delivery-method-notes"
                        v-model="deliveryForm.notes"
                        class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    />
                    <p v-if="deliveryForm.errors.notes" class="text-sm text-danger">{{ deliveryForm.errors.notes }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="deliveryForm.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="deliveryForm.processing"
                >
                    {{ deliveryForm.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>
</template>
