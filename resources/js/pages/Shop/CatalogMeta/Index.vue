<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import FilePicker from '../../../components/FilePicker.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
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

const categoryModalOpen = ref(false);
const editingCategory = ref(null);
const posCategories = computed(() => page.props.posCategories ?? []);
const categoryForm = useForm({
    name: '',
    pos_category_id: '',
});

const brandModalOpen = ref(false);
const editingBrand = ref(null);
const brandPreview = ref(null);
const brandForm = useForm({
    name: '',
    image: null,
    remove_image: false,
});

function openCreateCategory() {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryModalOpen.value = true;
}

function openEditCategory(category) {
    editingCategory.value = category;
    categoryForm.name = category.name;
    categoryForm.pos_category_id = category.pos_category_id ?? '';
    categoryForm.clearErrors();
    categoryModalOpen.value = true;
}

function submitCategory() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            categoryModalOpen.value = false;
            categoryForm.reset();
            editingCategory.value = null;
        },
    };

    if (editingCategory.value) {
        categoryForm.put(`/backoffice/shop/categories/${editingCategory.value.id}`, options);
        return;
    }

    categoryForm.post('/backoffice/shop/categories', options);
}

function deleteCategory(category) {
    if (!window.confirm(`Delete ${category.name}? Products using it will keep no category.`)) {
        return;
    }

    router.delete(`/backoffice/shop/categories/${category.id}`, {
        preserveScroll: true,
    });
}

function openCreateBrand() {
    editingBrand.value = null;
    brandForm.reset();
    brandForm.clearErrors();
    brandPreview.value = null;
    brandModalOpen.value = true;
}

function openEditBrand(brand) {
    editingBrand.value = brand;
    brandForm.name = brand.name;
    brandForm.image = null;
    brandForm.remove_image = false;
    brandForm.clearErrors();
    brandPreview.value = brand.image_url;
    brandModalOpen.value = true;
}

function onBrandImageChange(event) {
    const file = event.target.files[0] ?? null;
    brandForm.image = file;
    brandForm.remove_image = false;
    brandPreview.value = file ? URL.createObjectURL(file) : editingBrand.value?.image_url ?? null;
}

function clearBrandImage() {
    brandForm.image = null;
    brandForm.remove_image = true;
    brandPreview.value = null;
}

function submitBrand() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            brandModalOpen.value = false;
            brandForm.reset();
            editingBrand.value = null;
            brandPreview.value = null;
        },
    };

    if (editingBrand.value) {
        brandForm.put(`/backoffice/shop/brands/${editingBrand.value.id}`, options);
        return;
    }

    brandForm.post('/backoffice/shop/brands', options);
}

function deleteBrand(brand) {
    if (!window.confirm(`Delete ${brand.name}? Products using it will keep no brand.`)) {
        return;
    }

    router.delete(`/backoffice/shop/brands/${brand.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Categories & Brands" />

    <Teleport defer to="#admin-header-actions">
        <button
            class="h-10 rounded-xl border border-mist px-4 text-sm font-medium transition hover:border-pine dark:border-white/15 dark:hover:border-sand"
            type="button"
            @click="openCreateCategory"
        >
            Add category
        </button>
        <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="button" @click="openCreateBrand">
            Add brand
        </button>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="flex flex-col gap-4">
                <h2 class="text-lg font-medium">Categories</h2>
                <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">POS category</th>
                                <th class="px-4 py-3 font-medium">Items</th>
                                <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="categories.length === 0">
                                <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="4">No categories yet.</td>
                            </tr>
                            <tr
                                v-for="category in categories"
                                :key="category.id"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="px-4 py-3 font-medium">{{ category.name }}</td>
                                <td class="px-4 py-3">{{ category.pos_category_name }}</td>
                                <td class="px-4 py-3">{{ category.item_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <button class="font-medium text-pine dark:text-sand" type="button" @click="openEditCategory(category)">
                                            Edit
                                        </button>
                                        <button class="font-medium text-danger" type="button" @click="deleteCategory(category)">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-lg font-medium">Brands</h2>
                <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                    <table class="w-full text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="px-4 py-3 font-medium">Brand</th>
                                <th class="px-4 py-3 font-medium">Items</th>
                                <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="brands.length === 0">
                                <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="3">No brands yet.</td>
                            </tr>
                            <tr
                                v-for="brand in brands"
                                :key="brand.id"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="brand.image_url"
                                            :src="brand.image_url"
                                            alt=""
                                            class="h-10 w-10 rounded-lg object-cover"
                                        />
                                        <span class="font-medium">{{ brand.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ brand.item_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <button class="font-medium text-pine dark:text-sand" type="button" @click="openEditBrand(brand)">
                                            Edit
                                        </button>
                                        <button class="font-medium text-danger" type="button" @click="deleteBrand(brand)">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div v-if="categoryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingCategory ? 'Edit category' : 'Add category' }}</h2>
                <button class="text-sm font-medium" type="button" @click="categoryModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitCategory">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="category-name">Name</label>
                    <input
                        id="category-name"
                        v-model="categoryForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="categoryForm.errors.name" class="text-sm text-danger">{{ categoryForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="category-pos">POS category</label>
                    <select
                        id="category-pos"
                        v-model="categoryForm.pos_category_id"
                        class="h-11 rounded-xl border border-mist bg-white pr-11 pl-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                    >
                        <option value="" disabled>Choose a POS category</option>
                        <option v-for="posCategory in posCategories" :key="posCategory.id" :value="posCategory.id">
                            {{ posCategory.name }}
                        </option>
                    </select>
                    <p v-if="categoryForm.errors.pos_category_id" class="text-sm text-danger">
                        {{ categoryForm.errors.pos_category_id }}
                    </p>
                    <p v-if="posCategories.length === 0" class="text-sm text-ink/60 dark:text-foam/60">
                        Add a POS category first in POS Management.
                    </p>
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="categoryForm.processing"
                >
                    {{ categoryForm.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="brandModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">{{ editingBrand ? 'Edit brand' : 'Add brand' }}</h2>
                <button class="text-sm font-medium" type="button" @click="brandModalOpen = false">Close</button>
            </div>
            <form class="flex flex-col gap-4" @submit.prevent="submitBrand">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="brand-name">Name</label>
                    <input
                        id="brand-name"
                        v-model="brandForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="brandForm.errors.name" class="text-sm text-danger">{{ brandForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium" id="brand-image-label">Logo or image</p>
                    <FilePicker
                        id="brand-image"
                        button-label="Choose image"
                        :file-name="brandForm.image?.name ?? ''"
                        :hint="brandPreview && !brandForm.image ? 'Using current image' : 'JPG, PNG, or WebP · no file chosen'"
                        @change="onBrandImageChange"
                    />
                    <p v-if="brandForm.errors.image" class="text-sm text-danger">{{ brandForm.errors.image }}</p>
                    <div v-if="brandPreview" class="flex items-center gap-3">
                        <img :src="brandPreview" alt="" class="h-16 w-16 rounded-xl object-cover" />
                        <button class="text-sm font-medium text-danger" type="button" @click="clearBrandImage">
                            Remove image
                        </button>
                    </div>
                </div>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="brandForm.processing"
                >
                    {{ brandForm.processing ? 'Saving…' : 'Save' }}
                </button>
            </form>
        </div>
    </div>
</template>
