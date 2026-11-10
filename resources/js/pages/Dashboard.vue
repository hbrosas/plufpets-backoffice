<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import CommissionList from '../components/CommissionList.vue';
import CommissionPeriodFilter from '../components/CommissionPeriodFilter.vue';
import CountBadge from '../components/CountBadge.vue';
import LowStockList from '../components/LowStockList.vue';
import AdminLayout from '../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    pulse: { default: null },
    commissions: { default: null },
    stock: { default: null },
    branchOrders: { type: Array, default: () => [] },
    attendance: { default: null },
    cashAdvances: { default: null },
    schedule: { default: null },
});

const hourMax = computed(() => Math.max(...(props.pulse?.hours.map((hour) => hour.amount) ?? [0]), 0));
const canManageBusiness = computed(() => (usePage().props.employee?.permissions ?? []).includes('business.manage'));
const employeeView = computed(() => props.commissions?.scope === 'self' && props.attendance !== null);
const tab = ref('activities');
const branchOrderTabs = ref({});
const tabs = computed(() => [
    { id: 'activities', label: "Today's Activities" },
    ...(props.schedule ? [{ id: 'employees', label: 'Calendar' }] : []),
    ...(props.pulse ? [{ id: 'sales', label: 'Sales' }] : []),
    ...(props.commissions ? [{ id: 'commissions', label: 'Commissions' }] : []),
    ...(props.stock ? [{ id: 'stock', label: 'Stock' }] : []),
]);
const requestsOpen = ref(false);
const selectedDay = ref(null);
const reviewNotes = reactive({});
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const calendarDays = computed(() => {
    if (!props.schedule?.month) {
        return [];
    }

    const [year, month] = props.schedule.month.split('-').map(Number);
    const firstWeekday = (new Date(year, month - 1, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, month, 0).getDate();
    const total = Math.ceil((firstWeekday + daysInMonth) / 7) * 7;

    return Array.from({ length: total }, (_, index) => {
        const dayNumber = index - firstWeekday + 1;
        const date = new Date(year, month - 1, dayNumber);
        const key = [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');

        return {
            key,
            number: date.getDate(),
            inMonth: dayNumber >= 1 && dayNumber <= daysInMonth,
            events: props.schedule.events.filter((event) => event.dates.includes(key)),
            guests: (props.schedule.guests ?? []).filter((guest) => guest.dates.includes(key)),
            holidays: (props.schedule.holidays ?? []).filter((holiday) => holiday.date === key),
        };
    });
});

watch(tabs, (items) => {
    if (!items.some((item) => item.id === tab.value)) {
        tab.value = items[0]?.id ?? 'activities';
    }
}, { immediate: true });

function branchOrderTab(branchId) {
    return branchOrderTabs.value[branchId] ?? 'open';
}

function selectBranchOrderTab(branchId, value) {
    branchOrderTabs.value = {
        ...branchOrderTabs.value,
        [branchId]: value,
    };
}

function showMonth(month) {
    selectedDay.value = null;
    router.get('/backoffice/dashboard', { month }, { preserveState: true, preserveScroll: true });
}

function openDay(day) {
    if (!day.inMonth) {
        return;
    }

    selectedDay.value = day;
}

function dayTitle(day) {
    const [year, month, date] = day.key.split('-').map(Number);

    return new Date(year, month - 1, date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function reviewRequest(filing, status) {
    router.put(`/backoffice/staff/approvals/${filing.id}`, {
        status,
        review_note: reviewNotes[filing.id] ?? '',
    }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Dashboard" />

    <Teleport v-if="employeeView" defer to="#admin-header-actions">
        <CommissionPeriodFilter action="/backoffice/dashboard" :filters="commissions.filters" />
    </Teleport>

    <Teleport v-if="!employeeView && tabs.length > 1" defer to="#admin-header-tabs">
        <div class="flex w-max gap-2" role="tablist" aria-label="Dashboard">
            <button
                v-for="item in tabs"
                :id="`overview-tab-${item.id}`"
                :key="item.id"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="tab === item.id ? 'bg-pine text-foam' : 'border border-mist bg-white'"
                type="button"
                role="tab"
                :aria-selected="tab === item.id"
                :aria-controls="`overview-panel-${item.id}`"
                @click="tab = item.id"
            >
                {{ item.label }}
            </button>
        </div>
    </Teleport>

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div v-if="employeeView" class="grid gap-4 sm:grid-cols-3">
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm text-ink/60 dark:text-foam/60">Commissions</p>
                    <Link href="/backoffice/reports/commissions" class="shrink-0 text-sm font-medium text-pine dark:text-sand">
                        Commissions report
                    </Link>
                </div>
                <p class="text-3xl font-medium">{{ commissions.report.total }}</p>
            </article>
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">No. of Leaves</p>
                <p class="text-3xl font-medium">{{ attendance.leaves }}</p>
            </article>
            <article class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-ink/60 dark:text-foam/60">No. of Overnight</p>
                <p class="text-3xl font-medium">{{ attendance.overnights }}</p>
            </article>
        </div>

        <section v-if="employeeView" class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">Cash advances</h2>
            <div class="overflow-x-auto rounded-2xl border border-mist bg-white dark:border-white/10 dark:bg-white/5">
                <table class="w-full min-w-[36rem] text-left text-sm">
                    <thead class="text-ink/50 dark:text-foam/50">
                        <tr class="border-b border-mist dark:border-white/10">
                            <th class="px-4 py-3 font-medium">Requested</th>
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Reason</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!cashAdvances?.length">
                            <td class="px-4 py-8 text-ink/60 dark:text-foam/60" colspan="4">No cash advances.</td>
                        </tr>
                        <tr v-for="advance in cashAdvances" :key="advance.id" class="border-b border-mist last:border-b-0 dark:border-white/10">
                            <td class="px-4 py-3">{{ advance.requested_on }}</td>
                            <td class="px-4 py-3 font-medium">{{ advance.amount }}</td>
                            <td class="px-4 py-3">{{ advance.reason || '—' }}</td>
                            <td class="px-4 py-3">{{ advance.status_label }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <p
            v-if="!employeeView && tabs.length === 0"
            class="text-sm text-ink/60 dark:text-foam/60"
        >
            No reports are available for your role.
        </p>

        <section v-if="!employeeView && pulse" v-show="tab === 'sales'" id="overview-panel-sales" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-sales">
            <div class="flex flex-wrap items-center justify-end gap-3">
                <Link v-if="!pulse.target && canManageBusiness" class="text-sm font-medium text-pine dark:text-sand" href="/backoffice/business">Set a daily target</Link>
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

        <section v-if="!employeeView" v-show="tab === 'activities'" id="overview-panel-activities" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-activities">
            <p v-if="branchOrders.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No branches yet.</p>
            <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <article
                    v-for="branch in branchOrders"
                    :key="branch.id"
                    class="flex min-w-0 flex-col gap-4 rounded-2xl border border-mist bg-white p-5 dark:border-white/10 dark:bg-white/5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h3 class="text-lg font-medium">{{ branch.name }}</h3>
                        <Link v-if="branch.pos_href" :href="branch.pos_href" class="text-sm font-medium text-pine dark:text-sand">Open register</Link>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1">
                            <p class="text-sm text-ink/60 dark:text-foam/60">Current sales</p>
                            <p class="text-2xl font-medium">{{ branch.sales_label }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="text-sm text-ink/60 dark:text-foam/60">Current cash</p>
                            <p class="text-2xl font-medium">{{ branch.cash_label }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h4 class="text-sm font-medium">Shift activity</h4>
                        <p v-if="branch.activities.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No shift activity yet.</p>
                        <ul v-else class="flex max-h-64 flex-col gap-2 overflow-y-auto rounded-xl border border-mist p-3 dark:border-white/10">
                            <li v-for="activity in branch.activities" :key="activity.key" class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0">
                                    <span class="font-medium">{{ activity.title }}</span>
                                    <span class="text-ink/55 dark:text-foam/50"> · {{ activity.detail }} · {{ activity.time }}</span>
                                </span>
                                <span class="shrink-0 font-medium">{{ activity.amount_label }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap gap-2" role="tablist" :aria-label="`${branch.name} orders`">
                            <button
                                :id="`branch-${branch.id}-tab-open`"
                                class="inline-flex h-9 items-center gap-2 rounded-xl px-3 text-sm font-medium"
                                :class="branchOrderTab(branch.id) === 'open' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                                type="button"
                                role="tab"
                                :aria-selected="branchOrderTab(branch.id) === 'open'"
                                :aria-controls="`branch-${branch.id}-panel-open`"
                                @click="selectBranchOrderTab(branch.id, 'open')"
                            >
                                Open Orders
                                <CountBadge :count="branch.open.length" />
                            </button>
                            <button
                                :id="`branch-${branch.id}-tab-online`"
                                class="inline-flex h-9 items-center gap-2 rounded-xl px-3 text-sm font-medium"
                                :class="branchOrderTab(branch.id) === 'online' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                                type="button"
                                role="tab"
                                :aria-selected="branchOrderTab(branch.id) === 'online'"
                                :aria-controls="`branch-${branch.id}-panel-online`"
                                @click="selectBranchOrderTab(branch.id, 'online')"
                            >
                                Online Orders
                                <CountBadge :count="branch.online.length" />
                            </button>
                        </div>
                        <div
                            v-show="branchOrderTab(branch.id) === 'open'"
                            :id="`branch-${branch.id}-panel-open`"
                            role="tabpanel"
                            :aria-labelledby="`branch-${branch.id}-tab-open`"
                        >
                            <p v-if="branch.open.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No open orders.</p>
                            <ul v-else class="flex flex-col gap-3">
                                <li v-for="order in branch.open" :key="order.id" class="flex flex-col gap-1 text-sm">
                                    <span class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <span class="font-medium">{{ order.name }}</span>
                                        <span class="font-medium">{{ order.total_label }}</span>
                                    </span>
                                    <span class="text-ink/55 dark:text-foam/50">{{ order.saved_at }} · {{ order.saved_by }}</span>
                                    <ul v-if="order.items.length > 0" class="flex flex-col gap-0.5 text-ink/55 dark:text-foam/50">
                                        <li v-for="(item, index) in order.items" :key="`${order.id}-${index}`">{{ item.quantity }} × {{ item.name }}</li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                        <div
                            v-show="branchOrderTab(branch.id) === 'online'"
                            :id="`branch-${branch.id}-panel-online`"
                            role="tabpanel"
                            :aria-labelledby="`branch-${branch.id}-tab-online`"
                        >
                            <p v-if="branch.online.length === 0" class="text-sm text-ink/55 dark:text-foam/50">No online orders in progress.</p>
                            <ul v-else class="flex flex-col gap-3">
                                <li v-for="order in branch.online" :key="order.id" class="flex flex-col gap-1 text-sm">
                                    <span class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <span class="font-medium">
                                            <Link v-if="order.href" :href="order.href" class="text-pine dark:text-sand">{{ order.number }}</Link>
                                            <template v-else>{{ order.number }}</template>
                                        </span>
                                        <span class="font-medium">{{ order.total_label }}</span>
                                    </span>
                                    <span class="text-ink/55 dark:text-foam/50">{{ order.status_label }} · {{ order.customer }} · {{ order.placed_at }}</span>
                                    <ul v-if="order.items.length > 0" class="flex flex-col gap-0.5 text-ink/55 dark:text-foam/50">
                                        <li v-for="(item, index) in order.items" :key="`${order.id}-${index}`">{{ item.quantity }} × {{ item.name }}</li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section v-if="!employeeView && commissions" v-show="tab === 'commissions'" id="overview-panel-commissions" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-commissions">
            <CommissionPeriodFilter
                v-if="commissions.scope === 'self'"
                action="/backoffice/dashboard"
                :filters="commissions.filters"
            />
            <CommissionList :report="commissions.report" :scope="commissions.scope" report-href="/backoffice/reports/commissions" />
        </section>

        <section v-if="!employeeView && stock" v-show="tab === 'stock'" id="overview-panel-stock" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-stock">
            <div class="flex justify-end">
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

        <section v-if="schedule" v-show="tab === 'employees'" id="overview-panel-employees" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="overview-tab-employees">
            <button
                class="flex w-full max-w-xs flex-col gap-2 rounded-2xl border border-mist bg-white p-5 text-left dark:border-white/10 dark:bg-white/5"
                type="button"
                @click="requestsOpen = true"
            >
                <p class="text-sm text-ink/60 dark:text-foam/60">For Approval</p>
                <p class="text-3xl font-medium">{{ schedule.requests.length }}</p>
            </button>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button class="h-10 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15" type="button" @click="showMonth(schedule.previous)">
                    Previous
                </button>
                <p class="text-sm font-medium">{{ schedule.label }}</p>
                <button class="h-10 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15" type="button" @click="showMonth(schedule.next)">
                    Next
                </button>
            </div>
            <div class="flex flex-wrap gap-3 text-xs">
                <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-danger/20" /> Leave</span>
                <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-pine/20 dark:bg-sand/30" /> Overnight</span>
                <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-sand" /> Holiday</span>
                <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-ember/30" /> Hotel</span>
                <span class="text-ink/55 dark:text-foam/50">Dashed means still pending.</span>
            </div>
            <div class="overflow-x-auto">
                <div class="grid min-w-[44rem] grid-cols-7 gap-px overflow-hidden rounded-2xl border border-mist bg-mist dark:border-white/10 dark:bg-white/10">
                    <div v-for="weekday in weekdays" :key="weekday" class="bg-white px-2 py-2 text-center text-xs font-medium text-ink/55 dark:bg-ink dark:text-foam/50">
                        {{ weekday }}
                    </div>
                    <button
                        v-for="day in calendarDays"
                        :key="day.key"
                        class="flex min-h-24 flex-col gap-1 bg-white p-2 text-left dark:bg-ink"
                        :class="day.inMonth ? 'hover:bg-sand/30' : 'cursor-default opacity-40'"
                        :disabled="!day.inMonth"
                        type="button"
                        @click="openDay(day)"
                    >
                        <p class="text-xs font-medium">{{ day.number }}</p>
                        <ul class="flex flex-col gap-1">
                            <li
                                v-for="holiday in day.holidays"
                                :key="`${day.key}-holiday-${holiday.id}`"
                                class="truncate rounded-md bg-sand px-1.5 py-0.5 text-xs text-ink"
                            >
                                {{ holiday.name }}
                            </li>
                            <li
                                v-for="event in day.events"
                                :key="`${day.key}-${event.id}`"
                                class="truncate rounded-md px-1.5 py-0.5 text-xs"
                                :class="[
                                    event.type === 'leave' ? 'bg-danger/10 text-danger' : 'bg-pine/10 text-pine dark:bg-sand/15 dark:text-sand',
                                    event.status === 'pending' ? 'border border-dashed border-current' : '',
                                ]"
                            >
                                {{ event.type_label }} · {{ event.employee }}
                            </li>
                            <li
                                v-for="guest in day.guests"
                                :key="`${day.key}-guest-${guest.id}`"
                                class="flex items-center justify-between gap-1 rounded-md bg-ember/20 px-1.5 py-0.5 text-xs text-ink"
                            >
                                <span class="truncate">{{ guest.client }} · {{ guest.pets }}</span>
                                <span class="shrink-0">{{ guest.paid_label }}</span>
                            </li>
                        </ul>
                    </button>
                </div>
            </div>
        </section>
    </div>

    <div v-if="selectedDay" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4" @click.self="selectedDay = null">
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink" role="dialog" aria-modal="true" aria-labelledby="calendar-day-title">
            <div class="flex items-start justify-between gap-4">
                <h2 id="calendar-day-title" class="text-lg font-medium">{{ dayTitle(selectedDay) }}</h2>
                <button class="text-sm font-medium" type="button" @click="selectedDay = null">Close</button>
            </div>
            <p v-if="selectedDay.holidays.length === 0 && selectedDay.events.length === 0 && selectedDay.guests.length === 0" class="text-sm text-ink/60 dark:text-foam/60">Nothing on this day.</p>
            <ul v-else class="flex flex-col gap-3">
                <li v-for="holiday in selectedDay.holidays" :key="`day-holiday-${holiday.id}`" class="rounded-2xl border border-mist px-4 py-3 dark:border-white/10">
                    <p class="font-medium">{{ holiday.name }}</p>
                    <p class="text-sm text-ink/55 dark:text-foam/50">{{ holiday.type_label }} holiday</p>
                </li>
                <li v-for="event in selectedDay.events" :key="`day-event-${event.id}`" class="rounded-2xl border border-mist px-4 py-3 dark:border-white/10">
                    <p class="font-medium">{{ event.employee }}</p>
                    <p class="text-sm text-ink/55 dark:text-foam/50">{{ event.type_label }} · {{ event.status_label }}</p>
                </li>
                <li v-for="guest in selectedDay.guests" :key="`day-guest-${guest.id}`" class="rounded-2xl border border-mist px-4 py-3 dark:border-white/10">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="font-medium">{{ guest.client }}</p>
                        <p class="text-sm font-medium">{{ guest.paid_label }}</p>
                    </div>
                    <p class="text-sm text-ink/55 dark:text-foam/50">{{ guest.pets }} · {{ guest.pod_type }} · {{ guest.branch }}</p>
                    <p class="text-sm text-ink/55 dark:text-foam/50">Checked in</p>
                </li>
            </ul>
        </div>
    </div>

    <div v-if="requestsOpen && schedule" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4" @click.self="requestsOpen = false">
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col gap-4 overflow-y-auto rounded-2xl bg-foam p-6 dark:bg-ink" role="dialog" aria-modal="true" aria-labelledby="employee-requests-title">
            <div class="flex items-start justify-between gap-4">
                <h2 id="employee-requests-title" class="text-lg font-medium">Requests</h2>
                <button class="text-sm font-medium" type="button" @click="requestsOpen = false">Close</button>
            </div>
            <p v-if="schedule.requests.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No requests waiting.</p>
            <ul v-else class="flex flex-col gap-4">
                <li v-for="filing in schedule.requests" :key="filing.id" class="flex flex-col gap-3 rounded-2xl border border-mist p-4 dark:border-white/10">
                    <div class="flex flex-col gap-1">
                        <p class="font-medium">{{ filing.employee }}</p>
                        <p class="text-sm text-ink/55 dark:text-foam/50">{{ filing.employee_number }} · {{ filing.type_label }} · {{ filing.detail }}</p>
                        <p v-if="filing.reason" class="text-sm">{{ filing.reason }}</p>
                    </div>
                    <label class="sr-only" :for="`dashboard-review-note-${filing.id}`">Note</label>
                    <input
                        :id="`dashboard-review-note-${filing.id}`"
                        v-model="reviewNotes[filing.id]"
                        class="h-10 rounded-xl border border-mist bg-white px-3 text-sm dark:border-white/10 dark:bg-white/5"
                        maxlength="1000"
                        placeholder="Note"
                        type="text"
                    />
                    <div class="flex gap-2">
                        <button class="h-10 rounded-xl bg-pine px-3 text-sm font-medium text-foam" type="button" @click="reviewRequest(filing, 'approved')">
                            Accept
                        </button>
                        <button class="h-10 rounded-xl border border-mist px-3 text-sm font-medium dark:border-white/15" type="button" @click="reviewRequest(filing, 'rejected')">
                            Reject
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
