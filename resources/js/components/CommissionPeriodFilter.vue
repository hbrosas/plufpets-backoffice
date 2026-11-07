<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    preset: props.filters.preset,
    from: props.filters.from,
    to: props.filters.to,
});

const error = computed(() => form.errors.preset || form.errors.from || form.errors.to || '');

watch(
    () => props.filters,
    (value) => {
        form.preset = value.preset;
        form.from = value.from;
        form.to = value.to;
    },
);

function apply() {
    form
        .transform((data) => ({
            preset: data.preset,
            from: data.preset === 'custom' ? data.from : null,
            to: data.preset === 'custom' ? data.to : null,
        }))
        .get(props.action, {
            preserveState: true,
            replace: true,
        });
}
</script>

<template>
    <form class="flex w-full max-w-full flex-wrap items-center gap-2 lg:w-auto lg:justify-end" @submit.prevent="apply">
        <label class="sr-only" for="commission-preset">Period</label>
        <select
            id="commission-preset"
            v-model="form.preset"
            class="h-10 max-w-full rounded-xl border border-mist bg-white pr-10 pl-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
        >
            <option value="week">This week</option>
            <option value="two_weeks">Last 2 weeks</option>
            <option value="month">This month</option>
            <option value="custom">Custom</option>
        </select>
        <label v-if="form.preset === 'custom'" class="sr-only" for="commission-from">From</label>
        <input
            v-if="form.preset === 'custom'"
            id="commission-from"
            v-model="form.from"
            class="h-10 max-w-full rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
            type="date"
        />
        <label v-if="form.preset === 'custom'" class="sr-only" for="commission-to">To</label>
        <input
            v-if="form.preset === 'custom'"
            id="commission-to"
            v-model="form.to"
            class="h-10 max-w-full rounded-xl border border-mist bg-white px-3 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-ink"
            type="date"
        />
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="submit">
            Apply
        </button>
        <p v-if="error" class="basis-full text-right text-sm text-danger">{{ error }}</p>
    </form>
</template>
