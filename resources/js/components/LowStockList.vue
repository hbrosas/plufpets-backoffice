<script setup>
defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
        <table class="w-full min-w-[36rem] text-left text-sm">
            <thead class="text-ink/50 dark:text-foam/50">
                <tr class="border-b border-mist dark:border-white/10">
                    <th class="px-4 py-3 font-medium">Product</th>
                    <th class="px-4 py-3 font-medium">Branch</th>
                    <th class="px-4 py-3 font-medium">On hand</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="items.length === 0">
                    <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="4">
                        Every product is above 5 on hand.
                    </td>
                </tr>
                <tr
                    v-for="(item, index) in items"
                    :key="`${item.branch}-${item.product}-${item.variant ?? ''}-${index}`"
                    class="border-b border-mist last:border-b-0 dark:border-white/10"
                >
                    <td class="px-4 py-3">
                        <span class="font-medium">{{ item.product }}</span>
                        <span v-if="item.variant" class="text-ink/55 dark:text-foam/50"> · {{ item.variant }}</span>
                    </td>
                    <td class="px-4 py-3">{{ item.branch }}</td>
                    <td class="px-4 py-3">{{ item.quantity }}</td>
                    <td class="px-4 py-3">{{ item.status }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
