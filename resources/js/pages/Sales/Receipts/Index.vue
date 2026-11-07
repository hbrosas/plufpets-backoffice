<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SalesFilters from '../../../components/SalesFilters.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import ReceiptView from './Show.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    filters: { type: Object, required: true },
    period_label: { type: String, required: true },
    branches: { type: Array, required: true },
    receipts: { type: Object, required: true },
    receipt: { type: Object, default: null },
});

const selectedId = ref(props.receipt?.id ?? null);

watch(
    () => props.receipt?.id ?? null,
    (id) => {
        if (id !== null) {
            selectedId.value = id;
        }
    },
);

const selected = computed(
    () => props.receipts.data.find((item) => item.id === selectedId.value)
        ?? (props.receipt?.id === selectedId.value ? props.receipt : null),
);

function selectReceipt(item) {
    selectedId.value = item.id;

    const params = new URLSearchParams();

    if (props.filters.preset) {
        params.set('preset', props.filters.preset);
    }

    if (props.filters.from) {
        params.set('from', props.filters.from);
    }

    if (props.filters.to) {
        params.set('to', props.filters.to);
    }

    if (props.filters.branch_id) {
        params.set('branch_id', String(props.filters.branch_id));
    }

    if (props.filters.search) {
        params.set('search', props.filters.search);
    }

    const query = params.toString();

    window.history.replaceState(window.history.state, '', `/backoffice/sales/receipts/${item.id}${query ? `?${query}` : ''}`);
}
</script>

<template>
    <Head :title="selected?.number ?? 'Receipts'" />

    <Teleport defer to="#admin-header-actions">
        <SalesFilters
            action="/backoffice/sales/receipts"
            :filters="filters"
            :branches="branches"
            show-search
            search-placeholder="Receipt or cashier"
        />
    </Teleport>

    <div class="mx-auto grid w-full max-w-6xl items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <div class="flex max-h-[70vh] flex-col gap-3 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)]">
            <div class="min-h-0 flex-1 overflow-y-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <p v-if="receipts.data.length === 0" class="px-4 py-10 text-sm text-ink/60 dark:text-foam/60">
                    No receipts in this period.
                </p>
                <ul v-else>
                    <li v-for="item in receipts.data" :key="item.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                        <button
                            class="flex w-full flex-col gap-1 px-4 py-3 text-left"
                            :class="selected?.id === item.id ? 'bg-pine/10 dark:bg-sand/10' : ''"
                            type="button"
                            :aria-pressed="selected?.id === item.id"
                            @click="selectReceipt(item)"
                        >
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ item.number }}</span>
                                <span
                                    v-if="item.is_refunded"
                                    class="rounded-full bg-danger/10 px-2 py-0.5 text-[11px] font-medium text-danger"
                                >
                                    Refunded
                                </span>
                            </span>
                            <span class="text-xs text-ink/60 dark:text-foam/60">{{ item.sold_at }}</span>
                            <span class="text-sm">{{ item.branch }} · {{ item.total }}</span>
                        </button>
                    </li>
                </ul>
            </div>

            <nav v-if="receipts.last_page > 1" class="flex gap-3" aria-label="Pagination">
                <Link
                    v-if="receipts.prev_page_url"
                    class="text-sm font-medium text-pine dark:text-sand"
                    :href="receipts.prev_page_url"
                    preserve-scroll
                >
                    Previous
                </Link>
                <Link
                    v-if="receipts.next_page_url"
                    class="text-sm font-medium text-pine dark:text-sand"
                    :href="receipts.next_page_url"
                    preserve-scroll
                >
                    Next
                </Link>
            </nav>
        </div>

        <ReceiptView v-if="selected" :key="selected.id" :receipt="selected" />
        <p v-else class="rounded-2xl border border-mist bg-white px-5 py-10 text-sm text-ink/60 dark:border-white/10 dark:bg-white/5 dark:text-foam/60">
            Select a receipt.
        </p>
    </div>
</template>
