<script setup>
import { Head } from '@inertiajs/vue3';
import BranchForm from '../../../components/BranchForm.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    branch: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`Edit ${branch.name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <BranchForm :branch="branch" method="put" :submit-url="`/backoffice/shop/branches/${branch.id}`" />
    </div>
</template>
