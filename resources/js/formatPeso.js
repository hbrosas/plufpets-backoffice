export function formatPeso(value) {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return '₱0.00';
    }

    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
