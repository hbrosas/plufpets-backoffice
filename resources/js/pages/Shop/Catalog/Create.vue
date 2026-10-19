<script setup>
import { Head } from '@inertiajs/vue3';
import CatalogItemForm from '../../../components/CatalogItemForm.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
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
    <Head title="Add catalog item" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <CatalogItemForm
            :brands="brands"
            :branches="branches"
            :categories="categories"
            :modifiers="modifiers"
            :types="types"
            submit-url="/backoffice/shop/catalog"
        />
    </div>
</template>
