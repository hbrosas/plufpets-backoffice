<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branches: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const logoutForm = useForm({});
const canViewDashboard = computed(() => (page.props.employee?.permissions ?? []).includes('dashboard.view'));

function logout() {
    logoutForm.post('/backoffice/logout');
}
</script>

<template>
    <Head title="Choose a branch" />

    <main class="mx-auto flex h-dvh w-full max-w-lg flex-col gap-4 overflow-hidden px-5 py-4">
        <div class="flex shrink-0 items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-medium tracking-[0.18em] text-pine uppercase dark:text-sand">POS</p>
                <h1 class="text-2xl font-medium">Choose a branch</h1>
            </div>
            <button
                class="h-10 shrink-0 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15 disabled:opacity-70"
                type="button"
                :disabled="logoutForm.processing"
                @click="logout"
            >
                {{ logoutForm.processing ? 'Locking…' : 'Lock' }}
            </button>
        </div>

        <p v-if="props.branches.length === 0" class="text-sm text-ink/70 dark:text-foam/70">No active branches yet.</p>

        <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto" role="group" aria-label="Branches">
            <Link
                v-for="branch in props.branches"
                :key="branch.id"
                class="flex shrink-0 flex-col gap-2 rounded-2xl border border-mist bg-white px-4 py-4 text-left transition hover:border-pine dark:border-white/10 dark:bg-white/5 dark:hover:border-sand"
                :href="branch.href"
            >
                <span class="flex items-center justify-between gap-3">
                    <span class="text-lg font-medium">{{ branch.name }}</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase"
                        :class="
                            branch.is_open
                                ? 'bg-pine/15 text-pine dark:bg-sand/20 dark:text-sand'
                                : 'bg-mist text-ink/60 dark:bg-white/10 dark:text-foam/60'
                        "
                    >
                        {{ branch.status }}
                    </span>
                </span>
                <span class="text-xs text-ink/50 dark:text-foam/50">
                    <template v-if="branch.date || branch.user">
                        {{ [branch.date, branch.user].filter(Boolean).join(' · ') }}
                    </template>
                    <template v-else>No shift yet</template>
                </span>
            </Link>
        </div>

        <Link
            v-if="canViewDashboard"
            class="flex h-12 shrink-0 items-center justify-center rounded-2xl border border-mist text-sm font-medium dark:border-white/15"
            href="/backoffice/dashboard"
        >
            Dashboard
        </Link>
    </main>
</template>
