<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const toasts = ref([]);
let sequence = 0;
let lastMessage = '';
let lastAt = 0;
const timers = [];

function dismiss(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pushToast(message) {
    if (!message) {
        return;
    }

    const now = Date.now();

    if (message === lastMessage && now - lastAt < 400) {
        return;
    }

    lastMessage = message;
    lastAt = now;
    const id = ++sequence;
    toasts.value = [...toasts.value, { id, message }];
    timers.push(setTimeout(() => dismiss(id), 3000));
}

function onSuccess(event) {
    pushToast(event.detail.page?.props?.flash?.status);
}

function onFlash(event) {
    pushToast(event.detail.flash?.status);
}

function onAppToast(event) {
    pushToast(event.detail?.message);
}

watch(
    () => `${page.props.flash?.status_at ?? ''}:${page.props.flash?.status ?? ''}`,
    () => {
        pushToast(page.props.flash?.status);
    },
);

onMounted(() => {
    pushToast(page.props.flash?.status);
    window.addEventListener('app:toast', onAppToast);
});

onUnmounted(
    router.on('success', onSuccess),
);

onUnmounted(
    router.on('flash', onFlash),
);

onUnmounted(() => {
    window.removeEventListener('app:toast', onAppToast);
});

onUnmounted(() => {
    timers.forEach((timer) => clearTimeout(timer));
});
</script>

<template>
    <div
        class="pointer-events-none fixed top-4 right-4 z-[80] flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2"
        aria-live="polite"
    >
        <button
            v-for="toast in toasts"
            :key="toast.id"
            class="pointer-events-auto rounded-xl border border-pine/20 bg-foam px-4 py-3 text-left text-sm text-pine shadow-lg dark:border-sand/30 dark:bg-ink dark:text-sand"
            type="button"
            @click="dismiss(toast.id)"
        >
            {{ toast.message }}
        </button>
    </div>
</template>
