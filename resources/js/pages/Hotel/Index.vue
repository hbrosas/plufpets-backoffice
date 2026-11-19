<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import HotelManager from '../../components/HotelManager.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    stays: { type: Array, default: null },
    checkedOutStays: { type: Array, default: null },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    branches: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    podTypes: { type: Array, required: true },
});

const tabs = [
    { value: 'current', label: 'Current Guests' },
    { value: 'checked-out', label: 'Checked Out Guests' },
];

const hotel = ref(null);
const tab = ref(props.filters.tab === 'checked-out' ? 'checked-out' : 'current');
const loadingGuests = ref(false);
const page = usePage();
const podTypeError = computed(() => page.props.errors?.pod_type ?? '');
const modalOpen = ref(false);
const editingPodType = ref(null);
const form = useForm({
    name: '',
    is_active: true,
});

const activeStays = computed(() => (tab.value === 'checked-out' ? props.checkedOutStays : props.stays) ?? []);
const guestsLoading = computed(() => loadingGuests.value || (tab.value === 'checked-out' ? props.checkedOutStays === null : props.stays === null));

function hotelQuery(nextTab = tab.value, dates = {}) {
    const query = { tab: nextTab };

    if (nextTab !== 'checked-out') {
        return query;
    }

    const from = dates.from ?? props.filters.from ?? '';
    const to = dates.to ?? props.filters.to ?? '';

    if (from) {
        query.from = from;
    }

    if (to) {
        query.to = to;
    }

    return query;
}

function rememberTab() {
    const params = new URLSearchParams();
    const query = hotelQuery();

    if (query.tab === 'checked-out') {
        params.set('tab', query.tab);
    }

    if (query.from) {
        params.set('from', query.from);
    }

    if (query.to) {
        params.set('to', query.to);
    }

    const search = params.toString();

    window.history.replaceState(window.history.state, '', `/backoffice/hotel${search ? `?${search}` : ''}`);
}

function loadGuests(nextTab, dates = {}, onSuccess = null) {
    loadingGuests.value = true;

    router.get('/backoffice/hotel', hotelQuery(nextTab, dates), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: nextTab === 'checked-out' ? ['checkedOutStays', 'filters'] : ['stays', 'filters'],
        onSuccess: () => onSuccess?.(),
        onFinish: () => {
            loadingGuests.value = false;
        },
    });
}

function selectTab(next) {
    if (next === tab.value) {
        return;
    }

    tab.value = next;
    const loaded = next === 'checked-out' ? props.checkedOutStays !== null : props.stays !== null;

    if (loaded) {
        rememberTab();

        return;
    }

    loadGuests(next);
}

function applyDates(dates) {
    loadGuests('checked-out', dates);
}

function applyDateFilter(event, key) {
    applyDates({
        from: key === 'from' ? event.target.value : props.filters.from,
        to: key === 'to' ? event.target.value : props.filters.to,
    });
}

function checkIn() {
    const start = () => hotel.value?.startCheckIn();

    if (tab.value !== 'current') {
        tab.value = 'current';

        if (props.stays === null) {
            loadGuests('current', {}, start);

            return;
        }

        rememberTab();
    }

    start();
}

function openPodTypes() {
    editingPodType.value = null;
    form.reset();
    form.is_active = true;
    form.clearErrors();
    modalOpen.value = true;
}

function editPodType(podType) {
    editingPodType.value = podType;
    form.name = podType.name;
    form.is_active = podType.is_active;
    form.clearErrors();
}

function cancelEdit() {
    editingPodType.value = null;
    form.reset();
    form.is_active = true;
    form.clearErrors();
}

function submit() {
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingPodType.value = null;
            form.reset();
            form.is_active = true;
        },
    };

    if (editingPodType.value) {
        form.put(`/backoffice/hotel/pod-types/${editingPodType.value.id}`, options);
        return;
    }

    form.post('/backoffice/hotel/pod-types', options);
}

function remove(podType) {
    if (!window.confirm(`Delete ${podType.name}?`)) {
        return;
    }

    router.delete(`/backoffice/hotel/pod-types/${podType.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (editingPodType.value?.id === podType.id) {
                cancelEdit();
            }
        },
    });
}
</script>

<template>
    <Head title="Manage Hotel" />

    <Teleport defer to="#admin-header-tabs">
        <div class="flex w-max gap-2" role="tablist" aria-label="Hotel guests">
            <button
                v-for="item in tabs"
                :key="item.value"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="tab === item.value ? 'bg-pine text-foam' : 'border border-mist bg-white dark:border-white/15 dark:bg-transparent'"
                type="button"
                role="tab"
                :aria-selected="tab === item.value"
                @click="selectTab(item.value)"
            >
                {{ item.label }}
            </button>
        </div>
    </Teleport>

    <Teleport defer to="#admin-header-actions">
        <button class="h-10 rounded-xl border border-black/15 px-4 text-sm font-medium" type="button" @click="checkIn">
            Check In
        </button>
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="openPodTypes">
            Pod Types
        </button>
    </Teleport>

    <div class="flex h-full min-h-[36rem] flex-col gap-3">
        <p v-if="podTypeError" class="text-sm text-danger">{{ podTypeError }}</p>
        <div v-if="tab === 'checked-out'" class="flex shrink-0 items-center gap-2">
            <label class="flex items-center gap-2 text-sm" for="checked-out-from">
                From
                <input
                    id="checked-out-from"
                    class="h-10 rounded-xl border border-mist bg-white px-3 text-sm text-ink dark:border-white/10 dark:bg-white/5 dark:text-foam"
                    type="date"
                    :value="filters.from"
                    @change="applyDateFilter($event, 'from')"
                />
            </label>
            <label class="flex items-center gap-2 text-sm" for="checked-out-to">
                To
                <input
                    id="checked-out-to"
                    class="h-10 rounded-xl border border-mist bg-white px-3 text-sm text-ink dark:border-white/10 dark:bg-white/5 dark:text-foam"
                    type="date"
                    :value="filters.to"
                    @change="applyDateFilter($event, 'to')"
                />
            </label>
        </div>
        <HotelManager
            ref="hotel"
            embedded
            open
            :stays="activeStays"
            :checked-out="tab === 'checked-out'"
            :date-from="filters.from"
            :date-to="filters.to"
            :loading="guestsLoading"
            :options="options"
            :branches="branches"
            :payment-methods="paymentMethods"
        />
    </div>

    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[min(40rem,calc(100dvh-2rem))] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Pod Types</h2>
                <button class="text-sm font-medium" type="button" @click="modalOpen = false">Close</button>
            </div>
            <p class="text-sm text-ink/60">Inactive pod types stay on existing guests and are hidden from new check-ins.</p>
            <p v-if="podTypeError" class="text-sm text-danger">{{ podTypeError }}</p>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <input
                    v-model="form.name"
                    class="h-11 rounded-xl border border-mist bg-white px-4 text-sm"
                    required
                    type="text"
                    placeholder="Name"
                />
                <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="form.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
                <div class="flex gap-2">
                    <button class="h-11 flex-1 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70" type="submit" :disabled="form.processing">
                        {{ editingPodType ? 'Save changes' : 'Add pod type' }}
                    </button>
                    <button v-if="editingPodType" class="h-11 rounded-xl border border-mist px-4 text-sm font-medium" type="button" @click="cancelEdit">
                        Cancel
                    </button>
                </div>
            </form>
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full text-left text-sm">
                    <tbody>
                        <tr v-for="podType in podTypes" :key="podType.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ podType.name }}</p>
                                <p class="text-xs text-ink/50">{{ podType.is_active ? 'Active' : 'Inactive' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <button class="font-medium text-pine dark:text-sand" type="button" @click="editPodType(podType)">Edit</button>
                                    <button class="font-medium text-danger" type="button" @click="remove(podType)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
