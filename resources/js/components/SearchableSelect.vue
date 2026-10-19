<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Type to search',
    },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);
const editing = ref(false);

const selected = computed(() =>
    props.options.find((option) => String(option.id) === String(props.modelValue)) ?? null,
);

const displayValue = computed(() => {
    if (editing.value) {
        return query.value;
    }

    return selected.value?.label ?? query.value;
});

const matches = computed(() => {
    const term = (editing.value ? query.value : '').trim().toLowerCase();

    if (term === '') {
        return props.options;
    }

    return props.options.filter((option) => {
        const haystack = `${option.label} ${option.sku ?? ''}`.toLowerCase();

        return haystack.includes(term);
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (value === '' || value === null) {
            return;
        }

        if (!editing.value && selected.value) {
            query.value = selected.value.label;
        }
    },
);

function onFocus() {
    editing.value = true;
    query.value = selected.value?.label ?? query.value;
    open.value = true;
}

function onInput(event) {
    editing.value = true;
    query.value = event.target.value;
    open.value = true;

    if (selected.value && query.value !== selected.value.label) {
        emit('update:modelValue', '');
    }
}

function pick(option) {
    emit('update:modelValue', option.id);
    query.value = option.label;
    editing.value = false;
    open.value = false;
}

function close() {
    window.setTimeout(() => {
        const term = query.value.trim().toLowerCase();
        const exact = matches.value.filter((option) => option.label.toLowerCase() === term);

        if (exact.length === 1) {
            pick(exact[0]);
            return;
        }

        if (term !== '' && matches.value.length === 1) {
            pick(matches.value[0]);
            return;
        }

        open.value = false;
        editing.value = false;
    }, 120);
}

function onKeydown(event) {
    if (event.key === 'Enter' && open.value && matches.value.length > 0) {
        event.preventDefault();
        pick(matches.value[0]);
    }

    if (event.key === 'Escape') {
        open.value = false;
        editing.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            :value="displayValue"
            autocomplete="off"
            class="h-11 w-full rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
            :placeholder="placeholder"
            role="combobox"
            :aria-expanded="open"
            :aria-controls="`${id}-list`"
            type="text"
            @blur="close"
            @focus="onFocus"
            @input="onInput"
            @keydown="onKeydown"
        />
        <ul
            v-if="open"
            :id="`${id}-list`"
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-mist bg-white py-1 shadow-lg dark:border-white/10 dark:bg-ink"
            role="listbox"
        >
            <li v-if="matches.length === 0" class="px-4 py-2 text-sm text-ink/60 dark:text-foam/60">
                No matching products. Use New product to add one.
            </li>
            <li
                v-for="option in matches"
                :key="option.id"
                class="cursor-pointer px-4 py-2 text-sm hover:bg-pine/10 dark:hover:bg-sand/10"
                role="option"
                :aria-selected="String(option.id) === String(modelValue)"
                @mousedown.prevent="pick(option)"
            >
                {{ option.label }}
            </li>
        </ul>
    </div>
</template>
