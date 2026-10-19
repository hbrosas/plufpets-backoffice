<script setup>
import { Head } from '@inertiajs/vue3';
import BranchForm from '../../../components/BranchForm.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});
</script>

<template>
    <Head title="Add branch" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <BranchForm submit-url="/backoffice/shop/branches" />
    </div>
</template>
