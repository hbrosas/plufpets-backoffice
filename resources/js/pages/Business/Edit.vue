<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FilePicker from '../../components/FilePicker.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    business: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.business.name ?? '',
    address: props.business.address ?? '',
    phone: props.business.phone ?? '',
    email: props.business.email ?? '',
    tin: props.business.tin ?? '',
    daily_sales_target: props.business.daily_sales_target ?? '',
    logo: null,
    favicon: null,
    remove_logo: false,
    remove_favicon: false,
});

const logoPreview = ref(props.business.logo_url);
const faviconPreview = ref(props.business.favicon_url);

function onLogoChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = file ? URL.createObjectURL(file) : props.business.logo_url;
}

function clearLogo() {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
}

function onFaviconChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.favicon = file;
    form.remove_favicon = false;
    faviconPreview.value = file ? URL.createObjectURL(file) : props.business.favicon_url;
}

function clearFavicon() {
    form.favicon = null;
    form.remove_favicon = true;
    faviconPreview.value = null;
}

function submit() {
    form.post('/backoffice/business', {
        preserveScroll: true,
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Business" />

    <form class="mx-auto flex w-full max-w-2xl flex-col gap-6" @submit.prevent="submit">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-medium">Business information</h1>
            <p class="text-sm text-ink/70 dark:text-foam/70">
                Payroll, purchase orders, and other printed documents use this name, address, and logo.
            </p>
        </div>

        <div class="flex flex-col gap-5 rounded-2xl border border-mist bg-white p-6 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="business-name">Business name</label>
                <input
                    id="business-name"
                    v-model="form.name"
                    class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                    required
                    type="text"
                />
                <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="business-address">Address</label>
                <textarea
                    id="business-address"
                    v-model="form.address"
                    class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                    rows="3"
                />
                <p v-if="form.errors.address" class="text-sm text-danger">{{ form.errors.address }}</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="business-phone">Phone</label>
                    <input
                        id="business-phone"
                        v-model="form.phone"
                        class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                        type="text"
                    />
                    <p v-if="form.errors.phone" class="text-sm text-danger">{{ form.errors.phone }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="business-email">Email</label>
                    <input
                        id="business-email"
                        v-model="form.email"
                        class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                        type="email"
                    />
                    <p v-if="form.errors.email" class="text-sm text-danger">{{ form.errors.email }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="business-tin">TIN</label>
                <input
                    id="business-tin"
                    v-model="form.tin"
                    class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                    type="text"
                />
                <p v-if="form.errors.tin" class="text-sm text-danger">{{ form.errors.tin }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="business-target">Daily sales target</label>
                <input
                    id="business-target"
                    v-model="form.daily_sales_target"
                    class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    type="number"
                />
                <p class="text-sm text-ink/55 dark:text-foam/50">The overview compares today's sales with this amount.</p>
                <p v-if="form.errors.daily_sales_target" class="text-sm text-danger">{{ form.errors.daily_sales_target }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-sm font-medium">Logo</p>
                <FilePicker
                    id="business-logo"
                    button-label="Choose logo"
                    :file-name="form.logo?.name ?? ''"
                    :hint="logoPreview && !form.logo ? 'Using current logo' : 'JPG, PNG, or WebP · no file chosen'"
                    @change="onLogoChange"
                />
                <p v-if="form.errors.logo" class="text-sm text-danger">{{ form.errors.logo }}</p>
                <div v-if="logoPreview" class="flex items-center gap-3">
                    <img :src="logoPreview" alt="" class="h-16 w-auto rounded-xl object-contain" />
                    <button class="text-sm font-medium text-danger" type="button" @click="clearLogo">Remove logo</button>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-sm font-medium">Favicon</p>
                <FilePicker
                    id="business-favicon"
                    button-label="Choose favicon"
                    :file-name="form.favicon?.name ?? ''"
                    :hint="faviconPreview && !form.favicon ? 'Using current favicon' : 'Square PNG, JPG, or WebP · no file chosen'"
                    @change="onFaviconChange"
                />
                <p v-if="form.errors.favicon" class="text-sm text-danger">{{ form.errors.favicon }}</p>
                <div v-if="faviconPreview" class="flex items-center gap-3">
                    <img :src="faviconPreview" alt="" class="h-10 w-10 rounded-lg object-contain" />
                    <button class="text-sm font-medium text-danger" type="button" @click="clearFavicon">Remove favicon</button>
                </div>
            </div>
        </div>

        <button
            class="h-12 rounded-xl bg-pine px-5 text-sm font-medium text-foam transition hover:bg-pine/90 disabled:opacity-60"
            type="submit"
            :disabled="form.processing"
        >
            {{ form.processing ? 'Saving…' : 'Save business information' }}
        </button>
    </form>
</template>
