<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    filings: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    tabs: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const notes = reactive({});

function selectTab(tab) {
    router.get('/backoffice/staff/approvals', { tab }, { preserveState: true, replace: true });
}

function review(filing, status) {
    router.put(`/backoffice/staff/approvals/${filing.id}`, {
        status,
        review_note: notes[filing.id] ?? '',
    }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Filed Requests" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>

        <Teleport defer to="#admin-header-tabs">
        <div class="flex w-max gap-2" role="tablist" aria-label="Filing status">
            <button
                v-for="tab in tabs"
                :id="`approval-tab-${tab.value}`"
                :key="tab.value"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="filters.tab === tab.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="filters.tab === tab.value"
                @click="selectTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>
        </Teleport>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5" role="tabpanel" :aria-labelledby="`approval-tab-${filters.tab}`">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Employee</th>
                        <th class="px-4 py-3 font-medium">Filing</th>
                        <th class="px-4 py-3 font-medium">Reason</th>
                        <th class="px-4 py-3 font-medium">Review</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="filings.data.length === 0">
                        <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="4">No filings with this status.</td>
                    </tr>
                    <tr v-for="filing in filings.data" :key="filing.id" class="border-b border-mist align-top last:border-b-0 dark:border-white/10">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ filing.employee }}</p>
                            <p class="text-xs text-ink/50 dark:text-foam/50">{{ filing.employee_number }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ filing.type_label }}</p>
                            <p>{{ filing.detail }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ filing.reason || '—' }}</p>
                            <p v-if="filing.review_note" class="text-xs text-ink/50 dark:text-foam/50">{{ filing.reviewed_by_name }}: {{ filing.review_note }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="filing.status === 'pending'" class="flex flex-col items-start gap-2">
                                <label class="sr-only" :for="`review-note-${filing.id}`">Note</label>
                                <input
                                    :id="`review-note-${filing.id}`"
                                    v-model="notes[filing.id]"
                                    class="h-10 w-full rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5"
                                    maxlength="1000"
                                    placeholder="Note"
                                    type="text"
                                />
                                <div class="flex gap-2">
                                    <button class="h-10 rounded-xl bg-pine px-3 text-sm font-medium text-foam" type="button" @click="review(filing, 'approved')">
                                        Approve
                                    </button>
                                    <button class="h-10 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15" type="button" @click="review(filing, 'rejected')">
                                        Reject
                                    </button>
                                </div>
                            </div>
                            <p v-else>{{ filing.status_label }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
