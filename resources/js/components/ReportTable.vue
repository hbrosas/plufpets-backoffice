<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        required: true,
    },
    empty: {
        type: String,
        default: 'Nothing in this period.',
    },
});
</script>

<template>
    <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
        <h2 class="text-lg font-medium">{{ title }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[20rem] text-left text-sm">
                <thead class="text-ink/50 dark:text-foam/50">
                    <tr class="border-b border-mist dark:border-white/10">
                        <th v-for="column in columns" :key="column.key" class="px-3 py-2 font-medium">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td class="px-3 py-6 text-ink/60 dark:text-foam/60" :colspan="columns.length">{{ empty }}</td>
                    </tr>
                    <tr
                        v-for="(row, index) in rows"
                        :key="index"
                        class="border-b border-mist last:border-b-0 dark:border-white/10"
                    >
                        <td v-for="column in columns" :key="column.key" class="px-3 py-2">
                            <span :class="row.flagged && column.key === columns[0].key ? 'font-medium text-danger' : ''">
                                {{ row[column.key] }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>
</template>
