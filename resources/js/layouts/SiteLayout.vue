<script setup>
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <Head title="Shop" />

    <div class="min-h-screen bg-cream text-black">
        <slot />

        <footer class="border-t border-navy/15 px-4 py-8 text-sm text-black">
            <p class="mx-auto max-w-6xl">© {{ new Date().getFullYear() }} {{ page.props.appName }}</p>
        </footer>
    </div>
</template>
