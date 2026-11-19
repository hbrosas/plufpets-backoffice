<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SalesFilters from '../../../components/SalesFilters.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import ShiftView from './Show.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    filters: { type: Object, required: true },
    period_label: { type: String, required: true },
    branches: { type: Array, required: true },
    shifts: { type: Object, required: true },
});

const selectedId = ref(null);
const selected = computed(() => props.shifts.data.find((item) => item.id === selectedId.value) ?? null);

function openShift(shift) {
    if (!shift.z_report) {
        return;
    }

    selectedId.value = shift.id;
}
</script>

<template>
    <Head :title="selected ? `${selected.branch} Z-Report` : 'Shifts'" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters action="/backoffice/sales/shifts" :filters="filters" :branches="branches" />
    </Teleport>

    <div class="flex h-full min-h-0 w-full max-w-[20rem] flex-1 flex-col gap-3">
        <div class="h-full min-h-0 flex-1 overflow-y-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <p v-if="shifts.data.length === 0" class="px-4 py-10 text-sm text-ink/60 dark:text-foam/60">
                No shifts in this period.
            </p>
            <ul v-else>
                <li v-for="shift in shifts.data" :key="shift.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                    <button
                        class="flex w-full flex-col gap-1 px-4 py-3 text-left"
                        :class="[
                            selected?.id === shift.id ? 'bg-pine/10 dark:bg-sand/10' : '',
                            shift.z_report ? '' : 'cursor-default',
                        ]"
                        type="button"
                        :disabled="!shift.z_report"
                        :aria-pressed="selected?.id === shift.id"
                        :aria-label="shift.z_report ? `View Z-Report for ${shift.branch}` : `${shift.branch} is still open`"
                        @click="openShift(shift)"
                    >
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ shift.branch }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    shift.status === 'Open'
                                        ? 'bg-pine/10 text-pine dark:bg-sand/15 dark:text-sand'
                                        : 'bg-mist text-ink/50 dark:bg-white/10 dark:text-foam/50'
                                "
                            >
                                {{ shift.status }}
                            </span>
                        </span>
                        <span class="text-xs text-ink/60 dark:text-foam/60">{{ shift.opened_at }}</span>
                        <span class="text-sm">{{ shift.receipts }} receipts · {{ shift.sales_total }}</span>
                    </button>
                </li>
            </ul>
        </div>

        <nav v-if="shifts.last_page > 1" class="flex gap-3" aria-label="Pagination">
            <Link
                v-if="shifts.prev_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="shifts.prev_page_url"
                preserve-scroll
            >
                Previous
            </Link>
            <Link
                v-if="shifts.next_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="shifts.next_page_url"
                preserve-scroll
            >
                Next
            </Link>
        </nav>
    </div>

    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4" @click.self="selectedId = null">
        <div
            class="flex max-h-[90vh] w-full max-w-2xl flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink"
            role="dialog"
            aria-modal="true"
            aria-labelledby="shift-z-report-title"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <p class="text-sm text-ink/60 dark:text-foam/60">Z-Report</p>
                    <h2 id="shift-z-report-title" class="text-lg font-medium">{{ selected.branch }}</h2>
                </div>
                <button class="text-sm font-medium" type="button" @click="selectedId = null">Close</button>
            </div>
            <ShiftView :key="selected.id" :shift="selected" />
        </div>
    </div>
</template>
