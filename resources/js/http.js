function csrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function firstError(errors) {
    if (!errors || typeof errors !== 'object') {
        return '';
    }

    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : String(first ?? '');
}

export async function jsonRequest(url, { method = 'GET', body } = {}) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(
            payload.message || payload.status || firstError(payload.errors) || 'Request failed.',
        );
        error.status = response.status;
        error.errors = payload.errors ?? {};
        throw error;
    }

    return payload;
}
