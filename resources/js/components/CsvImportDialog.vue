<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FilePicker from './FilePicker.vue';

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    templateHref: {
        type: String,
        required: true,
    },
    previewUrl: {
        type: String,
        required: true,
    },
    confirmUrl: {
        type: String,
        required: true,
    },
    preview: {
        default: null,
    },
    logs: {
        default: null,
    },
});

const emit = defineEmits(['close']);

const step = ref('file');
const form = useForm({
    file: null,
});

const table = computed(() => (step.value === 'logs' ? props.logs : props.preview));
const importedCount = computed(() => props.logs?.rows?.filter((row) => row.imported).length ?? 0);

watch(() => props.open, (isOpen) => {
    if (!isOpen) {
        step.value = 'file';
        form.reset();
        form.clearErrors();
    }
});

function columnLabel(column) {
    const label = column.replaceAll('_', ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
}

function onFileChange(event) {
    form.file = event.target.files?.[0] ?? null;
    step.value = 'file';
}

function importFile() {
    form.post(props.previewUrl, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            step.value = 'review';
        },
    });
}

function confirmImport() {
    form.post(props.confirmUrl, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            step.value = 'logs';
        },
    });
}

function close() {
    emit('close');
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4 py-6">
        <div class="flex max-h-full w-full flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-6 dark:bg-ink" :class="step === 'file' ? 'max-w-md' : 'max-w-5xl'">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">{{ title }}</h2>
                    <p class="text-sm text-ink/70 dark:text-foam/70">{{ description }}</p>
                </div>
                <button class="text-sm font-medium" type="button" @click="close">Close</button>
            </div>

            <template v-if="step === 'file'">
                <a class="text-sm font-medium text-pine dark:text-sand" :href="templateHref">Download template</a>
                <form class="flex flex-col gap-4" @submit.prevent="importFile">
                    <FilePicker
                        :id="`${title}-import-file`"
                        accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        button-label="Choose CSV or Excel file"
                        :file-name="form.file?.name ?? ''"
                        hint="No file chosen"
                        @change="onFileChange"
                    />
                    <p v-if="form.errors.file" class="text-sm text-danger">{{ form.errors.file }}</p>
                    <button
                        class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Reading…' : 'Import file' }}
                    </button>
                </form>
            </template>

            <template v-else>
                <p v-if="step === 'logs'" class="text-sm text-ink/70 dark:text-foam/70">
                    {{ importedCount }} {{ importedCount === 1 ? 'row' : 'rows' }} imported.
                </p>
                <p v-else class="text-sm text-ink/70 dark:text-foam/70">Review the file, then confirm the import.</p>
                <div class="min-h-0 overflow-auto rounded-2xl border border-mist dark:border-white/10">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th v-if="step === 'logs'" class="w-12 px-4 py-3 font-medium"><span class="sr-only">Imported</span></th>
                                <th class="px-4 py-3 font-medium">Row</th>
                                <th v-for="column in table?.columns ?? []" :key="column" class="px-4 py-3 font-medium whitespace-nowrap">
                                    {{ columnLabel(column) }}
                                </th>
                                <th v-if="step === 'logs'" class="px-4 py-3 font-medium">Log</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="(table?.rows ?? []).length === 0">
                                <td class="px-4 py-8 text-ink/60 dark:text-foam/60" :colspan="(table?.columns?.length ?? 0) + (step === 'logs' ? 3 : 1)">
                                    The file has no rows.
                                </td>
                            </tr>
                            <tr
                                v-for="row in table?.rows ?? []"
                                :key="row.row"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td v-if="step === 'logs'" class="px-4 py-3">
                                    <span
                                        v-if="row.imported"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-pine text-foam"
                                        aria-label="Imported"
                                    >
                                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                            <path d="M2 6.2 4.6 8.8 10 3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ row.row }}</td>
                                <td v-for="(value, index) in row.values" :key="`${row.row}-${index}`" class="px-4 py-3 whitespace-nowrap">
                                    {{ value || '—' }}
                                </td>
                                <td v-if="step === 'logs'" class="px-4 py-3 text-danger">{{ row.imported ? '' : row.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="step === 'review'"
                        class="h-11 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                        type="button"
                        @click="step = 'file'"
                    >
                        Choose another file
                    </button>
                    <button
                        v-if="step === 'review'"
                        class="h-11 rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-70"
                        type="button"
                        :disabled="form.processing || (preview?.rows?.length ?? 0) === 0"
                        @click="confirmImport"
                    >
                        {{ form.processing ? 'Importing…' : 'Confirm import' }}
                    </button>
                    <button
                        v-else
                        class="h-11 rounded-xl bg-pine px-4 text-sm font-medium text-foam"
                        type="button"
                        @click="close"
                    >
                        Done
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
