<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FlashToast from '../components/FlashToast.vue';
import PosSmsControls from '../components/PosSmsControls.vue';
import { hidePosChrome, hideSendText } from '../posChrome';

const page = usePage();
const form = useForm({});
const menuOpen = ref(false);
const menuTrigger = ref(null);
const menuStyle = ref({});
const canViewDashboard = computed(() => (page.props.employee?.permissions ?? []).includes('dashboard.view'));

function logout() {
    form.post('/backoffice/logout');
}

function closeMenu() {
    menuOpen.value = false;
}

watch(hidePosChrome, (hidden) => {
    if (hidden) {
        closeMenu();
    }
});

function toggleMenu() {
    menuOpen.value = !menuOpen.value;

    if (menuOpen.value && menuTrigger.value) {
        const rect = menuTrigger.value.getBoundingClientRect();
        menuStyle.value = {
            top: `${rect.bottom + 8}px`,
            right: `${Math.max(8, window.innerWidth - rect.right)}px`,
        };
    }
}
</script>

<template>
    <div class="flex h-dvh flex-col overflow-hidden bg-mist/50 dark:bg-ink">
        <FlashToast />
        <header
            class="z-30 flex shrink-0 flex-wrap items-center gap-2 border-b border-mist bg-foam/90 px-3 py-2 backdrop-blur-md sm:gap-3 sm:px-4 sm:py-3 lg:px-6 dark:border-white/10 dark:bg-ink/90"
        >
            <span
                v-show="!hidePosChrome"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sand text-sm font-semibold text-pine"
            >PP</span>

            <div v-show="!hidePosChrome" id="pos-header-search" class="min-w-[8rem] flex-1 basis-40" />

            <div class="flex shrink-0 flex-nowrap items-center gap-2" :class="hidePosChrome ? 'ml-auto' : ''">
                <div v-show="!hidePosChrome && !hideSendText" class="contents">
                    <PosSmsControls />
                </div>

                <div
                    id="admin-header-actions"
                    class="flex shrink-0 flex-nowrap items-center justify-end gap-2"
                />

                <form v-show="!hidePosChrome" class="shrink-0" @submit.prevent="logout">
                    <button
                        class="h-10 rounded-xl border border-mist px-3 text-sm font-medium whitespace-nowrap dark:border-white/15 disabled:opacity-70"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Locking…' : 'Lock' }}
                    </button>
                </form>
            </div>

            <div v-show="!hidePosChrome" ref="menuTrigger" class="relative shrink-0">
                <button
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-mist dark:border-white/15"
                    type="button"
                    :aria-expanded="menuOpen"
                    aria-controls="pos-header-menu"
                    @click="toggleMenu"
                >
                    <span class="sr-only">Open menu</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path
                            fill-rule="evenodd"
                            d="M3 5.75A.75.75 0 0 1 3.75 5h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 5.75Zm0 4.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 10.25Zm0 4.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>
            </div>
        </header>

        <Teleport to="body">
            <div v-show="menuOpen" class="fixed inset-0 z-40" @click="closeMenu" />
            <div
                v-show="menuOpen"
                id="pos-header-menu"
                class="fixed z-50 flex w-56 flex-col overflow-hidden rounded-2xl border border-mist bg-foam py-1 shadow-lg dark:border-white/10 dark:bg-ink"
                :style="menuStyle"
                @click="closeMenu"
            >
                <div id="pos-header-menu-items" class="flex flex-col" />
                <Link
                    v-if="canViewDashboard"
                    class="px-4 py-2.5 text-left text-sm font-medium hover:bg-mist/70 dark:hover:bg-white/10"
                    href="/backoffice/dashboard"
                >
                    Dashboard
                </Link>
            </div>
        </Teleport>

        <main class="flex min-h-0 flex-1 flex-col overflow-hidden px-3 py-3 sm:px-4 sm:py-4 lg:px-6 lg:py-5">
            <slot />
        </main>
    </div>
</template>
