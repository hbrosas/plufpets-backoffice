export function showToast(message, { tone = 'info', duration = 5000 } = {}) {
    if (!message) {
        return;
    }

    window.dispatchEvent(new CustomEvent('app:toast', { detail: { message, tone, duration } }));
}

function toneStyle(tone) {
    if (tone === 'error') {
        return 'border:1px solid rgba(176,62,48,0.35);background:#fdeceb;color:#8d2b22;';
    }

    if (tone === 'warning') {
        return 'border:1px solid #c4a15a;background:#fbf6ea;color:#14231f;';
    }

    return 'border:1px solid rgba(31,111,104,0.2);background:#f4f7f5;color:#14231f;';
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

        const toast = document.createElement('div');
        toast.setAttribute('role', event.detail?.tone === 'error' ? 'alert' : 'status');
        toast.style.cssText = `pointer-events:auto;display:flex;align-items:flex-start;gap:0.75rem;border-radius:0.75rem;padding:0.75rem 1rem;text-align:left;font-size:14px;box-shadow:0 10px 15px rgba(20,35,31,0.12);${toneStyle(event.detail?.tone)}`;

        const text = document.createElement('p');
        text.textContent = message;
        text.style.cssText = 'margin:0;flex:1;min-width:0;';

        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = '×';
        close.setAttribute('aria-label', 'Close');
        close.style.cssText = 'border:0;background:transparent;padding:0 0.15rem;font-size:16px;line-height:1;cursor:pointer;color:inherit;opacity:0.6;';

        const remove = () => toast.remove();
        close.addEventListener('click', remove);
        toast.append(text, close);
        host.appendChild(toast);

        const duration = Number(event.detail?.duration ?? 5000);

        if (duration > 0) {
            window.setTimeout(remove, duration);
        }
    });
}
