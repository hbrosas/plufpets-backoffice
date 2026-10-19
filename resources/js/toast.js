export function showToast(message, { tone = 'info' } = {}) {
    if (!message) {
        return;
    }

    window.dispatchEvent(new CustomEvent('app:toast', { detail: { message, tone } }));
}

export function mountToastHost() {
    if (document.getElementById('app-toasts')) {
        return;
    }

    const host = document.createElement('div');
    host.id = 'app-toasts';
    host.setAttribute('aria-live', 'polite');
    host.style.cssText =
        'pointer-events:none;position:fixed;top:1rem;right:1rem;z-index:80;display:flex;flex-direction:column;gap:0.5rem;width:min(22rem,calc(100vw - 2rem));';
    document.body.appendChild(host);

    window.addEventListener('app:toast', (event) => {
        const message = event.detail?.message;

        if (!message) {
            return;
        }

        const warning = event.detail?.tone === 'warning';
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = message;
        button.style.cssText = warning
            ? 'pointer-events:auto;border-radius:0.75rem;border:1px solid #c4a15a;background:#fbf6ea;padding:0.75rem 1rem;text-align:left;font-size:14px;color:#14231f;box-shadow:0 10px 15px rgba(20,35,31,0.12);cursor:pointer;'
            : 'pointer-events:auto;border-radius:0.75rem;border:1px solid rgba(31,111,104,0.2);background:#f4f7f5;padding:0.75rem 1rem;text-align:left;font-size:14px;color:#14231f;box-shadow:0 10px 15px rgba(20,35,31,0.12);cursor:pointer;';
        button.addEventListener('click', () => button.remove());
        host.appendChild(button);
        window.setTimeout(() => button.remove(), 3000);
    });
}
