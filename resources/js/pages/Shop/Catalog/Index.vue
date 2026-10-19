<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import FilePicker from '../../../components/FilePicker.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    catalogItems: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    types: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const importOpen = ref(false);

const filters = useForm({
    search: props.filters.search,
    type: props.filters.type,
});

const importForm = useForm({
    file: null,
});

function applyFilters() {
    filters.get('/backoffice/shop/catalog', {
        preserveState: true,
        replace: true,
    });
}

function onFileChange(event) {
    importForm.file = event.target.files[0] ?? null;
}

function submitImport() {
    importForm.post('/backoffice/shop/catalog/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            importOpen.value = false;
        },
    });
}

function deleteItem(item) {
    if (!window.confirm(`Delete ${item.name}?`)) {
        return;
    }

    router.delete(`/backoffice/shop/catalog/${item.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Catalog" />

    <Teleport defer to="#admin-header-actions">
        <button
            class="h-10 rounded-xl border border-mist px-4 text-sm font-medium transition hover:border-pine dark:border-white/15 dark:hover:border-sand"
            type="button"
            @click="importOpen = true"
        >
            Bulk upload
        </button>
        <Link
            class="inline-flex h-10 items-center rounded-xl bg-pine px-4 text-sm font-medium text-foam transition hover:bg-pine/90"
            href="/backoffice/shop/catalog/create"
        >
            Add item
        </Link>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div
            v-if="flash.import_errors?.length"
            class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
        >
            <p class="font-medium">Skipped rows</p>
            <ul class="mt-2 flex flex-col gap-1">
                <li v-for="error in flash.import_errors" :key="`${error.row}-${error.message}`">
                    Row {{ error.row }}: {{ error.message }}
                </li>
            </ul>
        </div>

        <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="applyFilters">
            <input
                v-model="filters.search"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                class="h-11 flex-1 rounded-xl border border-mist bg-white px-4 text-sm outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                placeholder="Search by name, SKU, or barcode"
                type="text"
            />
            <select
                v-model="filters.type"
                class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none transition focus:border-pine dark:border-white/10 dark:bg-white/5"
            >
                <option value="">All types</option>
                <option v-for="type in types" :key="type.value" :value="type.value">
                    {{ type.label }}
                </option>
            </select>
            <button
                class="h-11 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                type="submit"
            >
                Filter
            </button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Item</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 font-medium">Brand</th>
                        <th class="px-4 py-3 font-medium">Variants</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="catalogItems.data.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="7">
                            No catalog items yet. Add one or upload a CSV.
                        </td>
                    </tr>
                    <tr
                        v-for="item in catalogItems.data"
                        :key="item.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="item.image_url"
                                    :src="item.image_url"
                                    alt=""
                                    class="h-10 w-10 rounded-lg object-cover"
                                />
                                <span class="font-medium">{{ item.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ item.type_label }}</td>
                        <td class="px-4 py-3">{{ item.category || '—' }}</td>
                        <td class="px-4 py-3">{{ item.brand || '—' }}</td>
                        <td class="px-4 py-3">{{ item.variant_count }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="item.is_active ? 'bg-pine/10 text-pine dark:bg-sand/15 dark:text-sand' : 'bg-mist text-ink/50 dark:bg-white/10 dark:text-foam/50'"
                            >
                                {{ item.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <Link class="font-medium text-pine dark:text-sand" :href="`/backoffice/shop/catalog/${item.id}`">
                                    View
                                </Link>
                                <Link class="font-medium text-pine dark:text-sand" :href="`/backoffice/shop/catalog/${item.id}/edit`">
                                    Edit
                                </Link>
                                <button class="font-medium text-danger" type="button" @click="deleteItem(item)">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="catalogItems.last_page > 1" class="flex gap-3" aria-label="Pagination">
            <Link
                v-if="catalogItems.prev_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="catalogItems.prev_page_url"
                :preserve-state="true"
            >
                Previous
            </Link>
            <Link
                v-if="catalogItems.next_page_url"
                class="text-sm font-medium text-pine dark:text-sand"
                :href="catalogItems.next_page_url"
                :preserve-state="true"
            >
                Next
            </Link>
        </nav>

        <div v-if="importOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
            <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2 class="text-lg font-medium">Bulk upload</h2>
                        <p class="text-sm text-ink/70 dark:text-foam/70">
                            CSV columns: type, name, description, category, brand, variant_name, sku, barcode, price, is_active.
                        </p>
                    </div>
                    <button class="text-sm font-medium" type="button" @click="importOpen = false">Close</button>
                </div>

                <a class="text-sm font-medium text-pine dark:text-sand" href="/backoffice/shop/catalog/import/template">
                    Download template
                </a>

                <form class="flex flex-col gap-4" @submit.prevent="submitImport">
                    <FilePicker
                        id="import-file"
                        accept=".csv,text/csv"
                        button-label="Choose CSV file"
                        :file-name="importForm.file?.name ?? ''"
                        hint="No file chosen"
                        @change="onFileChange"
                    />
                    <p v-if="importForm.errors.file" class="text-sm text-danger">{{ importForm.errors.file }}</p>
                    <button
                        class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                        type="submit"
                        :disabled="importForm.processing"
                    >
                        {{ importForm.processing ? 'Uploading…' : 'Upload CSV' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
