<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

defineOptions({
    layout: AdminLayout,
});

const props = defineProps({
    templates: {
        type: Array,
        required: true,
    },
    deliveryTemplates: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const tab = ref('pos');
const editingId = ref(null);
const templateForm = useForm({
    name: '',
    body: '',
});

function editTemplate(template) {
    editingId.value = template.id;
    templateForm.name = template.name;
    templateForm.body = template.body;
    templateForm.clearErrors();
    tab.value = 'pos';
}

function resetTemplateForm() {
    editingId.value = null;
    templateForm.reset();
    templateForm.clearErrors();
}

function saveTemplate() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetTemplateForm();
        },
    };

    if (editingId.value) {
        templateForm.put(`/backoffice/sms/templates/${editingId.value}`, options);

        return;
    }

    templateForm.post('/backoffice/sms/templates', options);
}

function deleteTemplate(template) {
    if (!window.confirm(`Delete ${template.name}?`)) {
        return;
    }

    router.delete(`/backoffice/sms/templates/${template.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingId.value === template.id) {
                resetTemplateForm();
            }
        },
    });
}

function saveDeliveryTemplate(template) {
    router.put(`/backoffice/pos/order-texts/${template.id}`, { body: template.body }, { preserveScroll: true });
}
</script>

<template>
    <Head title="SMS Templates" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <p v-if="flash.status" class="rounded-xl border border-pine/20 bg-foam px-4 py-3 text-sm text-pine dark:border-sand/30 dark:bg-ink dark:text-sand">
            {{ flash.status }}
        </p>

        <Teleport defer to="#admin-header-tabs">
        <div class="flex w-max gap-2" role="tablist" aria-label="SMS templates">
            <button
                id="sms-tab-pos"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="tab === 'pos' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="tab === 'pos'"
                aria-controls="sms-panel-pos"
                @click="tab = 'pos'"
            >
                POS Templates
            </button>
            <button
                id="sms-tab-delivery"
                class="h-10 shrink-0 rounded-xl px-4 text-sm font-medium whitespace-nowrap"
                :class="tab === 'delivery' ? 'bg-pine text-foam' : 'border border-mist dark:border-white/15'"
                type="button"
                role="tab"
                :aria-selected="tab === 'delivery'"
                aria-controls="sms-panel-delivery"
                @click="tab = 'delivery'"
            >
                Delivery Templates
            </button>
        </div>
        </Teleport>

        <section v-show="tab === 'pos'" id="sms-panel-pos" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="sms-tab-pos">
            <form class="flex flex-col gap-3 rounded-2xl border border-mist bg-white p-4 dark:border-white/10 dark:bg-white/5" @submit.prevent="saveTemplate">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="template-name">Name</label>
                    <input
                        id="template-name"
                        v-model="templateForm.name"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                        type="text"
                    />
                    <p v-if="templateForm.errors.name" class="text-sm text-danger">{{ templateForm.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="template-body">Message</label>
                    <textarea
                        id="template-body"
                        v-model="templateForm.body"
                        class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                        required
                    />
                    <p v-if="templateForm.errors.body" class="text-sm text-danger">{{ templateForm.errors.body }}</p>
                </div>
                <div class="flex gap-2">
                    <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam disabled:opacity-70" type="submit" :disabled="templateForm.processing">
                        {{ templateForm.processing ? 'Saving…' : (editingId ? 'Update template' : 'Save template') }}
                    </button>
                    <button v-if="editingId" class="h-10 rounded-xl px-3 text-sm font-medium" type="button" @click="resetTemplateForm">
                        Cancel
                    </button>
                </div>
            </form>
            <p v-if="props.templates.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No POS templates yet.</p>
            <ul v-else class="flex flex-col gap-3">
                <li
                    v-for="template in templates"
                    :key="template.id"
                    class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-4 dark:border-white/10 dark:bg-white/5"
                >
                    <p class="font-medium">{{ template.name }}</p>
                    <p class="text-sm text-ink/70 dark:text-foam/70">{{ template.body }}</p>
                    <div class="flex gap-3">
                        <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="editTemplate(template)">Edit</button>
                        <button class="text-sm font-medium text-danger" type="button" @click="deleteTemplate(template)">Delete</button>
                    </div>
                </li>
            </ul>
        </section>

        <section v-show="tab === 'delivery'" id="sms-panel-delivery" class="flex flex-col gap-4" role="tabpanel" aria-labelledby="sms-tab-delivery">
            <form
                v-for="template in deliveryTemplates"
                :key="template.id"
                class="flex flex-col gap-2 rounded-2xl border border-mist bg-white p-4 dark:border-white/10 dark:bg-white/5"
                @submit.prevent="saveDeliveryTemplate(template)"
            >
                <label class="text-sm font-medium" :for="`delivery-text-${template.id}`">{{ template.name }}</label>
                <textarea
                    :id="`delivery-text-${template.id}`"
                    v-model="template.body"
                    class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm outline-none focus:border-pine dark:border-white/10 dark:bg-white/5"
                    required
                />
                <button class="h-10 w-fit rounded-xl bg-pine px-3 text-sm font-medium text-foam" type="submit">Save</button>
            </form>
        </section>
    </div>
</template>
