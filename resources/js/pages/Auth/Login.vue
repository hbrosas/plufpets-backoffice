<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const appName = computed(() => page.props.appName);
const pinVisible = ref(false);

const form = useForm({
    employee_number: '',
    pin: '',
});

const formError = computed(
    () => form.errors.employee_number || form.errors.pin || '',
);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('pin'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <div class="min-h-screen lg:grid lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-pine text-foam lg:flex lg:flex-col lg:justify-between lg:px-14 lg:py-12">
            <div class="absolute -top-24 -left-16 h-72 w-72 rounded-full bg-sand/20 blur-3xl"></div>
            <div class="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-foam/10 blur-3xl"></div>

            <p class="relative text-sm font-medium tracking-[0.22em] uppercase">{{ appName }}</p>

            <div class="relative flex max-w-md flex-col gap-5">
                <h1 class="text-5xl leading-tight font-medium">Sign in with your employee number.</h1>
                <p class="text-lg text-foam/75">
                    PIN access for people who already have an account. New accounts are not open yet.
                </p>
            </div>

            <p class="relative text-sm text-foam/60">Laravel · Vue · Supabase</p>
        </section>

        <main class="flex min-h-screen items-center justify-center px-6 py-16">
            <div class="w-full max-w-sm">
                <div class="mb-10 flex flex-col gap-3 lg:hidden">
                    <p class="text-sm font-medium tracking-[0.22em] text-pine uppercase dark:text-sand">{{ appName }}</p>
                    <h1 class="text-4xl font-medium">Sign in</h1>
                </div>

                <div class="mb-8 hidden flex-col gap-2 lg:flex">
                    <h2 class="text-3xl font-medium">Welcome back</h2>
                    <p class="text-ink/70 dark:text-foam/70">Enter the employee number and PIN code issued to you.</p>
                </div>

                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div
                        v-if="formError"
                        class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
                        role="alert"
                    >
                        {{ formError }}
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="employee_number">Employee number</label>
                        <input
                            id="employee_number"
                            v-model="form.employee_number"
                            autocomplete="username"
                            class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                            inputmode="numeric"
                            name="employee_number"
                            required
                            type="text"
                            :aria-invalid="Boolean(form.errors.employee_number)"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium" for="pin">PIN code</label>
                        <div class="relative">
                            <input
                                id="pin"
                                v-model="form.pin"
                                autocomplete="current-password"
                                class="h-12 w-full rounded-xl border border-mist bg-white px-4 pr-20 text-base tracking-[0.3em] outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                                inputmode="numeric"
                                maxlength="8"
                                name="pin"
                                required
                                :type="pinVisible ? 'text' : 'password'"
                                :aria-invalid="Boolean(form.errors.pin)"
                            />
                            <button
                                class="absolute top-1/2 right-3 -translate-y-1/2 text-sm font-medium text-pine dark:text-sand"
                                type="button"
                                @click="pinVisible = !pinVisible"
                            >
                                {{ pinVisible ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                    </div>

                    <button
                        class="mt-2 h-12 rounded-xl bg-pine text-base font-medium text-foam transition hover:bg-pine/90 disabled:cursor-wait disabled:opacity-70"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>
        </main>
    </div>
</template>
