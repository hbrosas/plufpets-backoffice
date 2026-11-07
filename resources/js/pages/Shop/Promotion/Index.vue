<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    promotion: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    visible: props.promotion.visible,
    text: props.promotion.text ?? '',
    background_color: props.promotion.background_color,
    text_color: props.promotion.text_color,
});

function submit() {
    form.post('/backoffice/shop/promotion', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Promotional Text" />

    <form class="mx-auto flex w-full max-w-3xl flex-col gap-6" @submit.prevent="submit">
        <div class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-4 sm:p-6 dark:border-white/10 dark:bg-white/5">
            <label class="flex items-center gap-2 text-sm font-medium" for="promo-visible">
                <input id="promo-visible" v-model="form.visible" class="h-4 w-4 accent-pine" type="checkbox" />
                Show promotional text
            </label>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="promo-text">Text</label>
                <textarea
                    id="promo-text"
                    v-model="form.text"
                    class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    maxlength="200"
                    rows="3"
                />
                <p v-if="form.errors.text" class="text-sm text-danger">{{ form.errors.text }}</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="promo-background">Background color</label>
                    <input
                        id="promo-background"
                        v-model="form.background_color"
                        class="h-11 w-full cursor-pointer rounded-xl border border-mist bg-white p-1 dark:border-white/10 dark:bg-white/5"
                        type="color"
                    />
                    <p v-if="form.errors.background_color" class="text-sm text-danger">{{ form.errors.background_color }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="promo-text-color">Text color</label>
                    <input
                        id="promo-text-color"
                        v-model="form.text_color"
                        class="h-11 w-full cursor-pointer rounded-xl border border-mist bg-white p-1 dark:border-white/10 dark:bg-white/5"
                        type="color"
                    />
                    <p v-if="form.errors.text_color" class="text-sm text-danger">{{ form.errors.text_color }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-sm font-medium">Preview</p>
                <p
                    class="rounded-xl px-4 py-3 text-sm leading-5 break-words"
                    :style="{ backgroundColor: form.background_color, color: form.text_color }"
                >
                    {{ form.text || 'Promotional text' }}
                </p>
            </div>
        </div>

        <button
            class="h-12 w-full rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-60 sm:w-fit sm:px-5"
            type="submit"
            :disabled="form.processing"
        >
            {{ form.processing ? 'Saving…' : 'Save promotional text' }}
        </button>
    </form>
</template>
