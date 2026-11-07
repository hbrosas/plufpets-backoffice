<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
    categories: {
        type: Array,
        required: true,
    },
    brands: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const importOpen = ref(false);
const selectedIds = ref([]);
const dialog = ref(null);
const categoryName = ref('');
const brandName = ref(null);
const statusChoice = ref(null);
const preorderChoice = ref(null);

const visibleIds = computed(() => props.catalogItems.data.map((item) => item.id));
const allChecked = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selectedIds.value.includes(id)));
const selectedItems = computed(() => props.catalogItems.data.filter((item) => selectedIds.value.includes(item.id)));

const bulkForm = useForm({
    ids: [],
    action: '',
    category_name: '',
    brand_name: '',
    is_active: true,
    allows_preorder: true,
});

const canApply = computed(() => {
    if (dialog.value === 'category') {
        return categoryName.value !== '';
    }

    if (dialog.value === 'brand') {
        return brandName.value !== null;
    }

    if (dialog.value === 'status') {
        return statusChoice.value !== null;
    }

    if (dialog.value === 'preorder') {
        return preorderChoice.value !== null;
    }

    return dialog.value === 'delete';
});

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

function selectType(type) {
    filters.type = type;
    applyFilters();
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

function toggleItem(id, checked) {
    selectedIds.value = checked
        ? [...selectedIds.value, id]
        : selectedIds.value.filter((selectedId) => selectedId !== id);
}

function toggleAll(event) {
    selectedIds.value = event.target.checked ? [...visibleIds.value] : [];
}

function openDialog(action) {
    dialog.value = action;
    categoryName.value = '';
    brandName.value = null;
    statusChoice.value = null;
    preorderChoice.value = null;
    bulkForm.clearErrors();
}

function closeDialog() {
    dialog.value = null;
    bulkForm.clearErrors();
}

function submitBulk() {
    bulkForm
        .transform(() => {
            const payload = {
                ids: selectedIds.value,
                action: dialog.value,
            };

            if (dialog.value === 'category') {
                payload.category_name = categoryName.value;
            }

            if (dialog.value === 'brand') {
                payload.brand_name = brandName.value;
            }

            if (dialog.value === 'status') {
                payload.is_active = statusChoice.value;
            }

            if (dialog.value === 'preorder') {
                payload.allows_preorder = preorderChoice.value;
            }

            return payload;
        })
        .post('/backoffice/shop/catalog/bulk', {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                closeDialog();
            },
        });
}

watch(visibleIds, (ids) => {
    selectedIds.value = selectedIds.value.filter((id) => ids.includes(id));
});
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

        <Teleport defer to="#admin-header-tabs">
        <div class="flex w-max gap-2" role="tablist" aria-label="Catalog type">
            <button
                v-for="type in types"
                :id="`catalog-tab-${type.value}`"
                :key="type.value"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="filters.type === type.value ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="filters.type === type.value"
                @click="selectType(type.value)"
            >
                {{ type.value === 'product' ? 'Products' : 'Services' }}
            </button>
        </div>
        </Teleport>

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
            <button
                class="h-11 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
                type="submit"
            >
                Search
            </button>
        </form>

        <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-medium">{{ selectedIds.length }} selected</p>
            <button class="h-10 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15" type="button" @click="openDialog('category')">
                Change Category
            </button>
            <button class="h-10 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15" type="button" @click="openDialog('brand')">
                Change Brand
            </button>
            <button class="h-10 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15" type="button" @click="openDialog('status')">
                Change Status
            </button>
            <button class="h-10 rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15" type="button" @click="openDialog('preorder')">
                Change Allow Preorder
            </button>
            <button class="h-10 rounded-xl border border-danger/30 px-4 text-sm font-medium text-danger" type="button" @click="openDialog('delete')">
                Delete
            </button>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="w-12 px-4 py-3 font-medium">
                            <input
                                class="h-4 w-4 accent-pine"
                                type="checkbox"
                                :checked="allChecked"
                                :disabled="catalogItems.data.length === 0"
                                aria-label="Select all products"
                                @change="toggleAll"
                            />
                        </th>
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
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="8">
                            No catalog items yet. Add one or upload a CSV.
                        </td>
                    </tr>
                    <tr
                        v-for="item in catalogItems.data"
                        :key="item.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3">
                            <input
                                class="h-4 w-4 accent-pine"
                                type="checkbox"
                                :checked="selectedIds.includes(item.id)"
                                :aria-label="`Select ${item.name}`"
                                @change="toggleItem(item.id, $event.target.checked)"
                            />
                        </td>
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

        <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
            <form class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink" @submit.prevent="submitBulk">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-lg font-medium">
                        {{
                            {
                                category: 'Change Category',
                                brand: 'Change Brand',
                                status: 'Change Status',
                                preorder: 'Change Allow Preorder',
                                delete: 'Delete',
                            }[dialog]
                        }}
                    </h2>
                    <button class="text-sm font-medium" type="button" @click="closeDialog">Close</button>
                </div>
                <p class="text-sm text-ink/70 dark:text-foam/70">
                    {{ selectedItems.map((item) => item.name).join(', ') }}
                </p>

                <div v-if="dialog === 'category'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="bulk-category">Category</label>
                    <select
                        id="bulk-category"
                        v-model="categoryName"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    >
                        <option value="">Choose category</option>
                        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                    </select>
                    <p v-if="bulkForm.errors.category_name" class="text-sm text-danger">{{ bulkForm.errors.category_name }}</p>
                </div>

                <div v-else-if="dialog === 'brand'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="bulk-brand">Brand</label>
                    <select
                        id="bulk-brand"
                        v-model="brandName"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    >
                        <option :value="null" disabled>Choose brand</option>
                        <option value="">No brand</option>
                        <option v-for="brand in brands" :key="brand" :value="brand">{{ brand }}</option>
                    </select>
                    <p v-if="bulkForm.errors.brand_name" class="text-sm text-danger">{{ bulkForm.errors.brand_name }}</p>
                </div>

                <fieldset v-else-if="dialog === 'status'" class="flex flex-col gap-3">
                    <legend class="text-sm font-medium">Status</legend>
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="statusChoice" class="h-4 w-4 accent-pine" type="radio" :value="true" />
                        Active
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="statusChoice" class="h-4 w-4 accent-pine" type="radio" :value="false" />
                        Hidden
                    </label>
                    <p v-if="bulkForm.errors.is_active" class="text-sm text-danger">{{ bulkForm.errors.is_active }}</p>
                </fieldset>

                <fieldset v-else-if="dialog === 'preorder'" class="flex flex-col gap-3">
                    <legend class="text-sm font-medium">Allow preorder</legend>
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="preorderChoice" class="h-4 w-4 accent-pine" type="radio" :value="true" />
                        Allow preorder
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="preorderChoice" class="h-4 w-4 accent-pine" type="radio" :value="false" />
                        Do not allow preorder
                    </label>
                    <p v-if="bulkForm.errors.allows_preorder" class="text-sm text-danger">{{ bulkForm.errors.allows_preorder }}</p>
                </fieldset>

                <p v-else class="text-sm text-ink/70 dark:text-foam/70">
                    Delete {{ selectedIds.length }} selected {{ selectedIds.length === 1 ? 'product' : 'products' }}?
                </p>
                <p v-if="bulkForm.errors.ids" class="text-sm text-danger">{{ bulkForm.errors.ids }}</p>

                <button
                    class="h-11 rounded-xl text-sm font-medium text-foam disabled:opacity-70"
                    :class="dialog === 'delete' ? 'bg-danger' : 'bg-pine'"
                    type="submit"
                    :disabled="bulkForm.processing || !canApply"
                >
                    {{ bulkForm.processing ? 'Saving…' : dialog === 'delete' ? 'Delete' : 'Apply' }}
                </button>
            </form>
        </div>

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
