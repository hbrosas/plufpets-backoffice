<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FilePicker from '../../../components/FilePicker.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    banner: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    image: null,
    remove_image: false,
});
const preview = ref(props.banner.image_url);
const fileName = ref('');

function onImage(event) {
    const file = event.target.files?.[0] ?? null;
    form.image = file;
    form.remove_image = false;
    fileName.value = file?.name ?? '';
    preview.value = file ? URL.createObjectURL(file) : props.banner.image_url;
}

function clearImage() {
    form.image = null;
    form.remove_image = true;
    fileName.value = '';
    preview.value = null;
}

function submit() {
    form.post('/backoffice/shop/banner', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.image = null;
            form.remove_image = false;
            fileName.value = '';
        },
    });
}
</script>

<template>
    <Head title="Banner" />

    <form class="mx-auto flex w-full max-w-3xl flex-col gap-6" @submit.prevent="submit">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-medium">Banner</h1>
            <p class="text-sm text-ink/70 dark:text-foam/70">
                Shown on the public shop. Use a 1600 × 500 pixel image. The banner stays hidden until a photo is uploaded.
            </p>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-4 sm:p-6 dark:border-white/10 dark:bg-white/5">
            <FilePicker
                id="shop-banner-image"
                button-label="Choose image"
                :file-name="fileName"
                :hint="preview && !form.image ? 'Using current image' : 'JPG, PNG, or WebP · 1600 × 500'"
                @change="onImage"
            />
            <p v-if="form.errors.image" class="text-sm text-danger">{{ form.errors.image }}</p>
            <div v-if="preview" class="flex flex-col gap-3">
                <img :src="preview" alt="" class="aspect-[1600/500] w-full rounded-xl object-cover" />
                <button class="h-10 w-fit text-sm font-medium text-danger" type="button" @click="clearImage">Remove image</button>
            </div>
            <p v-else class="rounded-xl border border-dashed border-mist px-4 py-8 text-center text-sm text-ink/60 dark:border-white/10 dark:text-foam/60">
                No banner uploaded.
            </p>
        </div>

        <button
            class="h-12 w-full rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-60 sm:w-fit sm:px-5"
            type="submit"
            :disabled="form.processing"
        >
            {{ form.processing ? 'Saving…' : 'Save banner' }}
        </button>
    </form>
</template>
