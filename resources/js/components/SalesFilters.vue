<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    branches: {
        type: Array,
        required: true,
    },
    showSearch: {
        type: Boolean,
        default: false,
    },
    searchPlaceholder: {
        type: String,
        default: 'Search',
    },
});

const form = useForm({
    preset: props.filters.preset,
    from: props.filters.from,
    to: props.filters.to,
    branch_id: props.filters.branch_id ?? '',
    search: props.filters.search ?? '',
});

watch(
    () => props.filters,
    (value) => {
        form.preset = value.preset;
        form.from = value.from;
        form.to = value.to;
        form.branch_id = value.branch_id ?? '';
        form.search = value.search ?? '';
    },
);

function apply() {
    form.get(props.action, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <form class="flex flex-wrap items-center justify-end gap-2" @submit.prevent="apply">
        <label class="sr-only" for="sales-preset">Period</label>
        <select
            id="sales-preset"
            v-model="form.preset"
            class="h-10 rounded-xl border border-mist bg-white pr-10 pl-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
        >
            <option value="today">Today</option>
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="custom">Custom</option>
        </select>
        <label v-if="form.preset === 'custom'" class="sr-only" for="sales-from">From</label>
        <input
            v-if="form.preset === 'custom'"
            id="sales-from"
            v-model="form.from"
            class="h-10 rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
            type="date"
        />
        <label v-if="form.preset === 'custom'" class="sr-only" for="sales-to">To</label>
        <input
            v-if="form.preset === 'custom'"
            id="sales-to"
            v-model="form.to"
            class="h-10 rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
            type="date"
        />
        <label class="sr-only" for="sales-branch">Branch</label>
        <select
            id="sales-branch"
            v-model="form.branch_id"
            class="h-10 max-w-[10rem] rounded-xl border border-mist bg-white pr-10 pl-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
        >
            <option value="">All branches</option>
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                {{ branch.name }}
            </option>
        </select>
        <label v-if="showSearch" class="sr-only" for="sales-search">Search</label>
        <input
            v-if="showSearch"
            id="sales-search"
            v-model="form.search"
            class="h-10 w-44 rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
            :placeholder="searchPlaceholder"
            type="search"
        />
        <button
            class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam"
            type="submit"
        >
            Apply
        </button>
    </form>
</template>
