<script setup>
defineProps({
    id: {
        type: String,
        required: true,
    },
    accept: {
        type: String,
        default: 'image/jpeg,image/png,image/webp',
    },
    buttonLabel: {
        type: String,
        default: 'Choose file',
    },
    fileName: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: 'No file chosen',
    },
});

const emit = defineEmits(['change']);
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <label
            :for="id"
            class="inline-flex h-11 cursor-pointer items-center justify-center rounded-xl bg-pine px-4 text-sm font-medium text-foam transition hover:bg-pine/90"
        >
            {{ buttonLabel }}
        </label>
        <input :id="id" :accept="accept" class="sr-only" type="file" @change="emit('change', $event)" />
        <p class="min-w-0 flex-1 text-sm break-all text-ink/70 dark:text-foam/70">
            {{ fileName || hint }}
        </p>
    </div>
</template>
