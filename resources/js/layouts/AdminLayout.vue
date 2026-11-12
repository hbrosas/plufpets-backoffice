<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import FlashToast from '../components/FlashToast.vue';

const page = usePage();
const appName = computed(() => page.props.appName);
const employee = computed(() => page.props.employee);
const smsCreditBalance = computed(() => page.props.smsCreditBalance);
const form = useForm({});
const desktopNavQuery = '(min-width: 1280px)';
const sidebarOpen = ref(window.matchMedia(desktopNavQuery).matches);
const openGroups = ref({
    sales: false,
    reports: false,
    shop: false,
    inventory: false,
    pos: false,
    business: false,
    accounts: false,
    calendar: false,
    employees: false,
});
const permissions = computed(() => page.props.employee?.permissions ?? []);

function can(permission) {
    return permissions.value.includes(permission);
}

const menu = [
    { key: 'pos-launch', type: 'link', name: 'Open POS', href: '/backoffice/pos', permission: 'pos.access', emphasis: true },
    { key: 'dashboard', type: 'link', name: 'Dashboard', href: '/backoffice/dashboard', match: '/backoffice/dashboard', permission: 'dashboard.view' },
    { key: 'orders', type: 'link', name: 'Manage Orders', href: '/backoffice/shop/orders', match: '/backoffice/shop/orders', permission: 'shop.orders' },
    { key: 'hotel', type: 'link', name: 'Manage Hotel', href: '/backoffice/hotel', match: '/backoffice/hotel', permission: 'hotel.manage' },
    { key: 'my-commissions', type: 'link', name: 'My Commissions', href: '/backoffice/me/commissions', match: '/backoffice/me/commissions', requiresRecord: true },
    { key: 'my-payroll', type: 'link', name: 'My Payroll', href: '/backoffice/me/payroll', match: '/backoffice/me/payroll', requiresRecord: true },
    { key: 'my-employee-201', type: 'link', name: 'My Employee 201', href: '/backoffice/me/employee-201', match: '/backoffice/me/employee-201', requiresRecord: true },
    { key: 'divider-operations', type: 'divider' },
    {
        key: 'inventory',
        type: 'group',
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
        key: 'shop',
        type: 'group',
        id: 'shop',
        name: 'Product Management',
        prefix: '/backoffice/shop',
        items: [
            { name: 'Catalog', href: '/backoffice/shop/catalog', match: '/backoffice/shop/catalog', permission: 'shop.catalog' },
            { name: 'Categories & Brands', href: '/backoffice/shop/catalog-meta', match: '/backoffice/shop/catalog-meta', permission: 'shop.catalog-meta' },
            { name: 'Modifiers', href: '/backoffice/shop/modifiers', match: '/backoffice/shop/modifiers', permission: 'shop.modifiers' },
        ],
    },
    {
        key: 'storefront',
        type: 'group',
        id: 'storefront',
        name: 'Shop Management',
        prefix: '/backoffice/shop/banner',
        items: [
            { name: 'Banner', href: '/backoffice/shop/banner', match: '/backoffice/shop/banner', permission: 'shop.settings' },
            { name: 'Promotional Text', href: '/backoffice/shop/promotion', match: '/backoffice/shop/promotion', permission: 'shop.settings' },
            { name: 'Settings', href: '/backoffice/shop/settings', match: '/backoffice/shop/settings', permission: 'shop.settings' },
        ],
    },
    {
        key: 'pos',
        type: 'group',
        id: 'pos',
        name: 'POS Management',
        prefix: '/backoffice/pos',
        items: [
            { name: 'POS Categories', href: '/backoffice/pos/categories', match: '/backoffice/pos/categories', permission: 'pos.categories' },
            { name: 'POS Devices', href: '/backoffice/pos/settings', match: '/backoffice/pos/settings', permission: 'pos.settings' },
        ],
    },
    {
        key: 'sms',
        type: 'group',
        id: 'sms',
        name: 'SMS Management',
        prefix: '/backoffice/sms',
        items: [
            { name: 'SMS History', href: '/backoffice/pos/sms-history', match: '/backoffice/pos/sms-history', permission: 'pos.sms-history' },
            { name: 'SMS Templates', href: '/backoffice/sms/templates', match: '/backoffice/sms/templates', permission: 'pos.settings' },
        ],
    },
    { key: 'divider-sales', type: 'divider' },
    {
        key: 'sales',
        type: 'group',
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
        key: 'reports',
        type: 'group',
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
    { key: 'divider-admin', type: 'divider' },
    {
        key: 'business',
        type: 'group',
        id: 'business',
        name: 'My Business',
        prefix: '/backoffice/business',
        items: [
            { name: 'Business Information', href: '/backoffice/business', match: '/backoffice/business', permission: 'business.manage' },
            { name: 'Branches', href: '/backoffice/shop/branches', match: '/backoffice/shop/branches', permission: 'shop.branches' },
        ],
    },
    {
        key: 'accounts',
        type: 'group',
        id: 'accounts',
        name: 'User Accounts',
        prefix: '/backoffice/staff',
        items: [
            { name: 'Users', href: '/backoffice/staff', match: '/backoffice/staff', permission: 'staff.manage' },
            { name: 'Roles', href: '/backoffice/staff/roles', match: '/backoffice/staff/roles', permission: 'roles.manage' },
        ],
    },
    {
        key: 'calendar',
        type: 'group',
        id: 'calendar',
        name: 'Business Calendar',
        prefix: '/backoffice/staff/holidays',
        items: [
            { name: 'Holidays', href: '/backoffice/staff/holidays', match: '/backoffice/staff/holidays', permission: 'staff.manage' },
        ],
    },
    {
        key: 'employees',
        type: 'group',
        id: 'employees',
        name: 'Employee Management',
        prefix: '/backoffice/staff/records',
        items: [
            { name: 'Employees', href: '/backoffice/staff/records', match: '/backoffice/staff/records', permission: 'staff.manage' },
            { name: 'Filed Requests', href: '/backoffice/staff/approvals', match: '/backoffice/staff/approvals', permission: 'staff.manage' },
            { name: 'Run Payroll', href: '/backoffice/staff/payroll', match: '/backoffice/staff/payroll', permission: 'staff.manage' },
        ],
    },
];

const visibleMenu = computed(() => {
    const entries = menu.flatMap((entry) => {
        if (entry.type === 'divider') {
            return [entry];
        }

        if (entry.type === 'link') {
            if (entry.requiresRecord && !employee.value?.has_record) {
                return [];
            }

            return !entry.permission || can(entry.permission) ? [entry] : [];
        }

        const items = entry.items.filter((item) => !item.permission || can(item.permission));

        return items.length > 0 ? [{ ...entry, items }] : [];
    });
    const cleaned = [];

    entries.forEach((entry) => {
        if (entry.type === 'divider' && (cleaned.length === 0 || cleaned.at(-1).type === 'divider')) {
            return;
        }

        cleaned.push(entry);
    });

    if (cleaned.at(-1)?.type === 'divider') {
        cleaned.pop();
    }

    return cleaned;
});

const currentPath = computed(() => page.url.split('?')[0]);

const backLink = computed(() => {
    const path = currentPath.value;
    const links = [
        [/^\/backoffice\/reports\//, '/backoffice/dashboard', 'Back to dashboard'],
        [/^\/backoffice\/inventory\/products\/.+/, '/backoffice/inventory', 'Back to inventory'],
        [/^\/backoffice\/inventory\/purchase-orders\/.+/, '/backoffice/inventory/purchase-orders', 'Back to purchase orders'],
        [/^\/backoffice\/inventory\/suppliers\/.+/, '/backoffice/inventory/suppliers', 'Back to suppliers'],
        [/^\/backoffice\/inventory\/delivery-orders\/.+/, '/backoffice/inventory/delivery-orders', 'Back to delivery orders'],
        [/^\/backoffice\/shop\/orders\/.+/, '/backoffice/shop/orders', 'Back to manage orders'],
        [/^\/backoffice\/staff\/\d+\/record$/, '/backoffice/staff/records', 'Back to employees'],
    ];
    const match = links.find(([pattern]) => pattern.test(path));

    return match ? { href: match[1], label: match[2] } : null;
});

const pageTitle = computed(() => {
    if (/^\/backoffice\/staff\/\d+\/record$/.test(currentPath.value)) {
        return 'Employee record';
    }

    if (/^\/backoffice\/inventory\/suppliers\/\d+$/.test(currentPath.value)) {
        return 'Supplier';
    }

    const titles = [
        ['/backoffice/me/commissions', 'My Commissions'],
        ['/backoffice/me/employee-201', 'My Employee 201'],
        ['/backoffice/me/payroll', 'My Payroll'],
        ['/backoffice/staff/payroll', 'Run Payroll'],
        ['/backoffice/staff/approvals', 'Filed Requests'],
        ['/backoffice/staff/records', 'Employees'],
        ['/backoffice/staff/holidays', 'Holidays'],
        ['/backoffice/staff/roles', 'Roles'],
        ['/backoffice/staff', 'Users'],
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
        ['/backoffice/sms/templates', 'SMS Templates'],
        ['/backoffice/pos/sms-history', 'SMS History'],
        ['/backoffice/pos/settings', 'POS Devices'],
        ['/backoffice/pos/categories', 'POS Categories'],
        ['/backoffice/inventory/purchase-orders', 'Purchase Orders'],
        ['/backoffice/inventory/delivery-orders', 'Delivery Orders'],
        ['/backoffice/inventory/stock-assignments', 'Stock Assignment'],
        ['/backoffice/inventory/suppliers', 'Suppliers'],
        ['/backoffice/inventory', 'Inventory'],
        ['/backoffice/shop/orders', 'Manage Orders'],
        ['/backoffice/hotel', 'Manage Hotel'],
        ['/backoffice/shop/promotion', 'Promotional Text'],
        ['/backoffice/shop/banner', 'Banner'],
        ['/backoffice/shop/settings', 'Settings'],
        ['/backoffice/shop/branches', 'Branches'],
        ['/backoffice/shop/catalog-meta', 'Categories & Brands'],
        ['/backoffice/shop/modifiers', 'Modifiers'],
        ['/backoffice/shop/catalog', 'Catalog'],
        ['/backoffice/business', 'Business Information'],
        ['/backoffice/dashboard', 'Dashboard'],
    ];

    const match = titles.find(
        ([prefix]) => currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`),
    );

    return match ? match[1] : 'Dashboard';
});

function navItemClass(active) {
    return active
        ? 'bg-navy text-white'
        : 'text-black/70 hover:bg-cream hover:text-black';
}

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

function closeSidebarOnNarrowScreens() {
    if (!window.matchMedia(desktopNavQuery).matches) {
        sidebarOpen.value = false;
    }
}

onMounted(() => {
    window.matchMedia(desktopNavQuery).addEventListener('change', closeSidebarOnNarrowScreens);
});

onUnmounted(() => {
    window.matchMedia(desktopNavQuery).removeEventListener('change', closeSidebarOnNarrowScreens);
});

watch(
    currentPath,
    (path) => {
        closeSidebarOnNarrowScreens();

        visibleMenu.value.forEach((entry) => {
            if (entry.type !== 'group') {
                return;
            }

            if (entry.id === 'shop' && (path.startsWith('/backoffice/shop/orders') || path.startsWith('/backoffice/shop/branches') || path.startsWith('/backoffice/shop/banner') || path.startsWith('/backoffice/shop/promotion') || path.startsWith('/backoffice/shop/settings'))) {
                return;
            }

            if (entry.id === 'storefront' && (path.startsWith('/backoffice/shop/banner') || path.startsWith('/backoffice/shop/promotion') || path.startsWith('/backoffice/shop/settings'))) {
                openGroups.value.storefront = true;

                return;
            }

            if (entry.id === 'business' && path.startsWith('/backoffice/shop/branches')) {
                openGroups.value.business = true;

                return;
            }

            if (entry.id === 'accounts') {
                if (path === '/backoffice/staff' || path.startsWith('/backoffice/staff/roles')) {
                    openGroups.value.accounts = true;
                }

                return;
            }

            if (entry.id === 'calendar') {
                if (path.startsWith('/backoffice/staff/holidays')) {
                    openGroups.value.calendar = true;
                }

                return;
            }

            if (entry.id === 'employees') {
                if (path.startsWith('/backoffice/staff/records') || path.startsWith('/backoffice/staff/approvals') || path.startsWith('/backoffice/staff/payroll') || /^\/backoffice\/staff\/\d+\/record$/.test(path)) {
                    openGroups.value.employees = true;
                }

                return;
            }

            if (entry.id === 'pos' && (path === '/backoffice/pos' || path.startsWith('/backoffice/pos/sms-history'))) {
                return;
            }

            if (entry.id === 'sms' && path.startsWith('/backoffice/pos/sms-history')) {
                openGroups.value.sms = true;

                return;
            }

            if (path === entry.prefix || path.startsWith(`${entry.prefix}/`)) {
                openGroups.value[entry.id] = true;
            }
        });
    },
    { immediate: true },
);
</script>

<template>
    <div class="admin-shell min-h-screen bg-cream text-black xl:flex">
        <FlashToast />
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/40 xl:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-50 flex h-dvh w-72 shrink-0 flex-col overflow-hidden border-r border-black/10 bg-white text-black transition-[transform,width] duration-200 xl:sticky xl:top-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full xl:w-0 xl:translate-x-0 xl:border-r-0'"
            :inert="!sidebarOpen"
        >
            <div class="flex shrink-0 items-center gap-3 px-6 py-6">
                <img
                    v-if="page.props.businessLogoUrl"
                    :src="page.props.businessLogoUrl"
                    alt=""
                    class="h-10 w-10 rounded-xl bg-white object-contain"
                />
                <span v-else class="flex h-10 w-10 items-center justify-center rounded-xl bg-ember text-sm font-semibold text-black">PP</span>
                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <p class="truncate text-sm font-medium">{{ appName }}</p>
                    <p class="text-xs text-black/60">Backoffice</p>
                </div>
                <button
                    class="shrink-0 rounded-xl border border-black/15 p-2 xl:hidden"
                    type="button"
                    @click="sidebarOpen = false"
                >
                    <span class="sr-only">Close navigation</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </div>

            <nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-4" aria-label="Admin">
                <template v-for="entry in visibleMenu" :key="entry.key">
                    <div v-if="entry.type === 'divider'" class="my-2 border-t border-black/10" role="separator" />

                    <Link
                        v-else-if="entry.emphasis"
                        :href="entry.href"
                        class="mb-1 flex items-center gap-2 rounded-xl bg-ember px-3 py-2.5 text-sm font-semibold text-black transition hover:bg-ember/90"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M3.5 3.75A.75.75 0 0 1 4.25 3h11.5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75v-3.5ZM4 9.5h12v6.75a.75.75 0 0 1-.75.75H4.75a.75.75 0 0 1-.75-.75V9.5Zm3 2.25a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5H7Z" />
                        </svg>
                        {{ entry.name }}
                    </Link>

                    <Link
                        v-else-if="entry.type === 'link'"
                        :href="entry.href"
                        class="rounded-xl px-3 py-2.5 text-sm font-medium transition"
                        :class="navItemClass(isCurrent(entry))"
                    >
                        {{ entry.name }}
                    </Link>

                    <div v-else class="flex flex-col gap-1">
                        <button
                            class="flex items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-medium transition"
                            :class="navItemClass(isGroupCurrent(entry) && !openGroups[entry.id])"
                            type="button"
                            :aria-expanded="openGroups[entry.id]"
                            :aria-controls="`${entry.id}-submenu`"
                            @click="toggleGroup(entry.id)"
                        >
                            {{ entry.name }}
                            <svg
                                class="h-4 w-4 shrink-0 transition-transform"
                                :class="openGroups[entry.id] ? 'rotate-180' : ''"
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
                            v-if="openGroups[entry.id]"
                            :id="`${entry.id}-submenu`"
                            class="flex flex-col gap-1 pl-3"
                        >
                            <Link
                                v-for="item in entry.items"
                                :key="item.name"
                                :href="item.href"
                                class="rounded-xl px-3 py-2 text-sm font-medium transition"
                                :class="navItemClass(isCurrent(item))"
                            >
                                {{ item.name }}
                            </Link>
                        </div>
                    </div>
                </template>
            </nav>

            <div class="flex shrink-0 flex-col gap-3 border-t border-black/10 px-4 py-5">
                <div v-if="smsCreditBalance" class="flex items-center justify-between gap-3 rounded-xl border border-black/10 px-3 py-2.5">
                    <p class="text-xs font-medium text-black/60">SMS credits</p>
                    <p class="text-sm font-semibold tabular-nums">{{ smsCreditBalance.label }}</p>
                </div>
                <div class="flex flex-col gap-0.5 px-3">
                    <p class="truncate text-sm font-medium">{{ employee?.name }}</p>
                    <p class="text-xs text-black/55">#{{ employee?.employee_number }}</p>
                    <p v-if="employee?.role" class="text-xs text-black/55">{{ employee.role }}</p>
                </div>

                <form @submit.prevent="logout">
                    <button
                        class="w-full rounded-xl border border-black/15 px-3 py-2.5 text-left text-sm font-medium text-black transition hover:border-navy hover:text-navy disabled:opacity-70"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Sign out
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex h-dvh min-w-0 flex-1 flex-col overflow-hidden">
            <header class="sticky top-0 z-30 shrink-0 border-b border-black/10 bg-white">
                <div class="flex min-w-0 flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                    <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                        <button
                            class="shrink-0 rounded-xl border border-black/15 p-2"
                            type="button"
                            :aria-expanded="sidebarOpen"
                            aria-controls="admin-sidebar"
                            @click="sidebarOpen = !sidebarOpen"
                        >
                            <span class="sr-only">{{ sidebarOpen ? 'Close navigation' : 'Open navigation' }}</span>
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
                            class="inline-flex max-w-[45%] min-w-0 items-center gap-1.5 text-sm font-medium sm:max-w-none"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <span class="truncate">{{ backLink.label }}</span>
                        </Link>
                        <div id="admin-header-tabs" class="peer order-2 flex min-w-0 flex-1 items-center overflow-x-auto empty:hidden [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" />
                        <h1
                            class="order-1 min-w-0 max-w-[9rem] shrink truncate text-lg font-medium sm:max-w-[14rem]"
                            :class="backLink ? 'peer-[:empty]:hidden' : ''"
                        >
                            {{ pageTitle }}
                        </h1>
                    </div>

                    <div id="admin-header-actions" class="flex w-full min-w-0 flex-wrap items-center gap-2 empty:hidden lg:w-auto lg:justify-end lg:gap-3" />
                </div>
                <div id="admin-header-subnav" class="empty:hidden overflow-x-auto border-t border-black/10 px-4 py-2 [scrollbar-width:none] lg:px-8 [&::-webkit-scrollbar]:hidden" />
            </header>

            <main class="flex min-h-0 flex-1 flex-col overflow-auto px-4 py-4 lg:px-6 lg:py-4">
                <slot />
            </main>
        </div>
    </div>

</template>
