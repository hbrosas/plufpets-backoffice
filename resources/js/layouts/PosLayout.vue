<script setup>
import FlashToast from '../components/FlashToast.vue';
</script>

<template>
    <div class="flex h-dvh flex-col overflow-hidden bg-mist/50 dark:bg-ink">
        <FlashToast />
        <header
            class="z-30 flex shrink-0 flex-wrap items-center gap-2 border-b border-mist bg-foam/90 px-3 py-2 backdrop-blur-md sm:gap-3 sm:px-4 sm:py-3 lg:px-6 dark:border-white/10 dark:bg-ink/90"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sand text-sm font-semibold text-pine">PP</span>

            <div id="pos-header-search" class="min-w-[8rem] flex-1 basis-40" />

            <div
                id="admin-header-actions"
                class="flex w-full min-w-0 flex-nowrap items-center justify-end gap-2 overflow-x-auto sm:w-auto sm:flex-none sm:gap-3"
            />
        </header>

        <main class="flex min-h-0 flex-1 flex-col overflow-hidden px-3 py-3 sm:px-4 sm:py-4 lg:px-6 lg:py-5">
            <slot />
        </main>
    </div>
</template>
