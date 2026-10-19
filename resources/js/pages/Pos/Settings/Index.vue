<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import {
    printBluetoothTest,
    printSerialTest,
    requestBluetoothIdentity,
    requestSerialIdentity,
} from '../../../printerConnection';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    printers: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        required: true,
    },
    printerRoles: {
        type: Array,
        required: true,
    },
    printerConnections: {
        type: Array,
        required: true,
    },
});

const printerModalOpen = ref(false);
const editingPrinter = ref(null);
const printerNotice = ref('');
const selectedBranchId = ref('');
const filteredPrinters = computed(() => {
    if (selectedBranchId.value === '') {
        return props.printers;
    }

    return props.printers.filter((printer) => String(printer.branch_id) === selectedBranchId.value);
});

const printerForm = useForm({
    branch_id: '',
    name: '',
    role: 'receipt',
    connection: 'bluetooth',
    paper_width_mm: 80,
    paper_height_mm: '',
    baud_rate: 9600,
    is_default: false,
    is_active: true,
    usb_vendor_id: '',
    usb_product_id: '',
    usb_serial_number: '',
    device_name: '',
    bluetooth_id: '',
});

function applyPrinterRoleDefaults() {
    if (printerForm.role === 'label') {
        printerForm.connection = 'bluetooth';
        printerForm.paper_width_mm = 40;
        printerForm.paper_height_mm = 30;
        return;
    }

    printerForm.connection = 'bluetooth';
    printerForm.paper_width_mm = 80;
    printerForm.paper_height_mm = '';
}

function defaultBranchId() {
    return props.branches[0]?.id ?? '';
}

function printerPayload(overrides = {}) {
    return {
        branch_id: printerForm.branch_id,
        name: printerForm.name,
        role: printerForm.role,
        connection: printerForm.connection,
        paper_width_mm: printerForm.paper_width_mm,
        paper_height_mm: printerForm.paper_height_mm === '' ? null : printerForm.paper_height_mm,
        baud_rate: printerForm.baud_rate,
        is_default: printerForm.is_default,
        is_active: printerForm.is_active,
        usb_vendor_id: printerForm.usb_vendor_id === '' ? null : printerForm.usb_vendor_id,
        usb_product_id: printerForm.usb_product_id === '' ? null : printerForm.usb_product_id,
        usb_serial_number: printerForm.usb_serial_number,
        device_name: printerForm.device_name,
        bluetooth_id: printerForm.bluetooth_id,
        ...overrides,
    };
}

function openCreatePrinter() {
    editingPrinter.value = null;
    printerForm.reset();
    printerForm.branch_id = selectedBranchId.value === '' ? defaultBranchId() : Number(selectedBranchId.value);
    printerForm.role = 'receipt';
    printerForm.connection = 'bluetooth';
    printerForm.paper_width_mm = 80;
    printerForm.paper_height_mm = '';
    printerForm.baud_rate = 9600;
    printerForm.is_default = false;
    printerForm.is_active = true;
    printerForm.clearErrors();
    printerNotice.value = '';
    printerModalOpen.value = true;
}

function fillPrinterForm(printer) {
    printerForm.branch_id = printer.branch_id;
    printerForm.name = printer.name;
    printerForm.role = printer.role;
    printerForm.connection = printer.connection;
    printerForm.paper_width_mm = printer.paper_width_mm;
    printerForm.paper_height_mm = printer.paper_height_mm ?? '';
    printerForm.baud_rate = printer.baud_rate;
    printerForm.is_default = printer.is_default;
    printerForm.is_active = printer.is_active;
    printerForm.usb_vendor_id = printer.usb_vendor_id ?? '';
    printerForm.usb_product_id = printer.usb_product_id ?? '';
    printerForm.usb_serial_number = printer.usb_serial_number ?? '';
    printerForm.device_name = printer.device_name ?? '';
    printerForm.bluetooth_id = printer.bluetooth_id ?? '';
    printerForm.clearErrors();
}

function openEditPrinter(printer) {
    editingPrinter.value = printer;
    fillPrinterForm(printer);
    printerNotice.value = '';
    printerModalOpen.value = true;
}

function submitPrinter() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            printerModalOpen.value = false;
            printerForm.reset();
            editingPrinter.value = null;
        },
    };

    if (editingPrinter.value) {
        printerForm.transform(() => printerPayload()).put(`/backoffice/pos/printers/${editingPrinter.value.id}`, options);
        return;
    }

    printerForm.transform(() => printerPayload()).post('/backoffice/pos/printers', options);
}

function deletePrinter(printer) {
    if (!window.confirm(`Delete ${printer.name}?`)) {
        return;
    }

    router.delete(`/backoffice/pos/printers/${printer.id}`, {
        preserveScroll: true,
    });
}

function usesPairedDevice(printer) {
    return printer.connection === 'bluetooth' || printer.connection === 'serial';
}

async function connectPrinter(printer) {
    printerNotice.value = '';

    if (!usesPairedDevice(printer)) {
        window.open(printer.test_href, '_blank', 'noopener');
        return;
    }

    try {
        const identity =
            printer.connection === 'bluetooth' ? await requestBluetoothIdentity() : await requestSerialIdentity();
        fillPrinterForm(printer);
        printerForm.transform(() => printerPayload(identity)).put(`/backoffice/pos/printers/${printer.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                printerNotice.value = `Identified ${identity.device_name}.`;
            },
        });
    } catch (error) {
        printerNotice.value = error instanceof Error ? error.message : 'Could not connect to that printer.';
    }
}

async function testPrinter(printer) {
    printerNotice.value = '';

    try {
        if (printer.connection === 'bluetooth') {
            await printBluetoothTest(printer);
            printerNotice.value = `Sent a test to ${printer.device_name || printer.name}.`;
            return;
        }

        if (printer.connection === 'serial') {
            await printSerialTest(printer);
            printerNotice.value = `Sent a test to ${printer.device_name || printer.name}.`;
            return;
        }
    } catch (error) {
        printerNotice.value = error instanceof Error ? error.message : 'Test print failed.';
        return;
    }

    window.open(printer.test_href, '_blank', 'noopener');
}
</script>

<template>
    <Head title="POS Settings" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-medium">Printers</h2>
                    <p class="text-sm text-ink/60 dark:text-foam/60">
                        Pair each branch to its own receipt and label printer. Connect on that branch's register so
                        Bluetooth finds the right device.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="sr-only" for="printer-branch-filter">Branch</label>
                    <select
                        id="printer-branch-filter"
                        v-model="selectedBranchId"
                        class="h-10 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    >
                        <option value="">All branches</option>
                        <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                            {{ branch.name }}
                        </option>
                    </select>
                    <button
                        class="h-10 shrink-0 rounded-xl bg-pine px-3 text-sm font-medium text-foam"
                        type="button"
                        @click="openCreatePrinter"
                    >
                        Add
                    </button>
                </div>
            </div>
            <p v-if="printerNotice" class="text-sm text-pine dark:text-sand">{{ printerNotice }}</p>
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Printer</th>
                            <th class="px-4 py-3 font-medium">Branch</th>
                            <th class="px-4 py-3 font-medium">Use</th>
                            <th class="px-4 py-3 font-medium">Paper</th>
                            <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredPrinters.length === 0">
                            <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="5">No printers yet.</td>
                        </tr>
                        <tr
                            v-for="printer in filteredPrinters"
                            :key="printer.id"
                            class="border-b border-mist last:border-b-0 dark:border-white/10"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ printer.name }}</p>
                                <p class="text-xs text-ink/50 dark:text-foam/50">
                                    {{ printer.is_active ? 'Active' : 'Inactive' }}
                                    <span v-if="printer.is_default"> · Default {{ printer.role_label.toLowerCase() }}</span>
                                    · {{ printer.connection_label }}
                                    <span v-if="printer.is_identified"> · {{ printer.device_name }}</span>
                                    <span v-else-if="usesPairedDevice(printer)"> · Not connected</span>
                                </p>
                            </td>
                            <td class="px-4 py-3">{{ printer.branch_name }}</td>
                            <td class="px-4 py-3">{{ printer.role_label }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ printer.paper_label }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-3">
                                    <button class="font-medium text-pine dark:text-sand" type="button" @click="connectPrinter(printer)">
                                        {{ usesPairedDevice(printer) ? 'Connect' : 'Identify' }}
                                    </button>
                                    <button class="font-medium text-pine dark:text-sand" type="button" @click="testPrinter(printer)">
                                        Test
                                    </button>
                                    <button class="font-medium text-pine dark:text-sand" type="button" @click="openEditPrinter(printer)">
                                        Edit
                                    </button>
                                    <button class="font-medium text-danger" type="button" @click="deletePrinter(printer)">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-ink/50 dark:text-foam/50">
                Connect from the POS register of the assigned branch. Bluetooth pairing uses Chrome or Edge. Classic
                Bluetooth printers that do not appear in that list should be paired in the operating system first.
            </p>
        </section>
    </div>

    <div v-if="printerModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingPrinter ? 'Edit printer' : 'Add printer' }}</h2>
                <button class="text-sm font-medium" type="button" @click="printerModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitPrinter">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="printer-branch">Branch</label>
                    <select
                        id="printer-branch"
                        v-model="printerForm.branch_id"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                    >
                        <option disabled value="">Choose a branch</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </select>
                    <p v-if="printerForm.errors.branch_id" class="text-sm text-danger">{{ printerForm.errors.branch_id }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="printer-name">Name</label>
                    <input
                        id="printer-name"
                        v-model="printerForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="printerForm.errors.name" class="text-sm text-danger">{{ printerForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="printer-role">Use for</label>
                    <select
                        id="printer-role"
                        v-model="printerForm.role"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        @change="editingPrinter ? null : applyPrinterRoleDefaults()"
                    >
                        <option v-for="role in printerRoles" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                    <p v-if="printerForm.errors.role" class="text-sm text-danger">{{ printerForm.errors.role }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="printer-connection">Connection</label>
                    <select
                        id="printer-connection"
                        v-model="printerForm.connection"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    >
                        <option v-for="connection in printerConnections" :key="connection.value" :value="connection.value">
                            {{ connection.label }}
                        </option>
                    </select>
                    <p class="text-xs text-ink/50 dark:text-foam/50">
                        Bluetooth is the usual POS receipt printer. Browser print uses the system dialog. USB serial is
                        for a cable or a Classic Bluetooth printer that the OS exposes as a COM port.
                    </p>
                    <p v-if="printerForm.errors.connection" class="text-sm text-danger">{{ printerForm.errors.connection }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="printer-width">Width (mm)</label>
                        <input
                            id="printer-width"
                            v-model="printerForm.paper_width_mm"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="20"
                            required
                            type="number"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="printer-height">Height (mm)</label>
                        <input
                            id="printer-height"
                            v-model="printerForm.paper_height_mm"
                            class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                            min="15"
                            placeholder="Roll"
                            type="number"
                        />
                    </div>
                </div>
                <div v-if="printerForm.connection === 'serial'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="printer-baud">Baud rate</label>
                    <select
                        id="printer-baud"
                        v-model="printerForm.baud_rate"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    >
                        <option :value="9600">9600</option>
                        <option :value="19200">19200</option>
                        <option :value="38400">38400</option>
                        <option :value="57600">57600</option>
                        <option :value="115200">115200</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="printerForm.is_default" class="h-4 w-4 accent-pine" type="checkbox" />
                    Default for this use
                </label>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="printerForm.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
                    Active
                </label>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="printerForm.processing"
                >
                    {{ printerForm.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>
</template>
