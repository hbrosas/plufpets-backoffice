<script setup>
import { computed, onUnmounted, ref } from 'vue';

const deferredPrompt = ref(null);
const dismissed = ref(readDismissed());
const installed = ref(runningStandalone());
const showIosInstructions = ref(false);

const appName =
    document.querySelector('meta[name="apple-mobile-web-app-title"]')?.getAttribute('content') || 'Pluf Pets';

const visible = computed(
    () => !dismissed.value && !installed.value && (deferredPrompt.value !== null || showIosInstructions.value),
);

function runningStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function readDismissed() {
    try {
        return window.localStorage.getItem('pwa-install-dismissed') === '1';
    } catch {
        return false;
    }
}

function isIosSafari() {
    const userAgent = window.navigator.userAgent;
    const iOS =
        /iphone|ipad|ipod/i.test(userAgent) ||
        (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    const safari = /safari/i.test(userAgent) && !/crios|fxios|edgios|opios/i.test(userAgent);

    return iOS && safari;
}

function onBeforeInstallPrompt(event) {
    if (readDismissed() || runningStandalone()) {
        return;
    }

    event.preventDefault();
    deferredPrompt.value = event;
}

function onAppInstalled() {
    installed.value = true;
    deferredPrompt.value = null;
}

async function install() {
    const prompt = deferredPrompt.value;

    if (!prompt) {
        return;
    }

    try {
        await prompt.prompt();
        await prompt.userChoice;
    } finally {
        deferredPrompt.value = null;
    }
}

function dismiss() {
    dismissed.value = true;

    try {
        window.localStorage.setItem('pwa-install-dismissed', '1');
    } catch {
        // Private browsing can block storage. Hiding the prompt for this visit is enough.
    }
}

window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
window.addEventListener('appinstalled', onAppInstalled);

if (!dismissed.value && !installed.value && isIosSafari()) {
    showIosInstructions.value = true;
}

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', onAppInstalled);
});
</script>

<template>
    <div
        v-if="visible"
        class="pointer-events-none fixed inset-x-0 top-[max(0.75rem,env(safe-area-inset-top))] z-[45] flex justify-center px-3"
    >
        <section
            class="pointer-events-auto flex w-full max-w-md flex-col gap-3 rounded-2xl border border-mist bg-foam px-4 py-3 text-ink shadow-lg dark:border-white/10 dark:bg-ink dark:text-foam"
            aria-label="Install app"
        >
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-pine text-sm font-semibold text-foam">
                    PP
                </span>
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <p class="text-sm font-medium">Install {{ appName }}</p>
                    <p v-if="deferredPrompt" class="text-sm text-ink/70 dark:text-foam/70">
                        Add it to your home screen and open it like an app.
                    </p>
                    <p v-else class="text-sm text-ink/70 dark:text-foam/70">
                        In Safari, tap Share, then Add to Home Screen.
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button
                    class="h-10 rounded-xl px-3 text-sm font-medium text-ink/70 dark:text-foam/70"
                    type="button"
                    @click="dismiss"
                >
                    Not now
                </button>
                <button
                    v-if="deferredPrompt"
                    class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam"
                    type="button"
                    @click="install"
                >
                    Install
                </button>
            </div>
        </section>
    </div>
</template>
