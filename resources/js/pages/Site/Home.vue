<script setup>
import SiteLayout from '../../layouts/SiteLayout.vue';

defineOptions({
    layout: SiteLayout,
});

defineProps({
    branches: {
        type: Array,
        required: true,
    },
});

const services = [
    {
        id: 'shop',
        title: 'Shop',
        body: 'Thoughtful food, treats, and everyday supplies chosen for Philippine pets.',
    },
    {
        id: 'grooming',
        title: 'Grooming',
        body: 'Full grooms, baths, and tidy-ups so your dog or cat leaves looking like themselves — only fresher.',
    },
    {
        id: 'hotel',
        title: 'Hotel',
        body: 'Overnight stays with familiar routines, so boarding feels closer to home.',
    },
];
</script>

<template>
    <section class="bg-pine text-foam">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-16 sm:px-6 sm:py-24 lg:py-28">
            <p class="text-sm font-medium tracking-[0.22em] text-sand uppercase">Pluf Pets</p>
            <h1 class="max-w-3xl text-4xl font-medium leading-tight sm:text-6xl">
                A calmer shop, spa, and hotel for dogs and cats.
            </h1>
            <p class="max-w-2xl text-lg text-foam/75">
                We keep the shelves useful, the grooms unhurried, and the boarding kind. Visit a branch, or book a stay when you need us.
            </p>
            <div class="flex flex-wrap gap-3">
                <a class="inline-flex h-11 items-center rounded-xl bg-sand px-5 text-sm font-medium text-pine" href="#visit">Find a branch</a>
                <a class="inline-flex h-11 items-center rounded-xl border border-foam/30 px-5 text-sm font-medium text-foam" href="#grooming">See services</a>
            </div>
        </div>
    </section>

    <section class="mx-auto grid w-full max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-3">
        <article
            v-for="service in services"
            :id="service.id"
            :key="service.id"
            class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-6 dark:border-white/10 dark:bg-white/5"
        >
            <h2 class="text-xl font-medium">{{ service.title }}</h2>
            <p class="text-sm leading-relaxed text-ink/70 dark:text-foam/70">{{ service.body }}</p>
        </article>
    </section>

    <section id="visit" class="border-t border-mist bg-mist/40 dark:border-white/10 dark:bg-white/5">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-16 sm:px-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-2xl font-medium">Visit us</h2>
                <p class="text-sm text-ink/70 dark:text-foam/70">Hours and walk-in details are at each branch. Come by, or call ahead for grooming and hotel stays.</p>
            </div>
            <div v-if="branches.length > 0" class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="branch in branches"
                    :key="branch.id"
                    class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-6 dark:border-white/10 dark:bg-white/5"
                >
                    <h3 class="text-lg font-medium">{{ branch.name }}</h3>
                    <p v-if="branch.address" class="text-sm text-ink/70 dark:text-foam/70">{{ branch.address }}</p>
                    <p v-if="branch.phone" class="text-sm text-ink/70 dark:text-foam/70">{{ branch.phone }}</p>
                </article>
            </div>
            <p v-else class="text-sm text-ink/70 dark:text-foam/70">Branches will appear here as they open.</p>
        </div>
    </section>
</template>
