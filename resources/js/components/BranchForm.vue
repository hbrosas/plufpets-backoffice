<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    branch: {
        type: Object,
        default: null,
    },
    submitUrl: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'post',
    },
});

const form = useForm({
    name: props.branch?.name ?? '',
    registered_business_name: props.branch?.registered_business_name ?? '',
    tin: props.branch?.tin ?? '',
    code: props.branch?.code ?? '',
    address: props.branch?.address ?? '',
    phone: props.branch?.phone ?? '',
    is_active: props.branch?.is_active ?? true,
});

function submit() {
    const options = { preserveScroll: true };

    if (props.method === 'put') {
        form.put(props.submitUrl, options);
        return;
    }

    form.post(props.submitUrl, options);
}
</script>

<template>
    <form class="flex max-w-2xl flex-col gap-5" @submit.prevent="submit">
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium" for="name">Name</label>
            <input
                id="name"
                v-model="form.name"
                class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                required
                type="text"
            />
            <p v-if="form.errors.name" class="text-sm text-danger">{{ form.errors.name }}</p>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium" for="registered_business_name">Registered business name</label>
            <input
                id="registered_business_name"
                v-model="form.registered_business_name"
                class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                type="text"
            />
            <p v-if="form.errors.registered_business_name" class="text-sm text-danger">{{ form.errors.registered_business_name }}</p>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium" for="tin">TIN</label>
            <input
                id="tin"
                v-model="form.tin"
                class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                type="text"
            />
            <p v-if="form.errors.tin" class="text-sm text-danger">{{ form.errors.tin }}</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="code">Code</label>
                <input
                    id="code"
                    v-model="form.code"
                    class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                    type="text"
                />
                <p v-if="form.errors.code" class="text-sm text-danger">{{ form.errors.code }}</p>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium" for="phone">Phone</label>
                <input
                    id="phone"
                    v-model="form.phone"
                    class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                    type="text"
                />
                <p v-if="form.errors.phone" class="text-sm text-danger">{{ form.errors.phone }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium" for="address">Address</label>
            <input
                id="address"
                v-model="form.address"
                class="h-12 rounded-xl border border-mist bg-white px-4 text-base outline-none transition focus:border-pine focus:ring-2 focus:ring-sand/70 dark:border-white/10 dark:bg-white/5"
                type="text"
            />
            <p v-if="form.errors.address" class="text-sm text-danger">{{ form.errors.address }}</p>
        </div>

        <label class="flex items-center gap-3 text-sm font-medium">
            <input v-model="form.is_active" class="h-4 w-4 accent-pine" type="checkbox" />
            Active branch
        </label>

        <div class="flex flex-wrap items-center gap-3">
            <button
                class="h-12 rounded-xl bg-pine px-5 text-base font-medium text-foam transition hover:bg-pine/90 disabled:opacity-70"
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Save' }}
            </button>
            <Link class="text-sm font-medium text-pine dark:text-sand" href="/backoffice/shop/branches">Cancel</Link>
        </div>
    </form>
</template>
