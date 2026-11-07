<script setup>
defineProps({
    src: {
        type: String,
        required: true,
    },
    alt: {
        type: String,
        default: 'Instruction photo',
    },
});
</script>

<template>
    <figure class="flex flex-col gap-2">
        <img :src="src" :alt="alt" class="max-h-48 w-full rounded-xl bg-white object-contain" />
        <a :href="src" class="w-fit text-sm font-medium text-navy underline" download>Download</a>
    </figure>
</template>
