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

function selectShift(shift) {
    selectedId.value = shift.id;
}
</script>

<template>
    <Head :title="selected ? `${selected.branch} shift` : 'Shifts'" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters action="/backoffice/sales/shifts" :filters="filters" :branches="branches" />
    </Teleport>

    <div class="mx-auto grid w-full max-w-6xl items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <div class="flex max-h-[70vh] flex-col gap-3 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)]">
            <div class="min-h-0 flex-1 overflow-y-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <p v-if="shifts.data.length === 0" class="px-4 py-10 text-sm text-ink/60 dark:text-foam/60">
                    No shifts in this period.
                </p>
                <ul v-else>
                    <li v-for="shift in shifts.data" :key="shift.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                        <button
                            class="flex w-full flex-col gap-1 px-4 py-3 text-left"
                            :class="selected?.id === shift.id ? 'bg-pine/10 dark:bg-sand/10' : ''"
                            type="button"
                            :aria-pressed="selected?.id === shift.id"
                            @click="selectShift(shift)"
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

        <ShiftView v-if="selected" :key="selected.id" :shift="selected" />
        <p v-else class="rounded-2xl border border-mist bg-white px-5 py-10 text-sm text-ink/60 dark:border-white/10 dark:bg-white/5 dark:text-foam/60">
            Select a shift.
        </p>
    </div>
</template>
