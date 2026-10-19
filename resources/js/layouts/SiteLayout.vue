<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const appName = computed(() => page.props.appName ?? 'Pluf Pets');

const links = [
    { href: '#shop', label: 'Shop' },
    { href: '#grooming', label: 'Grooming' },
    { href: '#hotel', label: 'Hotel' },
    { href: '#visit', label: 'Visit' },
];
</script>

<template>
    <Head title="Home" />

    <div class="min-h-screen bg-foam text-ink dark:bg-ink dark:text-foam">
        <header class="sticky top-0 z-30 border-b border-mist/80 bg-foam/90 backdrop-blur-md dark:border-white/10 dark:bg-ink/90">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a class="flex items-center gap-3" href="#top">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sand text-sm font-semibold text-pine">PP</span>
                    <span class="text-sm font-medium">{{ appName }}</span>
                </a>
                <nav class="hidden items-center gap-6 text-sm font-medium sm:flex" aria-label="Site">
                    <a v-for="link in links" :key="link.href" class="text-ink/70 hover:text-pine dark:text-foam/70 dark:hover:text-sand" :href="link.href">
                        {{ link.label }}
                    </a>
                </nav>
                <Link
                    class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam"
                    href="/backoffice"
                >
                    Staff
                </Link>
            </div>
        </header>

        <main id="top">
            <slot />
        </main>

        <footer class="border-t border-mist bg-pine text-foam dark:border-white/10">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-sm">{{ appName }} · Care for dogs and cats, every day.</p>
                <Link class="text-sm font-medium text-sand" href="/backoffice">Staff sign in</Link>
            </div>
        </footer>
    </div>
</template>
