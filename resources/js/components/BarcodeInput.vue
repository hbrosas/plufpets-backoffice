<script setup>
const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Scan or type barcode',
    },
    preventEnter: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'scanned']);

function onEnter(event) {
    if (event.isComposing) {
        return;
    }

    const value = event.target instanceof HTMLInputElement ? event.target.value : '';
    emit('update:modelValue', value);
    emit('scanned', value.trim());

    if (props.preventEnter) {
        event.preventDefault();
    }
}
</script>

<template>
    <input
        :id="id"
        :value="modelValue"
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        inputmode="text"
        :placeholder="placeholder"
        class="h-11 w-full rounded-xl border border-mist bg-white px-4 font-mono text-sm outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
        type="text"
        @input="emit('update:modelValue', $event.target.value)"
        @keydown.enter="onEnter"
    />
</template>
