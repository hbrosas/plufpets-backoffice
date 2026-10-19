import { PrinterUnavailableError, printReceipt } from './printerConnection';
import { mountToastHost, showToast } from './toast';

function payload(id) {
    const node = document.getElementById(id);

    if (!node) {
        return null;
    }

    return JSON.parse(node.textContent);
}

async function printZReport() {
    const printer = payload('z-report-printer');
    const receipt = payload('z-report-receipt');

    try {
        await printReceipt(printer, typeof receipt === 'string' ? receipt : '', {
            openDrawer: document.getElementById('z-report-print')?.dataset.autoprint === '1',
            allowPrompt: true,
        });

        if (printer && printer.connection !== 'browser') {
            showToast(printer.device_name ? `Sent to ${printer.device_name}.` : 'Sent to the connected printer.');
        }
    } catch (error) {
        if (error instanceof PrinterUnavailableError) {
            showToast('No printer is connected.', { tone: 'warning' });

            return;
        }

        showToast(error instanceof Error ? error.message : 'Could not print on the assigned printer.');
    }
}

mountToastHost();

document.getElementById('print-z-report')?.addEventListener('click', () => {
    printZReport();
});

if (document.getElementById('z-report-print')?.dataset.autoprint === '1') {
    window.addEventListener('load', () => {
        printZReport();
    });
}
