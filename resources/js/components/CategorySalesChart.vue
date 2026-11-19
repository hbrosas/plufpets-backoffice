<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    grain: {
        type: String,
        default: 'today',
    },
    periodLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['change']);

const grains = [
    { value: 'today', label: 'Today' },
    { value: 'week', label: 'This Week' },
    { value: 'two_weeks', label: 'Last 2 Weeks' },
    { value: 'month', label: 'This Month' },
    { value: 'quarter', label: 'This Quarter' },
    { value: 'year', label: 'This Year' },
];

const open = ref([]);
const hover = ref(null);
const pinned = ref(null);
const width = 640;
const height = 176;
const padX = 12;
const padTop = 16;
const padBottom = 28;

const charts = computed(() => props.categories.map((category) => {
    const series = category.points ?? [];
    const maxAmount = Math.max(...series.map((point) => Number(point.amount) || 0), 0);
    const maxTransactions = Math.max(...series.map((point) => Number(point.transactions) || 0), 0);
    const plotWidth = width - padX * 2;
    const plotHeight = height - padTop - padBottom;
    const slot = plotWidth / Math.max(series.length, 1);
    const baseline = height - padBottom;
    const points = series.map((point, index) => {
        const center = padX + slot * index + slot / 2;
        const barWidth = Math.min(36, slot * 0.62);
        const amountRatio = maxAmount === 0 ? 0 : Number(point.amount) / maxAmount;
        const transactionRatio = maxTransactions === 0 ? 0 : Number(point.transactions) / maxTransactions;
        const barHeight = amountRatio * plotHeight;

        return {
            ...point,
            index,
            center,
            slot,
            hitX: padX + slot * index,
            barWidth,
            barX: center - barWidth / 2,
            barY: baseline - barHeight,
            barHeight,
            barRadius: Math.min(5, barWidth / 2, barHeight / 2),
            lineY: baseline - transactionRatio * plotHeight,
        };
    });

    return {
        ...category,
        baseline,
        plotHeight,
        points,
        line: points.map((point) => `${point.center},${point.lineY}`).join(' '),
        grid: [0, 0.5, 1].map((ratio) => baseline - ratio * plotHeight),
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

function selection(name) {
    if (hover.value?.name === name) {
        return hover.value;
    }

    if (pinned.value?.name === name) {
        return pinned.value;
    }

    return null;
}

function selectedPoint(category) {
    const current = selection(category.name);

    if (!current) {
        return null;
    }

    return category.points[current.index] ?? null;
}

function enter(name, index) {
    hover.value = { name, index };
}

function leave(name) {
    if (hover.value?.name === name) {
        hover.value = null;
    }
}

function pin(name, index) {
    if (pinned.value?.name === name && pinned.value.index === index) {
        pinned.value = null;

        return;
    }

    pinned.value = { name, index };
}

function isSelected(name, index) {
    const current = selection(name);

    return current?.index === index;
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h3 class="text-lg font-medium">Categories</h3>
                <p class="text-sm text-ink/55 dark:text-foam/50">{{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Category trend">
                    <button
                        v-for="item in grains"
                        :key="item.value"
                        class="h-9 rounded-xl px-3 text-sm font-medium"
                        :class="grain === item.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                        type="button"
                        role="tab"
                        :aria-selected="grain === item.value"
                        @click="emit('change', item.value)"
                    >
                        {{ item.label }}
                    </button>
                </div>
                <div class="flex items-center gap-4 text-xs text-ink/55 dark:text-foam/50">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-pine" /> Sales</span>
                    <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-sand" /> Transactions</span>
                </div>
            </div>
        </div>
        <p v-if="categories.length === 0" class="rounded-2xl border border-mist bg-white px-5 py-8 text-sm text-ink/55 dark:border-white/10 dark:bg-white/5 dark:text-foam/50">
            No category sales in this period.
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
                <div class="flex h-5 items-center text-xs text-ink/70 dark:text-foam/70" aria-live="polite">
                    <p v-if="selectedPoint(category)">
                        <span class="font-medium text-ink dark:text-foam">{{ selectedPoint(category).label }}</span>
                        <span> · {{ selectedPoint(category).total }} · {{ transactionLabel(selectedPoint(category).transactions) }}</span>
                    </p>
                </div>
                <svg
                    class="h-44 w-full"
                    :viewBox="`0 0 ${width} ${height}`"
                    role="img"
                    :aria-label="`${category.name} sales and transactions`"
                    @pointerleave="leave(category.name)"
                >
                    <line
                        v-for="(y, gridIndex) in category.grid"
                        :key="`${category.name}-grid-${gridIndex}`"
                        class="stroke-mist dark:stroke-white/15"
                        stroke-dasharray="3 6"
                        :x1="padX"
                        :x2="width - padX"
                        :y1="y"
                        :y2="y"
                    />
                    <template v-for="point in category.points" :key="`${category.name}-${point.index}`">
                        <rect
                            v-if="isSelected(category.name, point.index)"
                            class="fill-sand/20"
                            :x="point.hitX"
                            :y="padTop"
                            :width="point.slot"
                            :height="category.plotHeight"
                        />
                        <rect
                            v-if="point.barHeight > 0"
                            class="fill-pine"
                            :x="point.barX"
                            :y="point.barY"
                            :width="point.barWidth"
                            :height="point.barHeight"
                            :rx="point.barRadius"
                        />
                    </template>
                    <polyline class="fill-none stroke-sand" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :points="category.line" />
                    <circle
                        v-for="point in category.points"
                        v-show="point.transactions > 0 || isSelected(category.name, point.index)"
                        :key="`${category.name}-${point.index}-dot`"
                        class="fill-sand stroke-white dark:stroke-ink"
                        :cx="point.center"
                        :cy="point.lineY"
                        :r="isSelected(category.name, point.index) ? 5 : 3.5"
                        stroke-width="2"
                    />
                    <text
                        v-for="point in category.points.filter((item) => item.axis)"
                        :key="`${category.name}-${point.index}-axis`"
                        class="fill-ink/50 dark:fill-foam/50"
                        :x="point.center"
                        :y="height - 8"
                        text-anchor="middle"
                        font-size="12"
                    >
                        {{ point.axis }}
                    </text>
                    <rect
                        v-for="point in category.points"
                        :key="`${category.name}-${point.index}-hit`"
                        class="cursor-pointer"
                        fill="transparent"
                        :x="point.hitX"
                        :y="padTop"
                        :width="point.slot"
                        :height="category.plotHeight"
                        @pointerenter="enter(category.name, point.index)"
                        @click="pin(category.name, point.index)"
                    />
                </svg>
                <div v-if="isOpen(category.name)" class="flex flex-col gap-2 border-t border-mist pt-3 dark:border-white/10">
                    <div class="grid grid-cols-[minmax(0,1fr)_7rem_7rem] gap-3 text-xs text-ink/55 dark:text-foam/50">
                        <span>Item</span>
                        <span>Transactions</span>
                        <span>Sales</span>
                    </div>
                    <div v-for="item in category.items" :key="item.name" class="flex flex-col gap-1">
                        <div class="grid grid-cols-[minmax(0,1fr)_7rem_7rem] gap-3 text-sm">
                            <span class="truncate font-medium">{{ item.name }}</span>
                            <span>{{ item.transactions }}</span>
                            <span>{{ item.total }}</span>
                        </div>
                        <div
                            v-for="branch in item.branches"
                            :key="`${item.name}-${branch.name}`"
                            class="grid grid-cols-[minmax(0,1fr)_7rem_7rem] gap-3 text-xs text-ink/55 dark:text-foam/50"
                        >
                            <span class="truncate">{{ branch.name }}</span>
                            <span>{{ branch.transactions }}</span>
                            <span>{{ branch.total }}</span>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
