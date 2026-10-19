<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    activeStaffCount: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const employee = computed(() => page.props.employee);

const stats = computed(() => [
    { label: 'Pets listed', value: '0', hint: 'Catalog is empty' },
    { label: 'Open orders', value: '0', hint: 'No orders in progress' },
    { label: 'Customers', value: '0', hint: 'No customer accounts yet' },
    { label: 'Active staff', value: String(props.activeStaffCount), hint: employee.value?.name ?? 'Signed in' },
]);
</script>

<template>
    <Head title="Overview" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Summary">
            <article
                v-for="stat in stats"
                :key="stat.label"
                class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
            >
                <p class="text-sm text-ink/60 dark:text-foam/60">{{ stat.label }}</p>
                <p class="text-3xl font-medium">{{ stat.value }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">{{ stat.hint }}</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 xl:col-span-2 dark:border-white/10 dark:bg-white/5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-medium">Recent orders</h2>
                    <span class="rounded-full bg-mist px-3 py-1 text-xs font-medium text-ink/60 dark:bg-white/10 dark:text-foam/60">Live</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="py-3 pr-4 font-medium">Order</th>
                                <th class="py-3 pr-4 font-medium">Customer</th>
                                <th class="py-3 pr-4 font-medium">Status</th>
                                <th class="py-3 font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="py-10 text-ink/60 dark:text-foam/60" colspan="4">
                                    No orders yet. New pet shop orders will show up here.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-lg font-medium">Queue</h2>
                <ul class="flex flex-col gap-3">
                    <li class="flex items-start justify-between gap-3 rounded-xl bg-mist/70 px-4 py-3 dark:bg-white/5">
                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-medium">Pending listings</p>
                            <p class="text-xs text-ink/55 dark:text-foam/50">Pets waiting for review</p>
                        </div>
                        <span class="text-sm font-medium">0</span>
                    </li>
                    <li class="flex items-start justify-between gap-3 rounded-xl bg-mist/70 px-4 py-3 dark:bg-white/5">
                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-medium">Support tickets</p>
                            <p class="text-xs text-ink/55 dark:text-foam/50">Customer questions</p>
                        </div>
                        <span class="text-sm font-medium">0</span>
                    </li>
                    <li class="flex items-start justify-between gap-3 rounded-xl bg-mist/70 px-4 py-3 dark:bg-white/5">
                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-medium">Low stock</p>
                            <p class="text-xs text-ink/55 dark:text-foam/50">Items to restock</p>
                        </div>
                        <span class="text-sm font-medium">0</span>
                    </li>
                </ul>
            </article>
        </section>
    </div>
</template>
