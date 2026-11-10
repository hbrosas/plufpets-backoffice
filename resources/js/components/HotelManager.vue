<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatPeso } from '../formatPeso';

const props = defineProps({
    open: { type: Boolean, default: false },
    embedded: { type: Boolean, default: false },
    stays: { type: Array, default: () => [] },
    options: {
        type: Object,
        default: () => ({ pod_types: [], genders: [], vaccines: [] }),
    },
    branches: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    registerPath: { type: String, default: '' },
});

const emit = defineEmits(['close', 'sell', 'drawer']);

const selectedId = ref(null);
const checkingIn = ref(false);
const paymentOpen = ref(false);
const checkedInLabel = ref('');

const reload = computed(() => ({
    preserveScroll: true,
    preserveState: true,
    preserveUrl: !props.embedded,
    only: props.embedded
        ? ['stays', 'flash']
        : ['hotelStays', 'flash', 'shift', 'todayTransactions', 'todayTransactionsTotal'],
}));

function emptyPet() {
    return {
        name: '',
        breed: '',
        age: '',
        gender: '',
        has_vaccine_booklet: false,
        vaccines: [],
        feeding_instructions: '',
        medication_instructions: '',
        special_instructions: '',
    };
}

const checkInForm = useForm({
    branch_id: '',
    client_name: '',
    contact_number: '',
    address: '',
    checkout_on: '',
    hotel_pod_type_id: '',
    pets: [emptyPet()],
});

const paymentForm = useForm({
    payment_method_id: '',
    amount: '',
    amount_tendered: '',
    reference_number: '',
});

const checkoutForm = useForm({});

const selectedStay = computed(() => props.stays.find((stay) => stay.id === selectedId.value) ?? null);
const showCheckIn = computed(() => checkingIn.value && !selectedStay.value);
const today = computed(() => {
    const date = new Date();

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
});
const paymentMethod = computed(() =>
    props.paymentMethods.find((method) => String(method.id) === String(paymentForm.payment_method_id)),
);
const asksForTendered = computed(() => Boolean(paymentMethod.value?.is_cash || paymentMethod.value?.asks_for_change));
const asksForReference = computed(() => Boolean(paymentMethod.value) && !paymentMethod.value.is_cash);
const paymentChange = computed(() => {
    if (!asksForTendered.value) {
        return null;
    }

    const amount = Number(paymentForm.amount);
    const tendered = Number(paymentForm.amount_tendered);

    if (!Number.isFinite(amount) || !Number.isFinite(tendered) || tendered + 0.005 < amount) {
        return null;
    }

    return Math.max(0, tendered - amount);
});

watch(() => props.stays, (stays) => {
    if (selectedId.value !== null && !stays.some((stay) => stay.id === selectedId.value)) {
        selectedId.value = null;
        paymentOpen.value = false;
    }
});

watch(() => props.open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    if (!props.embedded) {
        checkingIn.value = false;
    }

    refreshCheckedInLabel();
});

function refreshCheckedInLabel() {
    checkedInLabel.value = new Date().toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function close() {
    emit('close');
}

function startCheckIn() {
    selectedId.value = null;
    checkingIn.value = true;
    paymentOpen.value = false;
    refreshCheckedInLabel();
}

function selectStay(stay) {
    selectedId.value = stay.id;
    checkingIn.value = false;
    paymentOpen.value = false;
    paymentForm.clearErrors();
}

function addPet() {
    checkInForm.pets = [...checkInForm.pets, emptyPet()];
}

function removePet(index) {
    if (checkInForm.pets.length === 1) {
        return;
    }

    checkInForm.pets = checkInForm.pets.filter((_, petIndex) => petIndex !== index);
}

function toggleVaccine(pet, value) {
    pet.vaccines = pet.vaccines.includes(value)
        ? pet.vaccines.filter((vaccine) => vaccine !== value)
        : [...pet.vaccines, value];
}

function stayUrl(suffix = '') {
    return props.embedded
        ? `/backoffice/hotel/stays${suffix}`
        : `${props.registerPath}/hotel-stays${suffix}`;
}

function submitCheckIn() {
    checkInForm.post(stayUrl(), {
        ...reload.value,
        onSuccess: (page) => {
            checkInForm.reset();
            checkInForm.pets = [emptyPet()];
            const stays = (props.embedded ? page.props.stays : page.props.hotelStays) ?? [];
            const newest = stays[0];

            if (newest) {
                selectedId.value = newest.id;
                checkingIn.value = false;
            }
        },
    });
}

function openPayment() {
    paymentOpen.value = true;
    const cash = props.paymentMethods.find((method) => method.is_cash);
    paymentForm.payment_method_id = cash?.id ?? props.paymentMethods[0]?.id ?? '';
    paymentForm.amount = '';
    paymentForm.amount_tendered = '';
    paymentForm.reference_number = '';
    paymentForm.clearErrors();
}

function submitPayment() {
    if (!selectedStay.value) {
        return;
    }

    const opensDrawer = Boolean(paymentMethod.value?.is_cash);

    paymentForm.post(stayUrl(`/${selectedStay.value.id}/payments`), {
        ...reload.value,
        onSuccess: () => {
            paymentOpen.value = false;
            paymentForm.reset();

            if (opensDrawer) {
                emit('drawer');
            }
        },
    });
}

function sellToGuest() {
    if (!selectedStay.value) {
        return;
    }

    if (props.embedded) {
        router.visit(`/backoffice/pos/register/${selectedStay.value.branch_id}`);

        return;
    }

    emit('sell', selectedStay.value);
}

function checkOut() {
    if (!selectedStay.value) {
        return;
    }

    if (!window.confirm(`Check out ${selectedStay.value.client_name}?`)) {
        return;
    }

    checkoutForm.post(stayUrl(`/${selectedStay.value.id}/checkout`), {
        ...reload.value,
        onSuccess: () => {
            selectedId.value = null;
            paymentOpen.value = false;
        },
    });
}

defineExpose({
    startCheckIn,
});
</script>

<template>
    <div
        v-if="open"
        :class="embedded
            ? 'flex h-full min-h-0 flex-1 flex-col'
            : 'fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-3 sm:p-6'"
        @click.self="embedded ? undefined : close()"
    >
        <div
            class="flex w-full flex-col overflow-hidden bg-foam text-ink dark:bg-ink dark:text-foam"
            :class="embedded
                ? 'h-full min-h-0 flex-1 rounded-2xl border border-mist dark:border-white/10'
                : 'h-[min(92dvh,52rem)] max-w-6xl rounded-2xl'"
            :role="embedded ? undefined : 'dialog'"
            :aria-modal="embedded ? undefined : true"
            aria-labelledby="manage-hotel-title"
        >
            <div v-if="!embedded" class="flex shrink-0 items-center justify-between gap-4 border-b border-mist px-5 py-4 dark:border-white/10">
                <h2 id="manage-hotel-title" class="text-lg font-medium">Manage Hotel</h2>
                <div class="flex items-center gap-2">
                    <button
                        class="h-10 rounded-xl px-4 text-sm font-medium"
                        :class="checkingIn ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                        type="button"
                        @click="startCheckIn"
                    >
                        Guest Check-In
                    </button>
                    <button class="h-10 rounded-xl px-3 text-sm font-medium" type="button" @click="close">Close</button>
                </div>
            </div>

            <div class="grid h-full min-h-0 flex-1 grid-cols-[minmax(12rem,16rem)_minmax(0,1fr)] grid-rows-[minmax(0,1fr)]">
                <aside class="flex h-full min-h-0 flex-col overflow-hidden border-r border-mist dark:border-white/10">
                    <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden px-4 py-4">
                        <p class="shrink-0 text-xs font-medium text-ink/55 dark:text-foam/55">Checked in</p>
                        <p v-if="stays.length === 0" class="text-sm text-ink/55 dark:text-foam/55">No guests are checked in.</p>
                        <ul v-else class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto">
                            <li v-for="stay in stays" :key="stay.id">
                                <button
                                    class="w-full rounded-xl px-3 py-2 text-left"
                                    :class="selectedStay?.id === stay.id ? 'bg-pine text-foam' : 'hover:bg-mist/70 dark:hover:bg-white/10'"
                                    type="button"
                                    @click="selectStay(stay)"
                                >
                                    <p class="truncate text-sm font-medium">{{ stay.client_name }}</p>
                                    <p class="truncate text-xs" :class="selectedStay?.id === stay.id ? 'text-foam/80' : 'text-ink/55 dark:text-foam/55'">
                                        {{ stay.pet_names }}
                                    </p>
                                    <p v-if="embedded" class="truncate text-xs" :class="selectedStay?.id === stay.id ? 'text-foam/80' : 'text-ink/55 dark:text-foam/55'">
                                        {{ stay.branch_name }}
                                    </p>
                                </button>
                            </li>
                        </ul>
                    </div>
                </aside>

                <section v-if="showCheckIn" class="flex h-full min-h-0 flex-col overflow-hidden">
                    <form id="hotel-check-in-form" class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-5" @submit.prevent="submitCheckIn">
                        <p v-if="checkInForm.errors.status" class="text-sm text-danger">{{ checkInForm.errors.status }}</p>

                        <label v-if="embedded" class="flex flex-col gap-1 text-sm">
                            Branch
                            <select v-model="checkInForm.branch_id" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required>
                                <option disabled value="">Choose</option>
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                            </select>
                            <span v-if="checkInForm.errors.branch_id" class="text-danger">{{ checkInForm.errors.branch_id }}</span>
                        </label>

                        <div class="flex flex-col gap-3">
                            <h3 class="text-sm font-medium">Client Information</h3>
                            <label class="flex flex-col gap-1 text-sm">
                                Name
                                <input v-model="checkInForm.client_name" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required type="text" />
                                <span v-if="checkInForm.errors.client_name" class="text-danger">{{ checkInForm.errors.client_name }}</span>
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                Contact No.
                                <input v-model="checkInForm.contact_number" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required type="tel" />
                                <span v-if="checkInForm.errors.contact_number" class="text-danger">{{ checkInForm.errors.contact_number }}</span>
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                Address
                                <textarea v-model="checkInForm.address" class="min-h-20 rounded-xl border border-mist bg-white px-3 py-2 dark:border-white/10 dark:bg-white/5" required />
                                <span v-if="checkInForm.errors.address" class="text-danger">{{ checkInForm.errors.address }}</span>
                            </label>
                        </div>

                        <div class="flex flex-col gap-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-medium">Pet Information</h3>
                                <button class="h-9 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15" type="button" @click="addPet">
                                    Add pet
                                </button>
                            </div>
                            <p v-if="checkInForm.errors.pets" class="text-sm text-danger">{{ checkInForm.errors.pets }}</p>
                            <article
                                v-for="(pet, index) in checkInForm.pets"
                                :key="index"
                                class="flex flex-col gap-3 rounded-2xl border border-mist p-4 dark:border-white/10"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium">Pet {{ index + 1 }}</p>
                                    <button
                                        v-if="checkInForm.pets.length > 1"
                                        class="text-sm font-medium text-danger"
                                        type="button"
                                        @click="removePet(index)"
                                    >
                                        Remove
                                    </button>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex flex-col gap-1 text-sm">
                                        Pet Name
                                        <input v-model="pet.name" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required type="text" />
                                        <span v-if="checkInForm.errors[`pets.${index}.name`]" class="text-danger">{{ checkInForm.errors[`pets.${index}.name`] }}</span>
                                    </label>
                                    <label class="flex flex-col gap-1 text-sm">
                                        Breed
                                        <input v-model="pet.breed" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required type="text" />
                                        <span v-if="checkInForm.errors[`pets.${index}.breed`]" class="text-danger">{{ checkInForm.errors[`pets.${index}.breed`] }}</span>
                                    </label>
                                    <label class="flex flex-col gap-1 text-sm">
                                        Age
                                        <input v-model="pet.age" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required type="text" />
                                        <span v-if="checkInForm.errors[`pets.${index}.age`]" class="text-danger">{{ checkInForm.errors[`pets.${index}.age`] }}</span>
                                    </label>
                                    <label class="flex flex-col gap-1 text-sm">
                                        Gender
                                        <select v-model="pet.gender" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required>
                                            <option disabled value="">Choose</option>
                                            <option v-for="gender in options.genders" :key="gender.value" :value="gender.value">{{ gender.label }}</option>
                                        </select>
                                        <span v-if="checkInForm.errors[`pets.${index}.gender`]" class="text-danger">{{ checkInForm.errors[`pets.${index}.gender`] }}</span>
                                    </label>
                                </div>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="pet.has_vaccine_booklet" class="h-4 w-4 accent-pine" type="checkbox" />
                                    Has vaccine booklet
                                </label>
                                <fieldset class="flex flex-col gap-2">
                                    <legend class="text-sm font-medium">Vaccines</legend>
                                    <label v-for="vaccine in options.vaccines" :key="vaccine.value" class="flex items-center gap-2 text-sm">
                                        <input
                                            class="h-4 w-4 accent-pine"
                                            type="checkbox"
                                            :checked="pet.vaccines.includes(vaccine.value)"
                                            @change="toggleVaccine(pet, vaccine.value)"
                                        />
                                        {{ vaccine.label }}
                                    </label>
                                    <span v-if="checkInForm.errors[`pets.${index}.vaccines`]" class="text-sm text-danger">{{ checkInForm.errors[`pets.${index}.vaccines`] }}</span>
                                </fieldset>
                                <div class="flex flex-col gap-3">
                                    <h4 class="text-sm font-medium">Care Instructions</h4>
                                    <label class="flex flex-col gap-1 text-sm">
                                        Feeding Instructions
                                        <textarea v-model="pet.feeding_instructions" class="min-h-16 rounded-xl border border-mist bg-white px-3 py-2 dark:border-white/10 dark:bg-white/5" />
                                    </label>
                                    <label class="flex flex-col gap-1 text-sm">
                                        Medication Instructions
                                        <textarea v-model="pet.medication_instructions" class="min-h-16 rounded-xl border border-mist bg-white px-3 py-2 dark:border-white/10 dark:bg-white/5" />
                                    </label>
                                    <label class="flex flex-col gap-1 text-sm">
                                        Other Special Instructions
                                        <textarea v-model="pet.special_instructions" class="min-h-16 rounded-xl border border-mist bg-white px-3 py-2 dark:border-white/10 dark:bg-white/5" />
                                    </label>
                                </div>
                            </article>
                        </div>

                        <div class="flex flex-col gap-3">
                            <h3 class="text-sm font-medium">Booking Details</h3>
                            <label class="flex flex-col gap-1 text-sm">
                                Check In Date &amp; Time
                                <input :value="checkedInLabel" class="h-10 rounded-xl border border-mist bg-mist/40 px-3 dark:border-white/10 dark:bg-white/5" readonly type="text" />
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                Check out date
                                <input v-model="checkInForm.checkout_on" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" :min="today" required type="date" />
                                <span class="text-xs text-ink/55 dark:text-foam/55">Tentative</span>
                                <span v-if="checkInForm.errors.checkout_on" class="text-danger">{{ checkInForm.errors.checkout_on }}</span>
                            </label>
                        </div>

                        <div class="flex flex-col gap-3">
                            <h3 class="text-sm font-medium">Pod Details</h3>
                            <label class="flex flex-col gap-1 text-sm">
                                Pod Type
                                <select v-model="checkInForm.hotel_pod_type_id" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required>
                                    <option disabled value="">Choose</option>
                                    <option v-for="pod in options.pod_types" :key="pod.id" :value="pod.id">{{ pod.name }}</option>
                                </select>
                                <span v-if="checkInForm.errors.hotel_pod_type_id" class="text-danger">{{ checkInForm.errors.hotel_pod_type_id }}</span>
                            </label>
                        </div>
                    </form>
                    <div class="shrink-0 border-t border-mist p-4 dark:border-white/10">
                        <button
                            class="h-11 rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-70"
                            form="hotel-check-in-form"
                            type="submit"
                            :disabled="checkInForm.processing"
                        >
                            {{ checkInForm.processing ? 'Checking in…' : 'Check in' }}
                        </button>
                    </div>
                </section>

                <section v-else-if="selectedStay" class="flex h-full min-h-0 flex-col overflow-hidden">
                    <div class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-5">
                        <p v-if="paymentForm.errors.status || checkoutForm.errors.status" class="text-sm text-danger">
                            {{ paymentForm.errors.status || checkoutForm.errors.status }}
                        </p>
                        <div class="flex flex-col gap-1">
                            <h3 class="text-lg font-medium">{{ selectedStay.client_name }}</h3>
                            <p class="text-sm text-ink/60 dark:text-foam/60">{{ selectedStay.contact_number }}</p>
                            <p class="text-sm text-ink/60 dark:text-foam/60">{{ selectedStay.address }}</p>
                        </div>
                        <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                            <p><span class="text-ink/55 dark:text-foam/55">Branch</span><br>{{ selectedStay.branch_name }}</p>
                            <p><span class="text-ink/55 dark:text-foam/55">Check in</span><br>{{ selectedStay.checked_in_label }}</p>
                            <p><span class="text-ink/55 dark:text-foam/55">Check out</span><br>{{ selectedStay.checkout_label }}</p>
                            <p><span class="text-ink/55 dark:text-foam/55">Pod</span><br>{{ selectedStay.pod_type_label }}</p>
                        </div>
                        <article v-for="pet in selectedStay.pets" :key="pet.id" class="flex flex-col gap-2 rounded-2xl border border-mist p-4 text-sm dark:border-white/10">
                            <p class="font-medium">{{ pet.name }}</p>
                            <p class="text-ink/60 dark:text-foam/60">{{ pet.breed }} · {{ pet.age }} · {{ pet.gender_label }}</p>
                            <p>Vaccine booklet: {{ pet.has_vaccine_booklet ? 'Yes' : 'No' }}</p>
                            <p>Vaccines: {{ pet.vaccines.length ? pet.vaccines.join(', ') : 'None recorded' }}</p>
                            <p v-if="pet.feeding_instructions"><span class="font-medium">Feeding.</span> {{ pet.feeding_instructions }}</p>
                            <p v-if="pet.medication_instructions"><span class="font-medium">Medication.</span> {{ pet.medication_instructions }}</p>
                            <p v-if="pet.special_instructions"><span class="font-medium">Special.</span> {{ pet.special_instructions }}</p>
                        </article>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex min-w-0 flex-col gap-2 rounded-2xl border border-mist p-4 dark:border-white/10">
                                <h3 class="text-sm font-medium">Payments · {{ selectedStay.payments_total_label }}</h3>
                                <p v-if="selectedStay.payments.length === 0" class="text-sm text-ink/55 dark:text-foam/55">No downpayments yet.</p>
                                <ul v-else class="flex flex-col gap-2">
                                    <li v-for="payment in selectedStay.payments" :key="payment.id" class="text-sm">
                                        <p class="font-medium">{{ payment.method }} · {{ payment.amount_label }}</p>
                                        <p class="text-ink/55 dark:text-foam/55">
                                            {{ payment.recorded_at_label }}
                                            <template v-if="payment.tendered_label"> · Tendered {{ payment.tendered_label }}</template>
                                            <template v-if="payment.change_label"> · Change {{ payment.change_label }}</template>
                                            <template v-if="payment.reference"> · Ref {{ payment.reference }}</template>
                                        </p>
                                    </li>
                                </ul>
                            </div>
                            <div class="flex min-w-0 flex-col gap-2 rounded-2xl border border-mist p-4 dark:border-white/10">
                                <h3 class="text-sm font-medium">Guest cart · {{ selectedStay.cart?.subtotal_label ?? '₱0.00' }}</h3>
                                <p v-if="!selectedStay.cart?.items?.length" class="text-sm text-ink/55 dark:text-foam/55">No products in the cart.</p>
                                <ul v-else class="flex flex-col gap-2">
                                    <li v-for="item in selectedStay.cart.items" :key="item.key" class="flex items-start justify-between gap-3 text-sm">
                                        <p class="min-w-0">
                                            <span class="font-medium">{{ item.quantity }} × {{ item.label }}</span>
                                            <span v-if="item.notes" class="block text-ink/55 dark:text-foam/55">{{ item.notes }}</span>
                                        </p>
                                        <p class="shrink-0">{{ item.line_total_label }}</p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <h3 class="text-sm font-medium">Sales</h3>
                            <p v-if="selectedStay.sales.length === 0" class="text-sm text-ink/55 dark:text-foam/55">No sales while checked in.</p>
                            <ul v-else class="flex flex-col gap-2">
                                <li v-for="sale in selectedStay.sales" :key="sale.id" class="text-sm">
                                    <p class="font-medium">{{ sale.number }} · {{ sale.total_label }}<span v-if="sale.refunded"> · Refunded</span></p>
                                    <p class="text-ink/55 dark:text-foam/55">{{ sale.sold_at_label }}</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div v-if="!embedded" class="shrink-0 border-t border-mist p-4 dark:border-white/10">
                        <form v-if="paymentOpen" class="flex flex-col gap-3" @submit.prevent="submitPayment">
                            <p class="text-sm font-medium">Add Payment</p>
                            <label class="flex flex-col gap-1 text-sm">
                                Payment method
                                <select v-model="paymentForm.payment_method_id" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required>
                                    <option disabled value="">Choose</option>
                                    <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
                                </select>
                                <span v-if="paymentForm.errors.payment_method_id" class="text-danger">{{ paymentForm.errors.payment_method_id }}</span>
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                Amount
                                <input v-model="paymentForm.amount" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" min="0.01" required step="0.01" type="number" />
                                <span v-if="paymentForm.errors.amount" class="text-danger">{{ paymentForm.errors.amount }}</span>
                            </label>
                            <label v-if="asksForTendered" class="flex flex-col gap-1 text-sm">
                                {{ paymentMethod?.asks_for_change ? 'Change for' : 'Amount tendered' }}
                                <input v-model="paymentForm.amount_tendered" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" min="0" required step="0.01" type="number" />
                                <span v-if="paymentChange !== null" class="text-xs text-ink/55 dark:text-foam/55">Change {{ formatPeso(paymentChange) }}</span>
                                <span v-if="paymentForm.errors.amount_tendered" class="text-danger">{{ paymentForm.errors.amount_tendered }}</span>
                            </label>
                            <label v-if="asksForReference" class="flex flex-col gap-1 text-sm">
                                Reference number
                                <input v-model="paymentForm.reference_number" class="h-10 rounded-xl border border-mist bg-white px-3 dark:border-white/10 dark:bg-white/5" required type="text" />
                                <span v-if="paymentForm.errors.reference_number" class="text-danger">{{ paymentForm.errors.reference_number }}</span>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button class="h-10 rounded-xl bg-pine px-3 text-sm font-medium text-foam disabled:opacity-70" type="submit" :disabled="paymentForm.processing">
                                    {{ paymentForm.processing ? 'Saving…' : 'Save payment' }}
                                </button>
                                <button class="h-10 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15" type="button" @click="paymentOpen = false">
                                    Cancel
                                </button>
                            </div>
                        </form>
                        <div v-else class="flex flex-wrap gap-2">
                            <button class="h-10 rounded-xl bg-pine px-3 text-sm font-medium text-foam" type="button" @click="openPayment">
                                Add Payment
                            </button>
                            <button class="h-10 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15" type="button" @click="sellToGuest">
                                Guest Cart
                            </button>
                            <button
                                class="h-10 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15 disabled:opacity-70"
                                type="button"
                                :disabled="checkoutForm.processing"
                                @click="checkOut"
                            >
                                {{ checkoutForm.processing ? 'Checking out…' : 'Check Out' }}
                            </button>
                        </div>
                    </div>
                </section>
                <section v-else class="flex h-full min-h-0 items-center justify-center p-5">
                    <p class="text-sm text-ink/55 dark:text-foam/55">Select a guest to see their stay.</p>
                </section>
            </div>
        </div>
    </div>
</template>
