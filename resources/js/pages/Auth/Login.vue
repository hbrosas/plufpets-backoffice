<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const appName = computed(() => page.props.appName);

const form = useForm({
    employee_number: '',
    pin: '',
});

const activeField = ref('employee_number');
const pinVisible = ref(false);

const employeeNumberMaxLength = 10;
const pinMaxLength = 8;
const digitKeys = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];

const formError = computed(() => form.errors.employee_number || form.errors.pin || '');

const canSignIn = computed(
    () => form.employee_number.length > 0 && form.pin.length >= 4 && !form.processing,
);

const pinDisplay = computed(() => (pinVisible.value ? form.pin : '•'.repeat(form.pin.length)));

function selectField(field) {
    activeField.value = field;
}

function appendDigit(digit) {
    const field = activeField.value;
    const maxLength = field === 'pin' ? pinMaxLength : employeeNumberMaxLength;

    if (form[field].length >= maxLength) {
        return;
    }

    form[field] += digit;
    form.clearErrors();
}

function backspace() {
    const field = activeField.value;
    form[field] = form[field].slice(0, -1);
    form.clearErrors();
}

function clearActiveField() {
    form[activeField.value] = '';
    form.clearErrors();
}

function submit() {
    if (!canSignIn.value) {
        return;
    }

    form.post('/backoffice/login', {
        onFinish: () => {
            form.reset('pin');
            activeField.value = 'pin';
        },
    });
}

function onKeydown(event) {
    if (event.metaKey || event.ctrlKey || event.altKey) {
        return;
    }

    if (/^\d$/.test(event.key)) {
        event.preventDefault();
        appendDigit(event.key);

        return;
    }

    if (event.key === 'Backspace') {
        event.preventDefault();
        backspace();

        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        submit();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Sign in" />

    <main class="flex min-h-dvh flex-col items-center px-4">
        <div class="m-auto flex w-full max-w-4xl flex-col gap-8 py-10">
            <div class="flex flex-col items-center gap-2 text-center">
                <img
                    v-if="page.props.businessLogoUrl"
                    :src="page.props.businessLogoUrl"
                    alt=""
                    class="h-11 w-11 rounded-xl object-contain"
                />
                <span v-else class="flex h-11 w-11 items-center justify-center rounded-xl bg-pine text-base font-semibold text-foam">PP</span>
                <div class="flex flex-col">
                    <p class="text-xs font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">{{ appName }}</p>
                    <h1 class="text-2xl font-medium">Sign in</h1>
                </div>
            </div>

            <p
                v-if="formError"
                class="rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-center text-sm text-danger"
                role="alert"
            >
                {{ formError }}
            </p>

            <div class="grid items-stretch gap-6 sm:grid-cols-2 sm:gap-8">
                <div class="flex h-full flex-col gap-3">
                    <button
                        class="flex min-h-32 flex-1 flex-col justify-center gap-3 rounded-2xl border px-5 py-6 text-left transition"
                        :class="
                            activeField === 'employee_number'
                                ? 'border-pine bg-white ring-2 ring-sand/80 dark:border-sand dark:bg-white/10'
                                : 'border-mist bg-white dark:border-white/10 dark:bg-white/5'
                        "
                        type="button"
                        :aria-pressed="activeField === 'employee_number'"
                        @click="selectField('employee_number')"
                    >
                        <span class="text-sm font-medium text-ink/70 dark:text-foam/70">Employee number</span>
                        <span class="truncate text-3xl tracking-[0.14em] tabular-nums">
                            {{ form.employee_number || '—' }}
                        </span>
                    </button>

                    <div
                        class="flex min-h-32 flex-1 cursor-pointer flex-col justify-center gap-3 rounded-2xl border px-5 py-6 text-left transition"
                        :class="
                            activeField === 'pin'
                                ? 'border-pine bg-white ring-2 ring-sand/80 dark:border-sand dark:bg-white/10'
                                : 'border-mist bg-white dark:border-white/10 dark:bg-white/5'
                        "
                        @click="selectField('pin')"
                    >
                        <span class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-ink/70 dark:text-foam/70">PIN code</span>
                            <button
                                class="text-sm font-medium text-pine dark:text-sand"
                                type="button"
                                @click.stop="pinVisible = !pinVisible"
                            >
                                {{ pinVisible ? 'Hide' : 'Show' }}
                            </button>
                        </span>
                        <span class="truncate text-3xl tracking-[0.2em]">
                            {{ pinDisplay || '—' }}
                        </span>
                    </div>

                    <button
                        class="flex h-16 shrink-0 items-center justify-center rounded-2xl bg-pine text-xl font-semibold text-foam transition hover:bg-pine/90 disabled:cursor-not-allowed disabled:opacity-50 sm:h-20"
                        type="button"
                        :disabled="!canSignIn"
                        @click="submit"
                    >
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </div>

                <div class="grid h-full grid-cols-3 grid-rows-4 gap-3">
                    <button
                        v-for="digit in digitKeys"
                        :key="digit"
                        class="flex h-full min-h-20 items-center justify-center rounded-2xl border border-mist bg-white text-3xl font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 sm:min-h-24 dark:border-white/10 dark:bg-white/5"
                        type="button"
                        :disabled="form.processing"
                        @click="appendDigit(digit)"
                    >
                        {{ digit }}
                    </button>

                    <button
                        class="flex h-full min-h-20 items-center justify-center rounded-2xl border border-mist bg-white text-lg font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 sm:min-h-24 dark:border-white/10 dark:bg-white/5"
                        type="button"
                        aria-label="Delete"
                        :disabled="form.processing"
                        @click="backspace"
                    >
                        Delete
                    </button>
                    <button
                        class="flex h-full min-h-20 items-center justify-center rounded-2xl border border-mist bg-white text-3xl font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 sm:min-h-24 dark:border-white/10 dark:bg-white/5"
                        type="button"
                        :disabled="form.processing"
                        @click="appendDigit('0')"
                    >
                        0
                    </button>
                    <button
                        class="flex h-full min-h-20 items-center justify-center rounded-2xl border border-mist bg-white text-lg font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 sm:min-h-24 dark:border-white/10 dark:bg-white/5"
                        type="button"
                        aria-label="Clear"
                        :disabled="form.processing"
                        @click="clearActiveField"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>
