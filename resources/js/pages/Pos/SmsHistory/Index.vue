<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    messages: { type: Object, required: true },
});
</script>

<template>
    <Head title="SMS History" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">SMS History</h2>
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[52rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">When</th>
                            <th class="px-4 py-3 font-medium">Branch</th>
                            <th class="px-4 py-3 font-medium">Number</th>
                            <th class="px-4 py-3 font-medium">Sender</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="messages.data.length === 0">
                            <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="6">No texts sent yet.</td>
                        </tr>
                        <tr
                            v-for="row in messages.data"
                            :key="row.id"
                            class="border-b border-mist align-top last:border-b-0 dark:border-white/10"
                        >
                            <td class="px-4 py-3 whitespace-nowrap">{{ row.sent_at }}</td>
                            <td class="px-4 py-3">{{ row.branch }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.number }}</td>
                            <td class="px-4 py-3">{{ row.sender }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="
                                        ['failed', 'refunded'].includes(String(row.status || '').toLowerCase())
                                            ? 'bg-danger/10 text-danger'
                                            : 'bg-pine/10 text-pine dark:bg-pine/20 dark:text-sand'
                                    "
                                >
                                    {{ row.status || 'Sent' }}
                                </span>
                            </td>
                            <td class="max-w-sm px-4 py-3">
                                <p class="whitespace-pre-wrap text-ink/70 dark:text-foam/70">{{ row.body }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="messages.last_page > 1" class="flex gap-3" aria-label="Pagination">
                <Link
                    v-if="messages.prev_page_url"
                    class="text-sm font-medium text-pine dark:text-sand"
                    :href="messages.prev_page_url"
                    :preserve-state="true"
                >
                    Previous
                </Link>
                <Link
                    v-if="messages.next_page_url"
                    class="text-sm font-medium text-pine dark:text-sand"
                    :href="messages.next_page_url"
                    :preserve-state="true"
                >
                    Next
                </Link>
            </nav>
        </section>
    </div>
</template>
