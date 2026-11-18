<script setup>
import { Head } from '@inertiajs/vue3';
import ShopOrderView from '../../../components/ShopOrderView.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    order: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
    schedules: {
        type: Array,
        required: true,
    },
});

</script>

<template>
    <Head :title="order.number" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <ShopOrderView :order="order" :schedules="schedules" :statuses="statuses" />
    </div>
</template>
