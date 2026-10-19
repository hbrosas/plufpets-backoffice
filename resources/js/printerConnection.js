const BLE_PRINTER_SERVICES = [
    '000018f0-0000-1000-8000-00805f9b34fb',
    '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
];

function usbLabel(info) {
    const vendor = Number(info.usbVendorId || 0)
        .toString(16)
        .padStart(4, '0');
    const product = Number(info.usbProductId || 0)
        .toString(16)
        .padStart(4, '0');

    return `USB ${vendor}:${product}`.toUpperCase();
}

function encodeEscPos(text, kickDrawer = false) {
    const body = String(text ?? '').replace(/[ \t\r\n]+$/u, '');
    const kick = kickDrawer ? '\x1b\x70\x00\x19\xfa' : '';

    return new TextEncoder().encode(`\x1b\x40${kick}${body}\n\x1d\x56\x41\x00`);
}

function escapeReceipt(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function printPlainText(text, widthMm = 80) {
    const width = Number(widthMm) > 0 ? Number(widthMm) : 80;
    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    document.body.appendChild(iframe);

    const doc = iframe.contentDocument;
    doc.open();
    doc.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>
@page { margin: 0; size: ${width}mm auto; }
html, body { margin: 0; padding: 0; height: auto; }
pre { margin: 0; padding: 2mm 2mm 0; font: 12px/1.2 ui-monospace, monospace; white-space: pre-wrap; }
</style></head><body><pre>${escapeReceipt(text)}</pre></body></html>`);
    doc.close();
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    window.setTimeout(() => iframe.remove(), 1500);
}

function drawerKickBytes() {
    return new Uint8Array([0x1b, 0x70, 0x00, 0x19, 0xfa]);
}

function testBytes(printer) {
    return encodeEscPos(`${printer.name}\n${printer.role_label} test\n${new Date().toLocaleString()}\n`);
}

export function serialSupported() {
    return typeof navigator !== 'undefined' && 'serial' in navigator;
}

export function bluetoothSupported() {
    return typeof navigator !== 'undefined' && 'bluetooth' in navigator;
}

export async function requestSerialIdentity() {
    if (!serialSupported()) {
        throw new Error('USB serial printers need Chrome or Edge on this register.');
    }

    const port = await navigator.serial.requestPort();
    const info = port.getInfo();

    if (!info.usbVendorId || !info.usbProductId) {
        throw new Error('That port did not report a USB identity. Try another cable or adapter.');
    }

    return {
        usb_vendor_id: info.usbVendorId,
        usb_product_id: info.usbProductId,
        usb_serial_number: info.serialNumber ?? '',
        device_name: usbLabel(info),
        bluetooth_id: '',
    };
}

export async function requestBluetoothIdentity() {
    if (!bluetoothSupported()) {
        throw new Error('Bluetooth printers need Chrome or Edge on this register, with Bluetooth turned on.');
    }

    const device = await navigator.bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: BLE_PRINTER_SERVICES,
    });

    rememberBluetoothDevice(device);

    return {
        bluetooth_id: device.id,
        device_name: device.name || 'Bluetooth printer',
        usb_vendor_id: null,
        usb_product_id: null,
        usb_serial_number: '',
    };
}

async function printSerial(printer, bytes, allowPrompt = true) {
    if (!serialSupported()) {
        if (!allowPrompt) {
            throw new PrinterUnavailableError('No printer is connected.');
        }

        throw new Error('USB serial printers need Chrome or Edge on this register.');
    }

    const ports = await navigator.serial.getPorts();
    let port = ports.find((candidate) => {
        const info = candidate.getInfo();

        return info.usbVendorId === printer.usb_vendor_id && info.usbProductId === printer.usb_product_id;
    });

    if (!port) {
        if (!allowPrompt) {
            throw new PrinterUnavailableError('No printer is connected.');
        }

        port = await navigator.serial.requestPort();
    }

    await port.open({ baudRate: Number(printer.baud_rate) || 9600 });

    try {
        const writer = port.writable.getWriter();
        await writer.write(bytes);
        writer.releaseLock();
    } finally {
        await port.close();
    }
}

async function writableCharacteristic(server) {
    const services = await server.getPrimaryServices();

    for (const service of services) {
        const characteristics = await service.getCharacteristics();

        for (const characteristic of characteristics) {
            if (characteristic.properties.writeWithoutResponse || characteristic.properties.write) {
                return characteristic;
            }
        }
    }

    throw new Error(
        'This Bluetooth device has no printable service. Pair it in the OS first; Classic Bluetooth printers often appear as a serial port.',
    );
}

async function writeChunks(characteristic, bytes) {
    const chunkSize = 20;

    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
        const chunk = bytes.slice(offset, offset + chunkSize);

        if (characteristic.properties.writeWithoutResponse) {
            await characteristic.writeValueWithoutResponse(chunk);
        } else {
            await characteristic.writeValue(chunk);
        }
    }
}

export class PrinterUnavailableError extends Error {
    constructor(message = 'No printer is connected.') {
        super(message);
        this.name = 'PrinterUnavailableError';
    }
}

const bluetoothDevices = new Map();

function rememberBluetoothDevice(device, printer = null) {
    bluetoothDevices.set(device.id, device);

    if (printer?.bluetooth_id) {
        bluetoothDevices.set(printer.bluetooth_id, device);
    }

    return device;
}

if (typeof navigator !== 'undefined' && navigator.bluetooth && typeof navigator.bluetooth.getDevices === 'function') {
    navigator.bluetooth
        .getDevices()
        .then((devices) => {
            devices.forEach((device) => rememberBluetoothDevice(device));
        })
        .catch(() => {});
}

async function savedBluetoothDevice(printer) {
    const cached = bluetoothDevices.get(printer.bluetooth_id);

    if (cached) {
        return cached;
    }

    if (!navigator.bluetooth || typeof navigator.bluetooth.getDevices !== 'function') {
        return null;
    }

    const devices = await navigator.bluetooth.getDevices();
    devices.forEach((device) => rememberBluetoothDevice(device));

    return bluetoothDevices.get(printer.bluetooth_id) ?? null;
}

function resolveBluetoothDevice(printer, allowPrompt) {
    if (!bluetoothSupported()) {
        const message = 'Bluetooth printers need Chrome or Edge on this register, with Bluetooth turned on.';

        return Promise.reject(allowPrompt ? new Error(message) : new PrinterUnavailableError('No printer is connected.'));
    }

    return savedBluetoothDevice(printer).then((device) => {
        if (device) {
            return device;
        }

        if (!allowPrompt) {
            return Promise.reject(new PrinterUnavailableError('No printer is connected.'));
        }

        return navigator.bluetooth
            .requestDevice({
                acceptAllDevices: true,
                optionalServices: BLE_PRINTER_SERVICES,
            })
            .then((requested) => rememberBluetoothDevice(requested, printer));
    });
}

async function writeBluetooth(device, bytes) {
    const server = await device.gatt.connect();

    try {
        await writeChunks(await writableCharacteristic(server), bytes);
    } finally {
        server.disconnect();
    }
}

async function printBluetooth(printer, bytes, allowPrompt) {
    await writeBluetooth(await resolveBluetoothDevice(printer, allowPrompt), bytes);
}

export function beginAssignedPrint(printer) {
    if (!printer) {
        return Promise.reject(new Error('Assign a receipt printer for this branch in POS Settings.'));
    }

    if (printer.connection === 'bluetooth') {
        return resolveBluetoothDevice(printer, true);
    }

    return Promise.resolve(null);
}

export async function finishAssignedPrint(printer, text, session) {
    if (!printer) {
        throw new Error('Assign a receipt printer for this branch in POS Settings.');
    }

    if (printer.connection === 'browser') {
        printPlainText(text, printer.paper_width_mm);
        return;
    }

    const bytes = encodeEscPos(text);
    const device = await session;

    if (printer.connection === 'bluetooth') {
        await writeBluetooth(device, bytes);
        return;
    }

    if (printer.connection === 'serial') {
        await printSerial(printer, bytes);
    }
}

async function sendToPrinter(printer, bytes, allowPrompt) {
    if (printer.connection === 'bluetooth') {
        await printBluetooth(printer, bytes, allowPrompt);
        return;
    }

    if (printer.connection === 'serial') {
        await printSerial(printer, bytes, allowPrompt);
        return;
    }

    throw new Error('This printer cannot send ESC/POS commands.');
}

export async function printEscPos(printer, text, allowPrompt = true) {
    if (printer.connection === 'browser') {
        printPlainText(text, printer.paper_width_mm);
        return;
    }

    await sendToPrinter(printer, encodeEscPos(text), allowPrompt);
}

export async function printReceipt(printer, text, { openDrawer = false, allowPrompt = false } = {}) {
    if (!printer) {
        throw new PrinterUnavailableError('No printer is connected.');
    }

    if (printer.connection === 'browser') {
        if (openDrawer) {
            window.open('/backoffice/pos/drawer', 'pos-drawer', 'width=420,height=320');
        }

        printPlainText(text, printer.paper_width_mm);
        return;
    }

    await sendToPrinter(printer, encodeEscPos(text, openDrawer), allowPrompt);
}

export async function openAssignedDrawer(printer, allowPrompt = true) {
    if (!printer) {
        throw new Error('Assign a receipt printer for this branch in POS Settings.');
    }

    if (printer.connection === 'browser') {
        window.open('/backoffice/pos/drawer', 'pos-drawer', 'width=420,height=320');
        return;
    }

    await sendToPrinter(printer, drawerKickBytes(), allowPrompt);
}

export async function printToAssignedPrinter(printer, text, allowPrompt = true) {
    if (!printer) {
        throw new Error('Assign a receipt printer for this branch in POS Settings.');
    }

    await printEscPos(printer, text, allowPrompt);
}

export async function printSerialTest(printer) {
    await printSerial(printer, testBytes(printer));
}

export async function printBluetoothTest(printer) {
    await printBluetooth(printer, testBytes(printer), true);
}
