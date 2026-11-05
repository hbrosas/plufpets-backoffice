<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CommissionList from '../components/CommissionList.vue';
import CommissionPeriodFilter from '../components/CommissionPeriodFilter.vue';
import LowStockList from '../components/LowStockList.vue';
import AdminLayout from '../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    activeStaffCount: { default: null },
    pulse: { default: null },
    commissions: { default: null },
    stock: { default: null },
});

const hourMax = computed(() => Math.max(...(props.pulse?.hours.map((hour) => hour.amount) ?? [0]), 0));
const canManageBusiness = computed(() => (usePage().props.employee?.permissions ?? []).includes('business.manage'));
const tab = ref('sales');
const tabs = computed(() => [
    ...(props.pulse ? [{ id: 'sales', label: 'Sales' }] : []),
    ...(props.commissions ? [{ id: 'commissions', label: 'Commissions' }] : []),
    ...(props.stock ? [{ id: 'stock', label: 'Stock' }] : []),
]);

watch(tabs, (items) => {
    if (!items.some((item) => item.id === tab.value)) {
        tab.value = items[0]?.id ?? 'sales';
    }
}, { immediate: true });
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <p
            v-if="tabs.length === 0 && activeStaffCount === null"
            class="text-sm text-ink/60 dark:text-foam/60"
        >
            No reports are available for your role.
        </p>

        <div v-if="tabs.length > 0" class="flex flex-wrap gap-2" role="tablist" aria-label="Dashboard">
            <button
                v-for="item in tabs"
                :id="`overview-tab-${item.id}`"
                :key="item.id"
                class="h-10 rounded-xl px-4 text-sm font-medium"
                :class="tab === item.id ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="tab === item.id"
                :aria-controls="`overview-panel-${item.id}`"
                @click="tab = item.id"
            >
                {{ item.label }}
            </button>
        </div>

        <section v-if="pulse" v-show="tab === 'sales'" id="overview-panel-sales" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-sales">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">Today</h2>
                    <p class="text-sm text-ink/55 dark:text-foam/50">
                        Compared with the same day last week.
                        <template v-if="pulse.target"> Daily target {{ pulse.target }}.</template>
                        <template v-else-if="canManageBusiness">
                            <Link class="font-medium text-pine dark:text-sand" href="/backoffice/business">Set a daily target</Link>
                            to track it here.
                        </template>
                    </p>
                </div>
                <Link href="/backoffice/reports/sales" class="text-sm font-medium text-pine dark:text-sand">Sales report</Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <article
                    v-for="metric in pulse.metrics"
                    :key="metric.label"
                    class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
                >
                    <p class="text-sm text-ink/60 dark:text-foam/60">{{ metric.label }}</p>
                    <p class="text-3xl font-medium">{{ metric.today }}</p>
                    <p class="text-sm text-ink/55 dark:text-foam/50">{{ metric.change }} vs last week · {{ metric.last_week }}</p>
                    <p v-if="metric.target_gap" class="text-sm font-medium">{{ metric.target_gap }}</p>
                </article>
            </div>

            <article class="flex flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h3 class="text-lg font-medium">Hourly sales</h3>
                <div class="grid grid-cols-6 gap-2 sm:grid-cols-8 lg:grid-cols-12">
                    <div v-for="hour in pulse.hours" :key="hour.label" class="flex flex-col justify-end gap-1">
                        <div class="flex h-16 items-end">
                            <div
                                class="w-full rounded-md"
                                :class="hour.slow ? 'bg-danger' : 'bg-pine'"
                                :style="{ height: `${hourMax === 0 ? 0 : Math.max((hour.amount / hourMax) * 100, hour.amount > 0 ? 8 : 0)}%` }"
                            />
                        </div>
                        <p class="text-[10px] text-ink/55 dark:text-foam/50">{{ hour.label }}</p>
                        <p v-if="hour.slow" class="text-[10px] font-medium text-danger">Slow</p>
                    </div>
                </div>
            </article>

            <div class="grid gap-4 lg:grid-cols-3">
                <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-lg font-medium">Refunds</h3>
                    <p v-if="pulse.refunds.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No refunds today.</p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="row in pulse.refunds" :key="`${row.cashier}-${row.branch}`" class="flex items-start justify-between gap-3 text-sm">
                            <span>
                                <span :class="row.flagged ? 'font-medium text-danger' : 'font-medium'">{{ row.cashier }}</span>
                                <span class="text-ink/55 dark:text-foam/50"> · {{ row.branch }}</span>
                            </span>
                            <span>{{ row.count }} · {{ row.total }}</span>
                        </li>
                    </ul>
                </article>
                <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-lg font-medium">Discounts</h3>
                    <p v-if="pulse.discounts.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No discounts on today's receipts.</p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="row in pulse.discounts" :key="row.cashier" class="flex items-center justify-between gap-3 text-sm">
                            <span :class="row.flagged ? 'font-medium text-danger' : 'font-medium'">{{ row.cashier }}</span>
                            <span>{{ row.total }}</span>
                        </li>
                    </ul>
                </article>
                <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-lg font-medium">Drawer opens</h3>
                    <p class="text-sm text-ink/55 dark:text-foam/50">Cash in, cash out, and open drawer. Three or more opens are marked.</p>
                    <p v-if="pulse.drawer.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No drawer opens today.</p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="row in pulse.drawer" :key="`${row.cashier}-${row.branch}`" class="flex flex-col gap-0.5 text-sm">
                            <span :class="row.flagged ? 'font-medium text-danger' : 'font-medium'">{{ row.cashier }} · {{ row.opens }}</span>
                            <span class="text-ink/55 dark:text-foam/50">In {{ row.cash_in }} · Out {{ row.cash_out }} · {{ row.branch }}</span>
                        </li>
                    </ul>
                </article>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-lg font-medium">Open shifts</h3>
                    <p v-if="pulse.open_shifts.length === 0" class="text-sm text-ink/55 dark:text-foam/50">Every shift is closed.</p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="shift in pulse.open_shifts" :key="`${shift.branch}-${shift.opened_at}`" class="flex flex-col gap-0.5 text-sm">
                            <span class="font-medium">{{ shift.branch }}</span>
                            <span class="text-ink/55 dark:text-foam/50">{{ shift.opened_by }} · {{ shift.opened_at }} · {{ shift.starting_cash }}</span>
                        </li>
                    </ul>
                </article>
                <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-lg font-medium">Drawer variances</h3>
                    <p v-if="pulse.variances.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No drawers are over or short today.</p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="row in pulse.variances" :key="`${row.branch}-${row.closed_by}`" class="flex items-center justify-between gap-3 text-sm">
                            <span>
                                <span class="font-medium">{{ row.branch }}</span>
                                <span class="text-ink/55 dark:text-foam/50"> · {{ row.closed_by }}</span>
                            </span>
                            <span class="font-medium text-danger">{{ row.direction }} {{ row.variance }}</span>
                        </li>
                    </ul>
                </article>
            </div>
        </section>

        <section v-if="commissions" v-show="tab === 'commissions'" id="overview-panel-commissions" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-commissions">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">Commissions</h2>
                    <p class="text-sm text-ink/55 dark:text-foam/50">
                        {{ commissions.scope === 'all' ? 'This month, by employee.' : 'Choose a period to see your commissions.' }}
                    </p>
                </div>
                <Link href="/backoffice/reports/commissions" class="text-sm font-medium text-pine dark:text-sand">
                    Commissions report
                </Link>
            </div>
            <CommissionPeriodFilter
                v-if="commissions.scope === 'self'"
                action="/backoffice/dashboard"
                :filters="commissions.filters"
            />
            <CommissionList :report="commissions.report" :scope="commissions.scope" />
        </section>

        <section v-if="stock" v-show="tab === 'stock'" id="overview-panel-stock" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-stock">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">Stock</h2>
                    <p class="text-sm text-ink/55 dark:text-foam/50">
                        Products with 5 or fewer on hand, and fast sellers with fewer than 7 days of cover.
                    </p>
                </div>
                <Link href="/backoffice/reports/inventory" class="text-sm font-medium text-pine dark:text-sand">Inventory report</Link>
            </div>
            <LowStockList :items="stock.low" />
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h3 class="text-lg font-medium">Days of cover</h3>
                <p v-if="stock.cover.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No fast seller is under 7 days of cover.</p>
                <ul v-else class="flex flex-col gap-2">
                    <li v-for="item in stock.cover" :key="`${item.product}-${item.branch}`" class="flex items-center justify-between gap-3 text-sm">
                        <span>
                            <span class="font-medium">{{ item.product }}</span>
                            <span v-if="item.variant" class="text-ink/55 dark:text-foam/50"> · {{ item.variant }}</span>
                            <span class="text-ink/55 dark:text-foam/50"> · {{ item.branch }}</span>
                        </span>
                        <span>{{ item.days }} days · {{ item.on_hand }} on hand</span>
                    </li>
                </ul>
            </article>
        </section>

        <section v-if="activeStaffCount !== null" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Staff">
            <article class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">Active staff</p>
                <p class="text-3xl font-medium">{{ activeStaffCount }}</p>
                <p class="text-sm text-ink/55 dark:text-foam/50">Employees who can sign in</p>
            </article>
        </section>
    </div>
</template>
