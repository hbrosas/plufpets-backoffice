import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import FullscreenButton from './components/FullscreenButton.vue';
import PwaInstallPrompt from './components/PwaInstallPrompt.vue';
import { mountToastHost } from './toast';

const appName = import.meta.env.VITE_APP_NAME || 'Employee Portal';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
        mountPwaInstallPrompt();
        mountFullscreenButton();
        mountToastHost();
    },
    progress: {
        color: '#1f6f68',
    },
});

function mountFullscreenButton() {
    if (document.getElementById('fullscreen-button-root')) {
        return;
    }

    const root = document.createElement('div');
    root.id = 'fullscreen-button-root';
    document.body.appendChild(root);
    createApp(FullscreenButton).mount(root);
}

function mountPwaInstallPrompt() {
    if (document.getElementById('pwa-install-root')) {
        return;
    }

    const root = document.createElement('div');
    root.id = 'pwa-install-root';
    document.body.appendChild(root);
    createApp(PwaInstallPrompt).mount(root);
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            })
            .catch((error) => {
                console.error('Service worker registration failed.', error);
            });
    });
}
