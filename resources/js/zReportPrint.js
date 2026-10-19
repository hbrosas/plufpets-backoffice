import { printToAssignedPrinter } from './printerConnection';
import { mountToastHost, showToast } from './toast';

function payload(id) {
    const node = document.getElementById(id);

    if (!node) {
        return null;
    }

    return JSON.parse(node.textContent);
}

async function printZReport(allowPrompt) {
    const printer = payload('z-report-printer');
    const receipt = payload('z-report-receipt');

    showToast(printer?.device_name ? `Printing on ${printer.device_name}…` : 'Printing…');

    try {
        await printToAssignedPrinter(printer, typeof receipt === 'string' ? receipt : '', allowPrompt);

        if (printer && printer.connection !== 'browser') {
            showToast('Sent to the assigned printer.');
        }
    } catch (error) {
        showToast(error instanceof Error ? error.message : 'Could not print on the assigned printer.');
    }
}

mountToastHost();

document.getElementById('print-z-report')?.addEventListener('click', () => {
    printZReport(true);
});

if (document.getElementById('z-report-print')?.dataset.autoprint === '1') {
    window.addEventListener('load', () => {
        printZReport(false);
    });
}
