<script setup>
import { Head } from '@inertiajs/vue3';
import CatalogItemForm from '../../../components/CatalogItemForm.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    catalogItem: {
        type: Object,
        required: true,
    },
    types: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    brands: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    modifiers: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head :title="`Edit ${catalogItem.name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <CatalogItemForm
            :item="catalogItem"
            method="put"
            :submit-url="`/backoffice/shop/catalog/${catalogItem.id}`"
            :brands="brands"
            :branches="branches"
            :categories="categories"
            :modifiers="modifiers"
            :types="types"
        />
    </div>
</template>
