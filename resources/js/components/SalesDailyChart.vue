<script setup>
import { computed } from 'vue';

const props = defineProps({
    daily: {
        type: Array,
        required: true,
    },
});

const width = 640;
const height = 220;
const padX = 16;
const padY = 24;

const maxAmount = computed(() => Math.max(...props.daily.map((row) => Number(row.total_amount) || 0), 0));

const points = computed(() => {
    if (props.daily.length === 0) {
        return [];
    }

    const span = Math.max(props.daily.length - 1, 1);

    return props.daily.map((row, index) => {
        const x = padX + (index / span) * (width - padX * 2);
        const ratio = maxAmount.value === 0 ? 0 : Number(row.total_amount) / maxAmount.value;
        const y = height - padY - ratio * (height - padY * 2);

        return { ...row, x, y };
    });
});

const line = computed(() => points.value.map((point) => `${point.x},${point.y}`).join(' '));
</script>

<template>
    <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
        <h2 class="text-lg font-medium">Daily sales</h2>
        <p v-if="daily.length === 0" class="py-10 text-sm text-ink/60 dark:text-foam/60">No sales in this period.</p>
        <div v-else class="flex flex-col gap-3">
            <svg class="h-56 w-full text-pine dark:text-sand" :viewBox="`0 0 ${width} ${height}`" role="img" aria-label="Daily sales line chart">
                <line
                    class="stroke-mist dark:stroke-white/15"
                    :x1="padX"
                    :x2="width - padX"
                    :y1="height - padY"
                    :y2="height - padY"
                />
                <polyline
                    fill="none"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="3"
                    :points="line"
                />
                <circle
                    v-for="point in points"
                    :key="point.day"
                    :cx="point.x"
                    :cy="point.y"
                    r="4"
                    fill="currentColor"
                >
                    <title>{{ point.day_label }} · {{ point.total }}</title>
                </circle>
            </svg>
            <div class="flex justify-between gap-2 text-xs text-ink/50 dark:text-foam/50">
                <span>{{ daily[0]?.day_label }}</span>
                <span>{{ daily[daily.length - 1]?.day_label }}</span>
            </div>
        </div>
    </article>
</template>
