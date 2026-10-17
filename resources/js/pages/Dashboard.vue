<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const appName = computed(() => page.props.appName);
const employee = computed(() => page.props.employee);
const form = useForm({});

function logout() {
    form.post('/logout');
}
</script>

<template>
    <Head title="Home" />

    <div class="min-h-screen">
        <header class="border-b border-mist/80 dark:border-white/10">
            <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium tracking-[0.18em] uppercase">{{ appName }}</p>

                <div class="flex items-center justify-between gap-4 sm:justify-end">
                    <p class="text-sm text-ink/70 dark:text-foam/70">
                        {{ employee?.name }}
                        <span class="text-ink/40 dark:text-foam/40">·</span>
                        {{ employee?.employee_number }}
                    </p>

                    <form @submit.prevent="logout">
                        <button
                            class="shrink-0 rounded-full border border-mist px-4 py-2 text-sm font-medium whitespace-nowrap transition hover:border-pine hover:text-pine disabled:opacity-70 dark:border-white/15 dark:hover:border-sand dark:hover:text-sand"
                            type="submit"
                            :disabled="form.processing"
                        >
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto flex min-h-[70vh] w-full max-w-5xl items-center px-6">
            <div class="flex max-w-lg flex-col gap-3">
                <p class="text-sm font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">Workspace</p>
                <h1 class="text-4xl font-medium">Nothing here yet.</h1>
                <p class="text-lg text-ink/70 dark:text-foam/70">
                    You are signed in. This project is still blank — the next screens can be added from here.
                </p>
            </div>
        </main>
    </div>
</template>
