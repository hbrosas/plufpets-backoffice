<script setup>
defineProps({
    count: {
        type: Number,
        required: true,
    },
});
</script>

<template>
    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-sand px-1.5 text-xs font-semibold text-ink tabular-nums">
        {{ count }}
    </span>
</template>
