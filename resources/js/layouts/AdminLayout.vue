<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FlashToast from '../components/FlashToast.vue';

const page = usePage();
const appName = computed(() => page.props.appName);
const employee = computed(() => page.props.employee);
const form = useForm({});
const sidebarOpen = ref(false);
const openGroups = ref({
    sales: false,
    reports: false,
    shop: false,
    inventory: false,
    pos: false,
    staff: false,
});
const permissions = computed(() => page.props.employee?.permissions ?? []);

function can(permission) {
    return permissions.value.includes(permission);
}

const navigation = [
    {
        id: 'sales',
        name: 'Sales',
        prefix: '/backoffice/sales',
        items: [
            { name: 'Overview', href: '/backoffice/sales', match: '/backoffice/sales', permission: 'sales.view' },
            { name: 'Receipts', href: '/backoffice/sales/receipts', match: '/backoffice/sales/receipts', permission: 'sales.view' },
            { name: 'Items', href: '/backoffice/sales/items', match: '/backoffice/sales/items', permission: 'sales.view' },
            { name: 'Shifts', href: '/backoffice/sales/shifts', match: '/backoffice/sales/shifts', permission: 'sales.view' },
        ],
    },
    {
        id: 'reports',
        name: 'Reports',
        prefix: '/backoffice/reports',
        items: [
            { name: 'Sales', href: '/backoffice/reports/sales', match: '/backoffice/reports/sales', permission: 'sales.view' },
            { name: 'Profit', href: '/backoffice/reports/profit', match: '/backoffice/reports/profit', permission: 'sales.view' },
            { name: 'Inventory', href: '/backoffice/reports/inventory', match: '/backoffice/reports/inventory', permission: 'inventory.view' },
            { name: 'Products', href: '/backoffice/reports/products', match: '/backoffice/reports/products', permission: 'sales.view' },
            { name: 'Purchasing', href: '/backoffice/reports/purchasing', match: '/backoffice/reports/purchasing', permission: 'inventory.purchase-orders' },
            { name: 'Staff', href: '/backoffice/reports/staff', match: '/backoffice/reports/staff', permission: 'sales.view' },
            { name: 'Reconciliation', href: '/backoffice/reports/reconciliation', match: '/backoffice/reports/reconciliation', permission: 'sales.view' },
            { name: 'Payables', href: '/backoffice/reports/payables', match: '/backoffice/reports/payables', permission: 'inventory.purchase-orders' },
            { name: 'Commissions', href: '/backoffice/reports/commissions', match: '/backoffice/reports/commissions', permission: 'dashboard.view' },
        ],
    },
    {
        id: 'shop',
        name: 'Shop Management',
        prefix: '/backoffice/shop',
        items: [
            { name: 'Catalog', href: '/backoffice/shop/catalog', match: '/backoffice/shop/catalog', permission: 'shop.catalog' },
            { name: 'Categories & Brands', href: '/backoffice/shop/catalog-meta', match: '/backoffice/shop/catalog-meta', permission: 'shop.catalog-meta' },
            { name: 'Modifiers', href: '/backoffice/shop/modifiers', match: '/backoffice/shop/modifiers', permission: 'shop.modifiers' },
            { name: 'Branches', href: '/backoffice/shop/branches', match: '/backoffice/shop/branches', permission: 'shop.branches' },
            { name: 'Settings', href: '/backoffice/shop/settings', match: '/backoffice/shop/settings', permission: 'shop.settings' },
        ],
    },
    {
        id: 'inventory',
        name: 'Inventory Management',
        prefix: '/backoffice/inventory',
        items: [
            { name: 'Inventory', href: '/backoffice/inventory', match: '/backoffice/inventory', permission: 'inventory.view' },
            { name: 'Purchase Orders', href: '/backoffice/inventory/purchase-orders', match: '/backoffice/inventory/purchase-orders', permission: 'inventory.purchase-orders' },
            { name: 'Delivery Orders', href: '/backoffice/inventory/delivery-orders', match: '/backoffice/inventory/delivery-orders', permission: 'inventory.delivery-orders' },
            { name: 'Stock Assignment', href: '/backoffice/inventory/stock-assignments', match: '/backoffice/inventory/stock-assignments', permission: 'inventory.stock-assignments' },
            { name: 'Suppliers', href: '/backoffice/inventory/suppliers', match: '/backoffice/inventory/suppliers', permission: 'inventory.suppliers' },
        ],
    },
    {
        id: 'pos',
        name: 'POS Management',
        prefix: '/backoffice/pos',
        items: [
            { name: 'POS Categories', href: '/backoffice/pos/categories', match: '/backoffice/pos/categories', permission: 'pos.categories' },
            { name: 'SMS History', href: '/backoffice/pos/sms-history', match: '/backoffice/pos/sms-history', permission: 'pos.sms-history' },
            { name: 'POS Settings', href: '/backoffice/pos/settings', match: '/backoffice/pos/settings', permission: 'pos.settings' },
        ],
    },
    {
        id: 'staff',
        name: 'Staff',
        prefix: '/backoffice/staff',
        items: [
            { name: 'Staff', href: '/backoffice/staff', match: '/backoffice/staff', permission: 'staff.manage' },
            { name: 'Employees Records', href: '/backoffice/staff/records', match: '/backoffice/staff/records', permission: 'staff.manage' },
            { name: 'Holidays', href: '/backoffice/staff/holidays', match: '/backoffice/staff/holidays', permission: 'staff.manage' },
            { name: 'Roles', href: '/backoffice/staff/roles', match: '/backoffice/staff/roles', permission: 'roles.manage' },
        ],
    },
];

const navigationGroups = computed(() => navigation
    .map((group) => ({
        ...group,
        items: group.items.filter((item) => can(item.permission)),
    }))
    .filter((group) => group.items.length > 0));

const currentPath = computed(() => page.url.split('?')[0]);

const backLink = computed(() => {
    const path = currentPath.value;
    const links = [
        [/^\/backoffice\/reports\//, '/backoffice/dashboard', 'Back to overview'],
        [/^\/backoffice\/sales\/receipts\/.+/, '/backoffice/sales/receipts', 'Back to receipts'],
        [/^\/backoffice\/inventory\/products\/.+/, '/backoffice/inventory', 'Back to inventory'],
        [/^\/backoffice\/inventory\/purchase-orders\/.+/, '/backoffice/inventory/purchase-orders', 'Back to purchase orders'],
        [/^\/backoffice\/inventory\/delivery-orders\/.+/, '/backoffice/inventory/delivery-orders', 'Back to delivery orders'],
        [/^\/backoffice\/shop\/catalog\/.+/, '/backoffice/shop/catalog', 'Back to catalog'],
        [/^\/backoffice\/staff\/\d+\/record$/, '/backoffice/staff/records', 'Back to records'],
    ];
    const match = links.find(([pattern]) => pattern.test(path));

    return match ? { href: match[1], label: match[2] } : null;
});

const pageTitle = computed(() => {
    if (/^\/backoffice\/staff\/\d+\/record$/.test(currentPath.value)) {
        return 'Employee record';
    }

    const titles = [
        ['/backoffice/staff/records', 'Employees Records'],
        ['/backoffice/staff/holidays', 'Holidays'],
        ['/backoffice/staff/roles', 'Roles'],
        ['/backoffice/staff', 'Staff'],
        ['/backoffice/reports/sales', 'Sales report'],
        ['/backoffice/reports/profit', 'Profit'],
        ['/backoffice/reports/inventory', 'Inventory'],
        ['/backoffice/reports/products', 'Products'],
        ['/backoffice/reports/purchasing', 'Purchasing'],
        ['/backoffice/reports/staff', 'Staff report'],
        ['/backoffice/reports/reconciliation', 'Reconciliation'],
        ['/backoffice/reports/payables', 'Payables'],
        ['/backoffice/reports/commissions', 'Commissions'],
        ['/backoffice/sales/receipts', 'Receipts'],
        ['/backoffice/sales/items', 'Sold items'],
        ['/backoffice/sales/shifts', 'Shifts'],
        ['/backoffice/sales', 'Sales'],
        ['/backoffice/pos/sms-history', 'SMS History'],
        ['/backoffice/pos/settings', 'POS Settings'],
        ['/backoffice/pos/categories', 'POS Categories'],
        ['/backoffice/inventory/purchase-orders', 'Purchase Orders'],
        ['/backoffice/inventory/delivery-orders', 'Delivery Orders'],
        ['/backoffice/inventory/stock-assignments', 'Stock Assignment'],
        ['/backoffice/inventory/suppliers', 'Suppliers'],
        ['/backoffice/inventory', 'Inventory'],
        ['/backoffice/shop/settings', 'Settings'],
        ['/backoffice/shop/branches', 'Branches'],
        ['/backoffice/shop/catalog-meta', 'Categories & Brands'],
        ['/backoffice/shop/modifiers', 'Modifiers'],
        ['/backoffice/shop/catalog', 'Catalog'],
        ['/backoffice/business', 'Business'],
    ];

    const match = titles.find(
        ([prefix]) => currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`),
    );

    return match ? match[1] : 'Overview';
});

function isCurrent(item) {
    if (item.match === '/backoffice/inventory' || item.match === '/backoffice/sales') {
        return currentPath.value === item.match;
    }

    if (item.match === '/backoffice/staff') {
        return currentPath.value === item.match;
    }

    if (item.match === '/backoffice/staff/records') {
        return currentPath.value === item.match || /^\/backoffice\/staff\/\d+\/record$/.test(currentPath.value);
    }

    if (item.match) {
        return currentPath.value === item.match || currentPath.value.startsWith(`${item.match}/`);
    }

    return item.href !== undefined && currentPath.value === item.href;
}

function isGroupCurrent(group) {
    return group.items.some((item) => isCurrent(item));
}

function toggleGroup(id) {
    openGroups.value[id] = !openGroups.value[id];
}

function logout() {
    form.post('/backoffice/logout');
}

watch(
    currentPath,
    (path) => {
        sidebarOpen.value = false;

        navigationGroups.value.forEach((group) => {
            if (path === group.prefix || path.startsWith(`${group.prefix}/`)) {
                openGroups.value[group.id] = true;
            }
        });
    },
    { immediate: true },
);
</script>

<template>
    <div class="min-h-screen bg-mist/50 lg:flex dark:bg-ink">
        <FlashToast />
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-ink/50 lg:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-50 flex h-dvh w-72 shrink-0 flex-col overflow-hidden bg-pine text-foam transition-transform duration-200 lg:sticky lg:top-0 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex shrink-0 items-center gap-3 px-6 py-6">
                <img
                    v-if="page.props.businessLogoUrl"
                    :src="page.props.businessLogoUrl"
                    alt=""
                    class="h-10 w-10 rounded-xl object-contain"
                />
                <span v-else class="flex h-10 w-10 items-center justify-center rounded-xl bg-sand text-sm font-semibold text-pine">PP</span>
                <div class="flex min-w-0 flex-col gap-0.5">
                    <p class="truncate text-sm font-medium">{{ appName }}</p>
                    <p class="text-xs text-foam/60">Admin dashboard</p>
                </div>
            </div>

            <nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-4" aria-label="Admin">
                <Link
                    v-if="can('pos.access')"
                    href="/backoffice/pos"
                    class="mb-1 flex items-center gap-2 rounded-xl bg-sand px-3 py-2.5 text-sm font-semibold text-pine transition hover:bg-sand/90"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3.5 3.75A.75.75 0 0 1 4.25 3h11.5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75v-3.5ZM4 9.5h12v6.75a.75.75 0 0 1-.75.75H4.75a.75.75 0 0 1-.75-.75V9.5Zm3 2.25a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5H7Z" />
                    </svg>
                    Open POS
                </Link>
                <Link
                    v-if="can('dashboard.view')"
                    href="/backoffice/dashboard"
                    class="rounded-xl px-3 py-2.5 text-sm font-medium transition"
                    :class="
                        currentPath === '/backoffice/dashboard'
                            ? 'bg-foam/15 text-foam'
                            : 'text-foam/70 hover:bg-foam/10 hover:text-foam'
                    "
                >
                    Overview
                </Link>
                <Link
                    v-if="can('business.manage')"
                    href="/backoffice/business"
                    class="rounded-xl px-3 py-2.5 text-sm font-medium transition"
                    :class="
                        currentPath === '/backoffice/business'
                            ? 'bg-foam/15 text-foam'
                            : 'text-foam/70 hover:bg-foam/10 hover:text-foam'
                    "
                >
                    Business
                </Link>

                <div v-for="group in navigationGroups" :key="group.id" class="flex flex-col gap-1">
                    <button
                        class="flex items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-medium transition"
                        :class="
                            isGroupCurrent(group) && !openGroups[group.id]
                                ? 'bg-foam/15 text-foam'
                                : isGroupCurrent(group)
                                  ? 'text-foam hover:bg-foam/10'
                                  : 'text-foam/70 hover:bg-foam/10 hover:text-foam'
                        "
                        type="button"
                        :aria-expanded="openGroups[group.id]"
                        :aria-controls="`${group.id}-submenu`"
                        @click="toggleGroup(group.id)"
                    >
                        {{ group.name }}
                        <svg
                            class="h-4 w-4 shrink-0 transition-transform"
                            :class="openGroups[group.id] ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>

                    <div
                        v-if="openGroups[group.id]"
                        :id="`${group.id}-submenu`"
                        class="flex flex-col gap-1 pl-3"
                    >
                        <Link
                            v-for="item in group.items"
                            :key="item.name"
                            :href="item.href"
                            class="rounded-xl px-3 py-2 text-sm font-medium transition"
                            :class="
                                isCurrent(item)
                                    ? 'bg-foam/15 text-foam'
                                    : 'text-foam/65 hover:bg-foam/10 hover:text-foam'
                            "
                        >
                            {{ item.name }}
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="flex shrink-0 flex-col gap-3 border-t border-foam/10 px-4 py-5">
                <div class="flex flex-col gap-0.5 px-3">
                    <p class="truncate text-sm font-medium">{{ employee?.name }}</p>
                    <p class="text-xs text-foam/55">#{{ employee?.employee_number }}</p>
                    <p v-if="employee?.role" class="text-xs text-foam/55">{{ employee.role }}</p>
                </div>

                <form @submit.prevent="logout">
                    <button
                        class="w-full rounded-xl border border-foam/15 px-3 py-2.5 text-left text-sm font-medium text-foam/80 transition hover:border-sand hover:text-sand disabled:opacity-70"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Sign out
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-mist bg-foam/90 px-4 py-3 backdrop-blur-md lg:px-8 dark:border-white/10 dark:bg-ink/90">
                <div class="flex items-center gap-3">
                    <button
                        class="rounded-xl border border-mist p-2 lg:hidden dark:border-white/15"
                        type="button"
                        :aria-expanded="sidebarOpen"
                        aria-controls="admin-sidebar"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <span class="sr-only">Open navigation</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path
                                fill-rule="evenodd"
                                d="M3 5.75A.75.75 0 0 1 3.75 5h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 5.75Zm0 4.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 10.25Zm0 4.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                    <Link
                        v-if="backLink"
                        :href="backLink.href"
                        class="inline-flex items-center gap-1.5 text-sm font-medium"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path
                                fill-rule="evenodd"
                                d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        {{ backLink.label }}
                    </Link>
                    <p v-else class="text-sm font-medium">{{ pageTitle }}</p>
                </div>

                <div id="admin-header-actions" class="flex min-w-0 flex-wrap items-center justify-end gap-3" />
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <slot />
            </main>
        </div>
    </div>

</template>
