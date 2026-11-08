<script setup>
import { Head } from '@inertiajs/vue3';
import PurchaseOrderView from '../../../components/PurchaseOrderView.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    purchaseOrder: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="purchaseOrder.number" />

    <PurchaseOrderView :purchase-order="purchaseOrder" />
</template>
