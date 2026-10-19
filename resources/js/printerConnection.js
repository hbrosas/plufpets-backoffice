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

function encodeEscPos(text) {
    return new TextEncoder().encode(`\x1b\x40${text}\n\n\n\x1d\x56\x41\x03`);
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

    return {
        bluetooth_id: device.id,
        device_name: device.name || 'Bluetooth printer',
        usb_vendor_id: null,
        usb_product_id: null,
        usb_serial_number: '',
    };
}

async function printSerial(printer, bytes) {
    if (!serialSupported()) {
        throw new Error('USB serial printers need Chrome or Edge on this register.');
    }

    const ports = await navigator.serial.getPorts();
    const port =
        ports.find((candidate) => {
            const info = candidate.getInfo();

            return info.usbVendorId === printer.usb_vendor_id && info.usbProductId === printer.usb_product_id;
        }) ?? (await navigator.serial.requestPort());

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

function resolveBluetoothDevice(printer, allowPrompt) {
    if (!bluetoothSupported()) {
        return Promise.reject(
            new Error('Bluetooth printers need Chrome or Edge on this register, with Bluetooth turned on.'),
        );
    }

    const cached = bluetoothDevices.get(printer.bluetooth_id);

    if (cached) {
        return Promise.resolve(cached);
    }

    if (!allowPrompt) {
        return Promise.reject(new Error('This register is not paired with the assigned printer. Click Print to connect.'));
    }

    return navigator.bluetooth
        .requestDevice({
            acceptAllDevices: true,
            optionalServices: BLE_PRINTER_SERVICES,
        })
        .then((device) => rememberBluetoothDevice(device, printer));
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

    const bytes = encodeEscPos(text);
    const device = await session;

    if (printer.connection === 'bluetooth') {
        await writeBluetooth(device, bytes);
        return;
    }

    if (printer.connection === 'serial') {
        await printSerial(printer, bytes);
        return;
    }

    window.print();
}

export async function printEscPos(printer, text, allowPrompt = true) {
    const bytes = encodeEscPos(text);

    if (printer.connection === 'bluetooth') {
        await printBluetooth(printer, bytes, allowPrompt);
        return;
    }

    if (printer.connection === 'serial') {
        await printSerial(printer, bytes);
        return;
    }

    window.print();
}

export async function printToAssignedPrinter(printer, text, allowPrompt = true) {
    if (!printer) {
        throw new Error('Assign a receipt printer for this branch in POS Settings.');
    }

    if (printer.connection === 'browser') {
        window.print();
        return;
    }

    await printEscPos(printer, text, allowPrompt);
}

export async function printSerialTest(printer) {
    await printSerial(printer, testBytes(printer));
}

export async function printBluetoothTest(printer) {
    await printBluetooth(printer, testBytes(printer), true);
}
