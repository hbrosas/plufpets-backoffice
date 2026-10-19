<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    receipt: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    reason: '',
});

function submit() {
    form.post(`/backoffice/sales/receipts/${props.receipt.id}/refund`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <form class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">Refund {{ receipt.number }}</h2>
                    <p class="text-sm text-ink/70 dark:text-foam/70">
                        Stock will be returned to {{ receipt.branch }}. This cannot be undone.
                    </p>
                </div>
                <button class="text-sm font-medium" type="button" @click="emit('close')">Close</button>
            </div>
            <label class="flex flex-col gap-1 text-sm">
                Reason
                <textarea
                    v-model="form.reason"
                    class="min-h-24 rounded-xl border border-mist bg-white px-3 py-2 text-sm outline-none transition focus:border-pine dark:border-white/15 dark:bg-white/5"
                    required
                />
            </label>
            <p v-if="form.errors.reason" class="text-sm text-danger">{{ form.errors.reason }}</p>
            <p v-if="form.errors.status" class="text-sm text-danger">{{ form.errors.status }}</p>
            <button
                class="h-11 rounded-xl bg-danger px-4 text-sm font-medium text-foam disabled:opacity-70"
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Refunding…' : 'Refund receipt' }}
            </button>
        </form>
    </div>
</template>
