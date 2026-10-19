<script setup>
import { Head } from '@inertiajs/vue3';
import PurchaseOrderForm from '../../../components/PurchaseOrderForm.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    suppliers: {
        type: Array,
        required: true,
    },
    variants: {
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
});
</script>

<template>
    <Head title="Create purchase order" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <PurchaseOrderForm
            :brands="brands"
            :branches="branches"
            :categories="categories"
            :suppliers="suppliers"
            :variants="variants"
            submit-url="/backoffice/inventory/purchase-orders"
        />
    </div>
</template>
