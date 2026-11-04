<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <Head title="Shop" />

    <div class="min-h-screen bg-cream text-black">
        <slot />

        <footer class="border-t border-navy/15 px-4 py-8 text-sm text-black">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ new Date().getFullYear() }} {{ page.props.appName }}</p>
                <Link href="/backoffice" class="font-medium text-navy hover:underline">
                    Staff sign in
                </Link>
            </div>
        </footer>
    </div>
</template>
