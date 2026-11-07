<script setup>
defineProps({
    id: {
        type: String,
        required: true,
    },
    existing: {
        type: Array,
        default: () => [],
    },
    pending: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['add', 'removeExisting', 'removePending']);

function choose(event) {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';

    if (files.length > 0) {
        emit('add', files);
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="existing.length > 0 || pending.length > 0" class="flex flex-wrap gap-2">
            <div v-for="image in existing" :key="image.path" class="relative">
                <img :src="image.url" alt="" class="h-20 w-20 rounded-xl object-cover" />
                <button
                    class="absolute top-1 right-1 rounded-full bg-ink/80 px-1.5 text-xs font-medium text-foam"
                    type="button"
                    @click="emit('removeExisting', image.path)"
                >
                    ×
                </button>
            </div>
            <div v-for="(image, index) in pending" :key="image.url" class="relative">
                <img :src="image.url" alt="" class="h-20 w-20 rounded-xl object-cover" />
                <button
                    class="absolute top-1 right-1 rounded-full bg-ink/80 px-1.5 text-xs font-medium text-foam"
                    type="button"
                    @click="emit('removePending', index)"
                >
                    ×
                </button>
            </div>
        </div>
        <label
            :for="id"
            class="inline-flex h-10 w-fit cursor-pointer items-center rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15"
        >
            Add photos
        </label>
        <input :id="id" accept="image/jpeg,image/png,image/webp" class="sr-only" multiple type="file" @change="choose" />
        <p class="text-xs text-ink/50 dark:text-foam/50">JPG, PNG, or WebP. Up to 4 photos.</p>
    </div>
</template>
