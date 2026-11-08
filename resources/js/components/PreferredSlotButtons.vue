<script setup>
const props = defineProps({
    slots: {
        type: Array,
        required: true,
    },
    scheduleId: {
        type: [Number, String],
        default: '',
    },
    preferredOn: {
        type: String,
        default: '',
    },
    tone: {
        type: String,
        default: 'site',
    },
});

const emit = defineEmits(['select']);

function selected(slot) {
    return String(props.scheduleId) === String(slot.id) && props.preferredOn === slot.on;
}

function buttonClass(slot) {
    if (props.tone === 'pos') {
        return selected(slot)
            ? 'border-pine bg-pine text-foam'
            : 'border-mist bg-white text-ink dark:border-white/15 dark:bg-white/5 dark:text-foam';
    }

    return selected(slot)
        ? 'border-navy bg-navy text-white'
        : 'border-black/15 bg-white text-black';
}

function dayClass(slot) {
    if (props.tone === 'pos') {
        return selected(slot) ? 'text-foam/80' : 'text-ink/60 dark:text-foam/60';
    }

    return selected(slot) ? 'text-white/80' : 'text-black/60';
}
</script>

<template>
    <div class="flex flex-wrap gap-2" role="group" aria-label="Preferred slot">
        <button
            v-for="slot in slots"
            :key="`${slot.id}-${slot.on}`"
            class="flex min-w-36 flex-col gap-0.5 rounded-xl border px-3 py-2 text-left"
            :class="buttonClass(slot)"
            type="button"
            :aria-pressed="selected(slot)"
            @click="emit('select', slot)"
        >
            <span class="text-sm font-medium">{{ slot.date }} · {{ slot.time }}</span>
            <span class="text-xs" :class="dayClass(slot)">{{ slot.day }}</span>
        </button>
    </div>
</template>
