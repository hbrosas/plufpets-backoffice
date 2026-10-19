<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

defineProps({
    catalogItem: {
        type: Object,
        required: true,
    },
    supplies: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head :title="catalogItem.name" />

    <Teleport defer to="#admin-header-actions">
        <Link
            class="inline-flex h-10 items-center rounded-xl border border-mist px-4 text-sm font-medium dark:border-white/15"
            :href="`/backoffice/shop/catalog/${catalogItem.id}/edit`"
        >
            Edit
        </Link>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-col gap-1">
            <Link class="text-sm font-medium text-pine dark:text-sand" href="/backoffice/shop/catalog">Back to catalog</Link>
            <h1 class="text-2xl font-medium">{{ catalogItem.name }}</h1>
            <p class="text-sm text-ink/60 dark:text-foam/60">
                {{ catalogItem.type_label }}
                <span v-if="catalogItem.category"> · {{ catalogItem.category }}</span>
                <span v-if="catalogItem.brand"> · {{ catalogItem.brand }}</span>
            </p>
        </div>

        <section class="grid gap-4 lg:grid-cols-[16rem_1fr]">
            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <dl class="grid gap-3 text-sm">
                    <div class="flex flex-col gap-1">
                        <dt class="text-ink/50 dark:text-foam/50">Status</dt>
                        <dd class="font-medium">{{ catalogItem.is_active ? 'Active' : 'Hidden' }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-ink/50 dark:text-foam/50">Description</dt>
                        <dd>{{ catalogItem.description || '—' }}</dd>
                    </div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-lg font-medium">Variants</h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead class="text-ink/50 dark:text-foam/50">
                            <tr class="border-b border-mist dark:border-white/10">
                                <th class="py-3 pr-4 font-medium">Photo</th>
                                <th class="py-3 pr-4 font-medium">Name</th>
                                <th class="py-3 pr-4 font-medium">SKU</th>
                                <th class="py-3 pr-4 font-medium">Barcode</th>
                                <th class="py-3 font-medium">Retail price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="catalogItem.variants.length === 0">
                                <td class="py-8 text-ink/60 dark:text-foam/60" colspan="5">No variants.</td>
                            </tr>
                            <tr
                                v-for="variant in catalogItem.variants"
                                :key="variant.id"
                                class="border-b border-mist last:border-b-0 dark:border-white/10"
                            >
                                <td class="py-3 pr-4">
                                    <img
                                        v-if="variant.image_url"
                                        :src="variant.image_url"
                                        alt=""
                                        class="h-12 w-12 rounded-lg object-cover"
                                    />
                                    <span v-else>—</span>
                                </td>
                                <td class="py-3 pr-4">{{ variant.name }}</td>
                                <td class="py-3 pr-4">{{ variant.sku || '—' }}</td>
                                <td class="py-3 pr-4">{{ variant.barcode || '—' }}</td>
                                <td class="py-3">{{ variant.price }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-1">
                <h2 class="text-lg font-medium">Supply list</h2>
                <p class="text-sm text-ink/60 dark:text-foam/60">
                    Print one 40×30mm label for each unit in the supply.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="py-3 pr-4 font-medium">Supply Date</th>
                            <th class="py-3 pr-4 font-medium">Variant</th>
                            <th class="py-3 pr-4 font-medium">Price</th>
                            <th class="py-3 pr-4 font-medium">Quantity</th>
                            <th class="py-3 font-medium">Labels</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="supplies.length === 0">
                            <td class="py-8 text-ink/60 dark:text-foam/60" colspan="5">
                                No supplies recorded yet. Receive a purchase order or convert stock into this item.
                            </td>
                        </tr>
                        <tr
                            v-for="supply in supplies"
                            :key="supply.id"
                            class="border-b border-mist last:border-b-0 dark:border-white/10"
                        >
                            <td class="py-3 pr-4">{{ supply.supply_date }}</td>
                            <td class="py-3 pr-4">{{ supply.variant || '—' }}</td>
                            <td class="py-3 pr-4">{{ supply.price }}</td>
                            <td class="py-3 pr-4 tabular-nums">{{ supply.quantity }}</td>
                            <td class="py-3">
                                <a
                                    class="font-medium text-pine dark:text-sand"
                                    :href="`/backoffice/shop/catalog/${catalogItem.id}/supplies/${supply.id}/labels`"
                                    target="_blank"
                                >
                                    Print labels
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
