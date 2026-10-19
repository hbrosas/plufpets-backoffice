<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const supported = ref(false);
const active = ref(false);

function currentFullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
}

function sync() {
    active.value = currentFullscreenElement() !== null;
}

function enterFullscreen() {
    const root = document.documentElement;

    if (typeof root.requestFullscreen !== 'function') {
        root.webkitRequestFullscreen?.();

        return;
    }

    try {
        root.requestFullscreen({ navigationUI: 'hide' })?.catch(() => {});
    } catch {
        try {
            root.requestFullscreen()?.catch(() => {});
        } catch {
            root.webkitRequestFullscreen?.();
        }
    }
}

function toggle() {
    if (currentFullscreenElement()) {
        const exit = document.exitFullscreen || document.webkitExitFullscreen;

        if (exit) {
            exit.call(document);
        }

        return;
    }

    enterFullscreen();
}

onMounted(() => {
    const root = document.documentElement;

    supported.value =
        typeof root.requestFullscreen === 'function' || typeof root.webkitRequestFullscreen === 'function';
    sync();
    document.addEventListener('fullscreenchange', sync);
    document.addEventListener('webkitfullscreenchange', sync);
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', sync);
    document.removeEventListener('webkitfullscreenchange', sync);
});
</script>

<template>
    <button
        v-if="supported"
        class="fixed bottom-[max(0.75rem,env(safe-area-inset-bottom))] left-[max(0.75rem,env(safe-area-inset-left))] z-[55] flex h-11 w-11 touch-manipulation items-center justify-center rounded-xl border border-mist bg-foam/95 text-ink shadow-lg backdrop-blur-md dark:border-white/15 dark:bg-ink/95 dark:text-foam"
        type="button"
        :aria-pressed="active"
        :aria-label="active ? 'Exit full screen' : 'Full screen'"
        @click="toggle"
    >
        <svg
            v-if="active"
            class="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 9H4.5M9 9V4.5M9 9 4.5 4.5M15 9h4.5M15 9V4.5M15 9l4.5-4.5M9 15H4.5M9 15v4.5M9 15l-4.5 4.5M15 15h4.5M15 15v4.5M15 15l4.5 4.5"
            />
        </svg>
        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"
            />
        </svg>
    </button>
</template>
