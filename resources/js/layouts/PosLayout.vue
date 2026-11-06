<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FlashToast from '../components/FlashToast.vue';
import PosSmsControls from '../components/PosSmsControls.vue';
import { hidePosChrome, hideSendText } from '../posChrome';

const page = usePage();
const form = useForm({});
const logoMissing = ref(false);
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
    <div class="admin-shell flex h-dvh flex-col overflow-hidden bg-cream text-black">
        <FlashToast />
        <header
            class="z-30 flex shrink-0 flex-wrap items-center gap-2 border-b border-mist bg-foam/90 px-3 py-2 backdrop-blur-md sm:gap-3 sm:px-4 sm:py-3 lg:px-6 dark:border-white/10 dark:bg-ink/90"
        >
            <img
                v-if="page.props.businessLogoUrl && !logoMissing"
                v-show="!hidePosChrome"
                :src="page.props.businessLogoUrl"
                alt=""
                class="h-10 w-10 shrink-0 rounded-xl bg-white object-contain"
                @error="logoMissing = true"
            />
            <span
                v-else
                v-show="!hidePosChrome"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ember text-sm font-semibold text-black"
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
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-mist dark:border-white/15 disabled:opacity-70"
                        type="submit"
                        :aria-label="form.processing ? 'Locking' : 'Lock'"
                        :disabled="form.processing"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <rect x="4" y="9" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                            <path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
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
            <div class="admin-shell contents">
            <div v-show="menuOpen" class="fixed inset-0 z-40" @click="closeMenu" />
            <div
                v-show="menuOpen"
                id="pos-header-menu"
                class="fixed z-50 flex w-56 flex-col overflow-hidden rounded-2xl border border-mist bg-foam py-1 text-ink shadow-lg dark:border-white/10 dark:bg-ink"
                :style="menuStyle"
                @click="closeMenu"
            >
                <div id="pos-header-menu-items" class="flex flex-col" />
                <Link
                    v-if="canViewDashboard"
                    class="flex items-center gap-2 px-4 py-2.5 text-left text-sm font-medium hover:bg-mist/70 dark:hover:bg-white/10"
                    href="/backoffice/dashboard"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <rect x="3" y="3" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                        <rect x="11" y="3" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                        <rect x="3" y="11" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                        <rect x="11" y="11" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                    Dashboard
                </Link>
            </div>
            </div>
        </Teleport>

        <main class="flex min-h-0 flex-1 flex-col overflow-hidden px-3 py-3 sm:px-4 sm:py-4 lg:px-6 lg:py-5">
            <slot />
        </main>
    </div>
</template>
