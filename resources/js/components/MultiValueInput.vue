<script setup>
import { ref } from 'vue';

const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    modelValue: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Type an option, then press Enter',
    },
});

const emit = defineEmits(['update:modelValue']);
const draft = ref('');

function normalizedOptions() {
    return props.modelValue.map((option) => String(option));
}

function addOption(raw) {
    const value = String(raw ?? '').trim();

    if (value === '') {
        return;
    }

    const exists = normalizedOptions().some((option) => option.toLowerCase() === value.toLowerCase());

    if (!exists) {
        emit('update:modelValue', [...normalizedOptions(), value]);
    }

    draft.value = '';
}

function removeOption(index) {
    emit(
        'update:modelValue',
        normalizedOptions().filter((_, optionIndex) => optionIndex !== index),
    );
}

function onKeydown(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addOption(draft.value);
        return;
    }

    if (event.key === 'Backspace' && draft.value === '' && normalizedOptions().length > 0) {
        removeOption(normalizedOptions().length - 1);
    }
}

function commitDraft() {
    addOption(draft.value);
}

defineExpose({
    commitDraft,
});

function onPaste(event) {
    const text = event.clipboardData?.getData('text') ?? '';

    if (!text.includes(',')) {
        return;
    }

    event.preventDefault();
    text.split(/[,\n]/).forEach((part) => addOption(part));
}
</script>

<template>
    <div
        class="flex min-h-11 flex-wrap items-center gap-2 rounded-xl border border-mist bg-white px-3 py-2 focus-within:border-pine dark:border-white/10 dark:bg-white/5"
        @click="$refs.optionInput?.focus()"
    >
        <span
            v-for="(option, index) in modelValue"
            :key="`${option}-${index}`"
            class="inline-flex items-center gap-1 rounded-lg bg-pine/10 px-2 py-1 text-sm text-pine dark:bg-sand/15 dark:text-sand"
        >
            {{ option }}
            <button class="text-sm font-medium" type="button" @click.stop="removeOption(index)">
                <span class="sr-only">Remove {{ option }}</span>
                ×
            </button>
        </span>
        <input
            :id="id"
            ref="optionInput"
            v-model="draft"
            autocomplete="off"
            class="min-w-[8rem] flex-1 border-0 bg-transparent py-1 text-sm outline-none"
            :placeholder="modelValue.length === 0 ? placeholder : 'Add another option'"
            type="text"
            @blur="addOption(draft)"
            @keydown="onKeydown"
            @paste="onPaste"
        />
    </div>
</template>
