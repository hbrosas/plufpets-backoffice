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

const sessionId = globalThis.crypto?.randomUUID?.() ?? `session-${Date.now()}`;
const printerChannel = typeof BroadcastChannel === 'function' ? new BroadcastChannel('plufpets-printer-sessions') : null;
const ownedSerial = new Map();
const ownedBluetooth = new Map();
const pendingReplies = new Map();
const watchedBluetooth = new WeakSet();
const serialQueues = new WeakMap();
const serialBaudRates = new WeakMap();
const bluetoothCharacteristics = new WeakMap();

function serialDeviceKey(printer) {
    return `serial:${printer.usb_vendor_id ?? ''}:${printer.usb_product_id ?? ''}:${printer.usb_serial_number ?? ''}`;
}

function bluetoothDeviceKey(printer, device = null) {
    return `bluetooth:${printer?.bluetooth_id || device?.id || ''}`;
}

function enqueuePort(queues, key, task) {
    const previous = queues.get(key) ?? Promise.resolve();
    const next = previous.catch(() => {}).then(task);
    queues.set(key, next);

    return next;
}

function enqueueSerial(port, task) {
    return enqueuePort(serialQueues, port, task);
}

const bluetoothQueues = new WeakMap();

function enqueueBluetooth(device, task) {
    return enqueuePort(bluetoothQueues, device, task);
}

function printerLock(deviceKey, task) {
    if (!navigator.locks?.request) {
        return task();
    }

    return navigator.locks.request(`plufpets-printer:${deviceKey}`, task);
}

function waitForPrinterReply(id, timeoutMs) {
    return new Promise((resolve) => {
        const timer = window.setTimeout(() => {
            pendingReplies.delete(id);
            resolve(null);
        }, timeoutMs);

        pendingReplies.set(id, {
            resolve: (data) => {
                window.clearTimeout(timer);
                resolve(data);
            },
            timer,
        });
    });
}

function newPrinterRequestId() {
    return `${sessionId}:${globalThis.crypto?.randomUUID?.() ?? Date.now()}`;
}

if (printerChannel) {
    printerChannel.addEventListener('message', (event) => {
        const data = event.data;

        if (!data || data.sessionId === sessionId) {
            return;
        }

        if (data.type === 'who-owns') {
            const owns = ownedSerial.has(data.deviceKey) || ownedBluetooth.has(data.deviceKey);

            if (owns) {
                printerChannel.postMessage({
                    type: 'owner',
                    deviceKey: data.deviceKey,
                    requestId: data.requestId,
                    sessionId,
                });
            }

            return;
        }

        if (data.type === 'print') {
            handleRemotePrint(data);

            return;
        }

        if (data.type !== 'owner' && data.type !== 'printed') {
            return;
        }

        const pending = pendingReplies.get(data.requestId);

        if (!pending) {
            return;
        }

        pendingReplies.delete(data.requestId);
        pending.resolve(data);
    });
}

async function anotherSessionOwns(deviceKey) {
    if (!printerChannel) {
        return false;
    }

    const requestId = newPrinterRequestId();
    printerChannel.postMessage({ type: 'who-owns', deviceKey, requestId, sessionId });
    const reply = await waitForPrinterReply(requestId, 300);

    return reply?.type === 'owner';
}

async function forwardPrint(deviceKey, bytes) {
    const requestId = newPrinterRequestId();
    printerChannel.postMessage({ type: 'print', deviceKey, requestId, sessionId, bytes });
    const reply = await waitForPrinterReply(requestId, 10000);

    if (!reply?.ok) {
        throw new Error(reply?.message || 'The register that has this printer did not finish printing.');
    }
}

async function handleRemotePrint(data) {
    try {
        const serial = ownedSerial.get(data.deviceKey);
        const device = ownedBluetooth.get(data.deviceKey);

        if (serial) {
            await writeOwnedSerial(serial, data.bytes);
        } else if (device) {
            await writeBluetoothLocal(device, data.bytes);
        } else {
            throw new PrinterUnavailableError('No printer is connected.');
        }

        printerChannel?.postMessage({ type: 'printed', requestId: data.requestId, ok: true, sessionId });
    } catch (error) {
        const serial = ownedSerial.get(data.deviceKey);

        if (serial && !serial.port.writable && !serial.port.readable) {
            ownedSerial.delete(data.deviceKey);
        }

        printerChannel?.postMessage({
            type: 'printed',
            requestId: data.requestId,
            ok: false,
            sessionId,
            message: error instanceof Error ? error.message : 'Could not print.',
        });
    }
}

if (serialSupported()) {
    navigator.serial.addEventListener('disconnect', (event) => {
        for (const [key, owned] of ownedSerial) {
            if (owned.port === event.target) {
                ownedSerial.delete(key);
                serialBaudRates.delete(owned.port);
            }
        }
    });
}

function portMatchesPrinter(printer, port) {
    const info = port.getInfo();

    if (info.usbVendorId !== printer.usb_vendor_id || info.usbProductId !== printer.usb_product_id) {
        return false;
    }

    if (printer.usb_serial_number && info.serialNumber && info.serialNumber !== printer.usb_serial_number) {
        return false;
    }

    return true;
}

async function selectSerialPort(printer, allowPrompt) {
    const ports = await navigator.serial.getPorts();
    const matches = ports.filter((port) => portMatchesPrinter(printer, port) && port.connected !== false);

    if (matches.length === 1) {
        return matches[0];
    }

    const opened = matches.find((port) => port.writable);

    if (opened) {
        return opened;
    }

    const connected = matches.filter((port) => port.connected === true);

    if (connected.length === 1) {
        return connected[0];
    }

    if (!allowPrompt) {
        throw new PrinterUnavailableError('No printer is connected.');
    }

    return navigator.serial.requestPort();
}

async function closeSerialPort(port) {
    serialBaudRates.delete(port);

    if (!port.readable && !port.writable) {
        return;
    }

    try {
        await port.close();
    } catch {
        // The port may already be closing after the device dropped.
    }
}

async function openSerialPort(port, baudRate) {
    const openBaudRate = serialBaudRates.get(port);

    if (port.writable && (openBaudRate === undefined || openBaudRate === baudRate)) {
        serialBaudRates.set(port, baudRate);

        return;
    }

    if (port.readable || port.writable) {
        await closeSerialPort(port);
    }

    await port.open({ baudRate });
    serialBaudRates.set(port, baudRate);
}

async function writeSerialPort(port, bytes) {
    const writer = port.writable.getWriter();

    try {
        await writer.write(bytes);
    } finally {
        writer.releaseLock();
    }
}

async function writeOwnedSerial(owned, bytes) {
    await enqueueSerial(owned.port, async () => {
        await openSerialPort(owned.port, owned.baudRate);

        try {
            await writeSerialPort(owned.port, bytes);
        } catch (error) {
            if (owned.port.writable) {
                throw error;
            }

            await closeSerialPort(owned.port);
            await openSerialPort(owned.port, owned.baudRate);
            await writeSerialPort(owned.port, bytes);
        }
    });
}

async function claimSerial(printer, bytes, allowPrompt) {
    const deviceKey = serialDeviceKey(printer);
    const baudRate = Number(printer.baud_rate) || 9600;
    const port = await selectSerialPort(printer, allowPrompt);

    await enqueueSerial(port, async () => {
        try {
            await openSerialPort(port, baudRate);
            await writeSerialPort(port, bytes);
        } catch (error) {
            if (port.writable) {
                ownedSerial.set(deviceKey, { port, baudRate });
                throw error;
            }

            if (await anotherSessionOwns(deviceKey)) {
                await forwardPrint(deviceKey, bytes);

                return;
            }

            await closeSerialPort(port);
            await openSerialPort(port, baudRate);
            await writeSerialPort(port, bytes);
        }

        ownedSerial.set(deviceKey, { port, baudRate });
    });
}

async function printSerial(printer, bytes, allowPrompt = true) {
    if (!serialSupported()) {
        if (!allowPrompt) {
            throw new PrinterUnavailableError('No printer is connected.');
        }

        throw new Error('USB serial printers need Chrome or Edge on this register.');
    }

    const deviceKey = serialDeviceKey(printer);
    const owned = ownedSerial.get(deviceKey);

    if (owned) {
        await writeOwnedSerial(owned, bytes);

        return;
    }

    if (await anotherSessionOwns(deviceKey)) {
        await forwardPrint(deviceKey, bytes);

        return;
    }

    await printerLock(deviceKey, async () => {
        const claimed = ownedSerial.get(deviceKey);

        if (claimed) {
            await writeOwnedSerial(claimed, bytes);

            return;
        }

        if (await anotherSessionOwns(deviceKey)) {
            await forwardPrint(deviceKey, bytes);

            return;
        }

        await claimSerial(printer, bytes, allowPrompt);
    });
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

function watchBluetooth(device, keys) {
    if (watchedBluetooth.has(device)) {
        return;
    }

    watchedBluetooth.add(device);
    device.addEventListener('gattserverdisconnected', () => {
        bluetoothCharacteristics.delete(device);
        keys.forEach((key) => {
            if (ownedBluetooth.get(key) === device) {
                ownedBluetooth.delete(key);
            }
        });
    });
}

function claimBluetooth(printer, device) {
    const keys = new Set([bluetoothDeviceKey(printer, device), `bluetooth:${device.id}`]);

    keys.forEach((key) => {
        if (key !== 'bluetooth:') {
            ownedBluetooth.set(key, device);
        }
    });
    watchBluetooth(device, keys);
}

async function characteristicFor(device, server) {
    const cached = bluetoothCharacteristics.get(device);

    if (cached && device.gatt?.connected) {
        return cached;
    }

    const characteristic = await writableCharacteristic(server);
    bluetoothCharacteristics.set(device, characteristic);

    return characteristic;
}

async function connectedBluetoothServer(device) {
    if (device.gatt?.connected) {
        return device.gatt;
    }

    return device.gatt.connect();
}

async function writeBluetoothLocal(device, bytes) {
    await enqueueBluetooth(device, async () => {
        const server = await connectedBluetoothServer(device);

        try {
            await writeChunks(await characteristicFor(device, server), bytes);
        } catch (error) {
            bluetoothCharacteristics.delete(device);

            if (device.gatt?.connected) {
                throw error;
            }

            const reconnected = await device.gatt.connect();
            await writeChunks(await characteristicFor(device, reconnected), bytes);
        }
    });
}

async function printBluetooth(printer, bytes, allowPrompt) {
    const deviceKey = bluetoothDeviceKey(printer);
    const owned = ownedBluetooth.get(deviceKey);

    if (owned) {
        await writeBluetoothLocal(owned, bytes);

        return;
    }

    if (await anotherSessionOwns(deviceKey)) {
        await forwardPrint(deviceKey, bytes);

        return;
    }

    await printerLock(deviceKey, async () => {
        const claimed = ownedBluetooth.get(deviceKey);

        if (claimed) {
            await writeBluetoothLocal(claimed, bytes);

            return;
        }

        if (await anotherSessionOwns(deviceKey)) {
            await forwardPrint(deviceKey, bytes);

            return;
        }

        const device = await resolveBluetoothDevice(printer, allowPrompt);
        claimBluetooth(printer, device);
        await writeBluetoothLocal(device, bytes);
    });
}

export async function ensurePrinterConnection(printer) {
    if (!printer || printer.connection === 'browser') {
        return;
    }

    if (printer.connection === 'bluetooth') {
        if (printer.bluetooth_id && bluetoothDevices.has(printer.bluetooth_id)) {
            return;
        }

        if (!bluetoothSupported()) {
            throw new Error('Bluetooth printers need Chrome or Edge on this register, with Bluetooth turned on.');
        }

        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: BLE_PRINTER_SERVICES,
        });

        rememberBluetoothDevice(device, printer);

        return;
    }

    if (printer.connection !== 'serial') {
        return;
    }

    if (!serialSupported()) {
        throw new Error('USB serial printers need Chrome or Edge on this register.');
    }

    const ports = await navigator.serial.getPorts();
    const matched = ports.some((candidate) => portMatchesPrinter(printer, candidate));

    if (!matched) {
        await navigator.serial.requestPort();
    }
}

export function beginAssignedPrint(printer) {
    if (!printer) {
        return Promise.reject(new Error('Assign a receipt printer for this branch in POS Devices.'));
    }

    if (printer.connection === 'bluetooth') {
        if (ownedBluetooth.has(bluetoothDeviceKey(printer)) || bluetoothDevices.has(printer.bluetooth_id)) {
            return Promise.resolve(null);
        }

        return resolveBluetoothDevice(printer, true);
    }

    return Promise.resolve(null);
}

export async function finishAssignedPrint(printer, text, session) {
    if (!printer) {
        throw new Error('Assign a receipt printer for this branch in POS Devices.');
    }

    if (printer.connection === 'browser') {
        printPlainText(text, printer.paper_width_mm);
        return;
    }

    await session;
    await sendToPrinter(printer, encodeEscPos(text), true);
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
        throw new Error('Assign a receipt printer for this branch in POS Devices.');
    }

    if (printer.connection === 'browser') {
        window.open('/backoffice/pos/drawer', 'pos-drawer', 'width=420,height=320');
        return;
    }

    await sendToPrinter(printer, drawerKickBytes(), allowPrompt);
}

export async function printToAssignedPrinter(printer, text, allowPrompt = true) {
    if (!printer) {
        throw new Error('Assign a receipt printer for this branch in POS Devices.');
    }

    await printEscPos(printer, text, allowPrompt);
}

export async function printSerialTest(printer) {
    await printSerial(printer, testBytes(printer));
}

export async function printBluetoothTest(printer) {
    await printBluetooth(printer, testBytes(printer), true);
}
