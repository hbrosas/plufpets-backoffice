import { printToAssignedPrinter } from './printerConnection';

function payload(id) {
    const node = document.getElementById(id);

    if (!node) {
        return null;
    }

    return JSON.parse(node.textContent);
}

async function printZReport(allowPrompt) {
    const status = document.getElementById('print-status');
    const printer = payload('z-report-printer');
    const receipt = payload('z-report-receipt');

    if (status) {
        status.textContent = printer?.device_name
            ? `Printing on ${printer.device_name}…`
            : 'Printing…';
    }

    try {
        await printToAssignedPrinter(printer, typeof receipt === 'string' ? receipt : '', allowPrompt);

        if (status) {
            status.textContent = printer && printer.connection !== 'browser' ? 'Sent to the assigned printer.' : '';
        }
    } catch (error) {
        if (status) {
            status.textContent = error instanceof Error ? error.message : 'Could not print on the assigned printer.';
        }
    }
}

document.getElementById('print-z-report')?.addEventListener('click', () => {
    printZReport(true);
});

if (document.getElementById('z-report-print')?.dataset.autoprint === '1') {
    window.addEventListener('load', () => {
        printZReport(false);
    });
}
