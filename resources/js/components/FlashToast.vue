<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const toasts = ref([]);
let sequence = 0;
const timers = new Map();
const recent = new Map();
const durationMs = 5000;

function dismiss(id) {
    const timer = timers.get(id);

    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }

    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function normalizeTone(tone) {
    if (tone === 'error' || tone === 'warning') {
        return tone;
    }

    return 'success';
}

function pushToast(message, tone = 'success', duration = durationMs) {
    if (typeof message !== 'string' || message.trim() === '') {
        return;
    }

    const resolvedTone = normalizeTone(tone);
    const now = Date.now();
    const key = `${resolvedTone}:${message}`;

    if (now - (recent.get(key) ?? 0) < 400) {
        return;
    }

    if (toasts.value.some((toast) => toast.message === message && toast.tone === resolvedTone)) {
        return;
    }

    recent.set(key, now);
    const id = ++sequence;
    toasts.value = [...toasts.value, { id, message, tone: resolvedTone }];

    if (duration > 0) {
        timers.set(id, setTimeout(() => dismiss(id), duration));
    }
}

function errorMessages(errors) {
    if (!errors || typeof errors !== 'object') {
        return [];
    }

    const messages = [];

    Object.values(errors).forEach((value) => {
        if (typeof value === 'string' && value !== '') {
            messages.push(value);

            return;
        }

        if (Array.isArray(value)) {
            value.forEach((item) => {
                if (typeof item === 'string' && item !== '') {
                    messages.push(item);
                }
            });
        }
    });

    return [...new Set(messages)];
}

function pushErrors(errors) {
    const messages = errorMessages(errors);

    if (messages.length === 0) {
        return;
    }

    const visible = messages.slice(0, 3);
    const extra = messages.length - visible.length;
    const summary = extra > 0 ? `${visible.join('\n')}\nAnd ${extra} more.` : visible.join('\n');

    pushToast(summary, 'error');
}

function importErrorMessage(errors) {
    if (!Array.isArray(errors) || errors.length === 0) {
        return '';
    }

    if (errors.length === 1) {
        const error = errors[0];

        return `Row ${error.row}: ${error.message}`;
    }

    return `${errors.length} rows were skipped.`;
}

watch(
    () => `${page.props.flash?.status_at ?? ''}:${page.props.flash?.status ?? ''}`,
    () => {
        pushToast(page.props.flash?.status, 'success');
    },
    { immediate: true },
);

watch(
    () => page.props.errors,
    (errors) => {
        pushErrors(errors);
    },
    { deep: true, immediate: true },
);

watch(
    () => page.props.flash?.import_errors,
    (errors) => {
        pushToast(importErrorMessage(errors), 'error');
    },
    { immediate: true },
);

function onAppToast(event) {
    const duration = Number(event.detail?.duration ?? durationMs);

    pushToast(event.detail?.message, event.detail?.tone, Number.isFinite(duration) ? duration : durationMs);
}

onMounted(() => {
    window.addEventListener('app:toast', onAppToast);
});

onUnmounted(() => {
    window.removeEventListener('app:toast', onAppToast);
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
});

function toneClass(tone) {
    if (tone === 'error') {
        return 'border-danger/30 bg-danger/10 text-danger';
    }

    if (tone === 'warning') {
        return 'border-sand bg-[#fbf6ea] text-ink dark:border-sand/60 dark:bg-ink dark:text-sand';
    }

    return 'border-pine/20 bg-foam text-pine dark:border-sand/30 dark:bg-ink dark:text-sand';
}
</script>

<template>
    <div
        class="pointer-events-none fixed top-4 right-4 z-[90] flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2"
        aria-live="polite"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg"
            :class="toneClass(toast.tone)"
            :role="toast.tone === 'error' ? 'alert' : 'status'"
        >
            <p class="min-w-0 flex-1 whitespace-pre-line">{{ toast.message }}</p>
            <button
                class="shrink-0 rounded-md px-1 text-base leading-none opacity-60 hover:opacity-100"
                type="button"
                aria-label="Close"
                @click="dismiss(toast.id)"
            >
                ×
            </button>
        </div>
    </div>
</template>
