<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
</script>

<template>
    <Head :title="order.number" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>

        <ShopOrderView :order="order" :schedules="schedules" :statuses="statuses" />
    </div>
</template>
