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

    <main class="mx-auto flex h-dvh max-h-dvh w-full max-w-md flex-col gap-[clamp(0.5rem,1.6vh,1rem)] overflow-hidden px-4 py-[clamp(0.75rem,2vh,1.25rem)]">
        <div class="flex shrink-0 flex-col items-center gap-1 text-center">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-pine text-sm font-semibold text-foam">PP</span>
            <div class="flex flex-col">
                <p class="text-xs font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">{{ appName }}</p>
                <h1 class="text-xl font-medium [@media(min-height:760px)]:text-2xl">Sign in</h1>
            </div>
        </div>

        <p
            v-if="formError"
            class="shrink-0 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-center text-sm text-danger"
            role="alert"
        >
            {{ formError }}
        </p>

        <div class="grid shrink-0 grid-cols-1 gap-2 [@media(max-height:720px)]:grid-cols-2">
            <button
                class="flex shrink-0 flex-col gap-0.5 rounded-2xl border px-3 py-2 text-left transition"
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
                <span class="min-h-7 truncate text-[clamp(1.15rem,3.4vh,1.5rem)] tracking-[0.14em] tabular-nums">
                    {{ form.employee_number || '—' }}
                </span>
            </button>

            <div
                class="flex shrink-0 cursor-pointer flex-col gap-0.5 rounded-2xl border px-3 py-2 text-left transition"
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
                <span class="min-h-7 truncate text-[clamp(1.15rem,3.4vh,1.5rem)] tracking-[0.2em]">
                    {{ pinDisplay || '—' }}
                </span>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-3 grid-rows-4 gap-2">
            <button
                v-for="digit in digitKeys"
                :key="digit"
                class="h-full min-h-0 rounded-2xl border border-mist bg-white text-[clamp(1.05rem,3vh,1.5rem)] font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 dark:border-white/10 dark:bg-white/5"
                type="button"
                :disabled="form.processing"
                @click="appendDigit(digit)"
            >
                {{ digit }}
            </button>

            <button
                class="h-full min-h-0 rounded-2xl border border-mist bg-white text-[clamp(0.8rem,2.2vh,1rem)] font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 dark:border-white/10 dark:bg-white/5"
                type="button"
                aria-label="Delete"
                :disabled="form.processing"
                @click="backspace"
            >
                Delete
            </button>
            <button
                class="h-full min-h-0 rounded-2xl border border-mist bg-white text-[clamp(1.05rem,3vh,1.5rem)] font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 dark:border-white/10 dark:bg-white/5"
                type="button"
                :disabled="form.processing"
                @click="appendDigit('0')"
            >
                0
            </button>
            <button
                class="h-full min-h-0 rounded-2xl border border-mist bg-white text-[clamp(0.8rem,2.2vh,1rem)] font-medium transition active:scale-[0.98] active:bg-sand/30 disabled:opacity-60 dark:border-white/10 dark:bg-white/5"
                type="button"
                aria-label="Clear"
                :disabled="form.processing"
                @click="clearActiveField"
            >
                Clear
            </button>
        </div>

        <button
            class="h-[clamp(2.5rem,7vh,3rem)] shrink-0 rounded-2xl bg-pine text-base font-medium text-foam transition hover:bg-pine/90 disabled:cursor-not-allowed disabled:opacity-50"
            type="button"
            :disabled="!canSignIn"
            @click="submit"
        >
            {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
    </main>
</template>
