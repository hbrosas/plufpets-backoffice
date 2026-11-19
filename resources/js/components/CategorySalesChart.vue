<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
});

const open = ref([]);
const width = 640;
const height = 140;
const padX = 8;
const padTop = 8;
const padBottom = 8;

const charts = computed(() => props.categories.map((category) => {
    const hours = category.hours ?? [];
    const maxAmount = Math.max(...hours.map((hour) => Number(hour.amount) || 0), 0);
    const maxTransactions = Math.max(...hours.map((hour) => Number(hour.transactions) || 0), 0);
    const plotWidth = width - padX * 2;
    const plotHeight = height - padTop - padBottom;
    const slot = plotWidth / Math.max(hours.length, 1);
    const baseline = height - padBottom;
    const points = hours.map((hour, index) => {
        const center = padX + slot * index + slot / 2;
        const barWidth = Math.min(10, slot * 0.55);
        const amountRatio = maxAmount === 0 ? 0 : Number(hour.amount) / maxAmount;
        const transactionRatio = maxTransactions === 0 ? 0 : Number(hour.transactions) / maxTransactions;
        const barHeight = amountRatio * plotHeight;

        return {
            ...hour,
            center,
            barWidth,
            barX: center - barWidth / 2,
            barY: baseline - barHeight,
            barHeight,
            lineY: baseline - transactionRatio * plotHeight,
        };
    });

    return {
        ...category,
        baseline,
        points,
        line: points.map((point) => `${point.center},${point.lineY}`).join(' '),
    };
}));

function isOpen(name) {
    return open.value.includes(name);
}

function toggle(name) {
    open.value = isOpen(name)
        ? open.value.filter((item) => item !== name)
        : [...open.value, name];
}

function transactionLabel(count) {
    return `${count} ${count === 1 ? 'transaction' : 'transactions'}`;
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-medium">Categories</h3>
            <div class="flex items-center gap-4 text-xs text-ink/55 dark:text-foam/50">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-pine" /> Sales</span>
                <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 bg-ink dark:bg-foam" /> Transactions</span>
            </div>
        </div>
        <p v-if="categories.length === 0" class="rounded-2xl border border-mist bg-white px-5 py-8 text-sm text-ink/55 dark:border-white/10 dark:bg-white/5 dark:text-foam/50">
            No category sales today.
        </p>
        <div v-else class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="category in charts"
                :key="category.name"
                class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
            >
                <button
                    class="flex items-start justify-between gap-3 text-left"
                    type="button"
                    :aria-expanded="isOpen(category.name)"
                    @click="toggle(category.name)"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-lg font-medium">{{ category.name }}</span>
                        <span class="block text-sm text-ink/55 dark:text-foam/50">{{ transactionLabel(category.transactions) }} · {{ category.total }}</span>
                    </span>
                    <span class="shrink-0 text-sm font-medium text-pine dark:text-sand">{{ isOpen(category.name) ? 'Hide items' : 'Items' }}</span>
                </button>
                <svg class="h-28 w-full text-ink dark:text-foam" :viewBox="`0 0 ${width} ${height}`" role="img" :aria-label="`${category.name} hourly sales and transactions`">
                    <line class="stroke-mist dark:stroke-white/15" :x1="padX" :x2="width - padX" :y1="category.baseline" :y2="category.baseline" />
                    <rect
                        v-for="point in category.points"
                        :key="`${category.name}-${point.label}-bar`"
                        class="fill-pine"
                        :x="point.barX"
                        :y="point.barY"
                        :width="point.barWidth"
                        :height="point.barHeight"
                    >
                        <title>{{ point.label }} · {{ point.transactions }} · {{ point.amount }}</title>
                    </rect>
                    <polyline fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :points="category.line" />
                    <circle v-for="point in category.points" :key="`${category.name}-${point.label}-dot`" :cx="point.center" :cy="point.lineY" r="2.5" fill="currentColor">
                        <title>{{ point.label }} · {{ transactionLabel(point.transactions) }}</title>
                    </circle>
                </svg>
                <div class="flex justify-between gap-2 text-[10px] text-ink/50 dark:text-foam/50">
                    <span>{{ category.hours[0]?.label }}</span>
                    <span>{{ category.hours[12]?.label }}</span>
                    <span>{{ category.hours[23]?.label }}</span>
                </div>
                <div v-if="isOpen(category.name)" class="flex flex-col gap-2 border-t border-mist pt-3 dark:border-white/10">
                    <div class="grid grid-cols-[minmax(0,1fr)_7rem_7rem] gap-3 text-xs text-ink/55 dark:text-foam/50">
                        <span>Item</span>
                        <span>Transactions</span>
                        <span>Sales</span>
                    </div>
                    <div
                        v-for="item in category.items"
                        :key="item.name"
                        class="grid grid-cols-[minmax(0,1fr)_7rem_7rem] gap-3 text-sm"
                    >
                        <span class="truncate font-medium">{{ item.name }}</span>
                        <span>{{ item.transactions }}</span>
                        <span>{{ item.total }}</span>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
