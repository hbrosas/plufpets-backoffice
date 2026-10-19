<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    branches: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

function deleteBranch(branch) {
    if (!window.confirm(`Delete ${branch.name}?`)) {
        return;
    }

    router.delete(`/backoffice/shop/branches/${branch.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Branches" />

    <Teleport defer to="#admin-header-actions">
        <Link
            class="inline-flex h-10 items-center rounded-xl bg-pine px-4 text-sm font-medium text-foam transition hover:bg-pine/90"
            href="/backoffice/shop/branches/create"
        >
            Add branch
        </Link>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Registered name</th>
                        <th class="px-4 py-3 font-medium">TIN</th>
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 font-medium">Address</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="branches.data.length === 0">
                        <td class="px-4 py-10 text-ink/60 dark:text-foam/60" colspan="7">
                            No branches yet. Add the first shop location.
                        </td>
                    </tr>
                    <tr
                        v-for="branch in branches.data"
                        :key="branch.id"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td class="px-4 py-3 font-medium">{{ branch.name }}</td>
                        <td class="px-4 py-3">{{ branch.registered_business_name || '—' }}</td>
                        <td class="px-4 py-3">{{ branch.tin || '—' }}</td>
                        <td class="px-4 py-3">{{ branch.code || '—' }}</td>
                        <td class="px-4 py-3">{{ branch.address || '—' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="branch.is_active ? 'bg-pine/10 text-pine dark:bg-sand/15 dark:text-sand' : 'bg-mist text-ink/50 dark:bg-white/10 dark:text-foam/50'"
                            >
                                {{ branch.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                <Link class="font-medium text-pine dark:text-sand" :href="`/backoffice/shop/branches/${branch.id}/edit`">
                                    Edit
                                </Link>
                                <button class="font-medium text-danger" type="button" @click="deleteBranch(branch)">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
