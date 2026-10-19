<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { jsonRequest } from '../http';
import { showToast } from '../toast';

const DISCLAIMER = 'Do not reply to this message.';
const BODY_MAX = 1600 - `\n\n${DISCLAIMER}`.length;

const page = usePage();
const branchId = computed(() => page.props.branch?.id ?? null);

const menuOpen = ref(false);
const menuTrigger = ref(null);
const menuStyle = ref({});
const panel = ref(null);
const sending = ref(false);
const resendingId = ref(null);
const loading = ref(false);
const number = ref('');
const message = ref('');
const errors = ref({});
const templates = ref([]);
const history = ref([]);
const templateForm = ref({ id: null, name: '', body: '' });
const templateErrors = ref({});

const canSend = computed(() => Boolean(branchId.value) && !sending.value);

function closeMenu() {
    menuOpen.value = false;
}

function toggleMenu() {
    menuOpen.value = !menuOpen.value;

    if (menuOpen.value && menuTrigger.value) {
        const rect = menuTrigger.value.getBoundingClientRect();
        menuStyle.value = {
            top: `${rect.bottom + 8}px`,
            left: `${rect.left}px`,
        };
    }
}

function closePanel() {
    panel.value = null;
}

function fieldError(bag, key) {
    const value = bag[key];

    return Array.isArray(value) ? value[0] : value || '';
}

function openCompose(preset = '') {
    closeMenu();
    errors.value = {};
    if (preset) {
        message.value = preset;
    }
    panel.value = 'compose';
    loadTemplates();
}

function appendTemplate(template) {
    const addition = template.body.trim();

    if (addition === '') {
        return;
    }

    const current = message.value.trimEnd();
    const next = current === '' ? addition : `${current}\n${addition}`;
    message.value = next.slice(0, BODY_MAX);
}

function openTemplates() {
    closeMenu();
    panel.value = 'templates';
    loadTemplates();
}

function openHistory() {
    closeMenu();
    panel.value = 'history';
    loadHistory();
}

async function sendSms() {
    if (!canSend.value) {
        return;
    }

    sending.value = true;
    errors.value = {};
    const payload = {
        number: number.value,
        message: message.value,
    };
    panel.value = null;
    showToast('Sending text…');

    try {
        const response = await jsonRequest(`/backoffice/pos/register/${branchId.value}/sms`, {
            method: 'POST',
            body: payload,
        });
        number.value = '';
        message.value = '';
        showToast(response.status || 'Text sent.');
    } catch (error) {
        errors.value = error.errors ?? {};
        panel.value = 'compose';
        showToast(error instanceof Error ? error.message : 'Could not send the text.');
    } finally {
        sending.value = false;
    }
}

async function loadTemplates() {
    loading.value = true;

    try {
        const response = await jsonRequest('/backoffice/pos/sms/templates');
        templates.value = response.templates ?? [];
    } catch (error) {
        showToast(error instanceof Error ? error.message : 'Could not load templates.');
    } finally {
        loading.value = false;
    }
}

async function loadHistory({ silent = false } = {}) {
    if (!silent) {
        loading.value = true;
    }

    try {
        if (!branchId.value) {
            history.value = [];
            return;
        }

        const response = await jsonRequest(`/backoffice/pos/register/${branchId.value}/sms/history`);
        history.value = response.messages ?? [];
    } catch (error) {
        showToast(error instanceof Error ? error.message : 'Could not load history.');
    } finally {
        loading.value = false;
    }
}

function statusClass(status) {
    const value = String(status || '').toLowerCase();

    if (value === 'failed' || value === 'refunded') {
        return 'bg-danger/10 text-danger';
    }

    return 'bg-pine/10 text-pine dark:bg-pine/20 dark:text-sand';
}

async function resendSms(row) {
    if (resendingId.value) {
        return;
    }

    resendingId.value = row.id;
    showToast('Sending text…');

    try {
        const response = await jsonRequest(`/backoffice/pos/sms/${row.id}/resend`, {
            method: 'POST',
        });
        showToast(response.status || 'Text resent.');
        await loadHistory({ silent: true });
    } catch (error) {
        showToast(error instanceof Error ? error.message : 'Could not resend the text.');
    } finally {
        resendingId.value = null;
    }
}

function editTemplate(template) {
    templateForm.value = { id: template.id, name: template.name, body: template.body };
    templateErrors.value = {};
}

function resetTemplateForm() {
    templateForm.value = { id: null, name: '', body: '' };
    templateErrors.value = {};
}

async function saveTemplate() {
    templateErrors.value = {};
    const editingId = templateForm.value.id;
    const path = editingId
        ? `/backoffice/pos/sms/templates/${editingId}`
        : '/backoffice/pos/sms/templates';

    try {
        const response = await jsonRequest(path, {
            method: editingId ? 'PUT' : 'POST',
            body: {
                name: templateForm.value.name,
                body: templateForm.value.body,
            },
        });
        resetTemplateForm();
        showToast(response.status);
        await loadTemplates();
    } catch (error) {
        templateErrors.value = error.errors ?? {};
        showToast(error instanceof Error ? error.message : 'Could not save the template.');
    }
}

async function deleteTemplate(template) {
    if (!window.confirm(`Delete ${template.name}?`)) {
        return;
    }

    try {
        const response = await jsonRequest(`/backoffice/pos/sms/templates/${template.id}`, {
            method: 'DELETE',
        });
        if (templateForm.value.id === template.id) {
            resetTemplateForm();
        }
        showToast(response.status);
        await loadTemplates();
    } catch (error) {
        showToast(error instanceof Error ? error.message : 'Could not delete the template.');
    }
}

function useTemplate(template) {
    openCompose(template.body);
}
</script>

<template>
    <div ref="menuTrigger" class="relative flex shrink-0">
        <button
            class="h-10 rounded-l-xl bg-pine px-3 text-sm font-medium whitespace-nowrap text-foam disabled:opacity-70"
            type="button"
            :disabled="sending"
            @click="openCompose()"
        >
            {{ sending ? 'Sending…' : 'Send Text' }}
        </button>
        <button
            class="flex h-10 w-10 items-center justify-center rounded-r-xl border-l border-foam/20 bg-pine text-foam"
            type="button"
            :aria-expanded="menuOpen"
            aria-label="SMS options"
            @click="toggleMenu"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>
    </div>

    <Teleport to="body">
        <div v-show="menuOpen" class="fixed inset-0 z-40" @click="closeMenu" />
        <div
            v-show="menuOpen"
            class="fixed z-50 flex w-48 flex-col overflow-hidden rounded-2xl border border-mist bg-foam py-1 shadow-lg dark:border-white/10 dark:bg-ink"
            :style="menuStyle"
        >
            <button
                class="px-4 py-2.5 text-left text-sm font-medium hover:bg-mist/70 dark:hover:bg-white/10"
                type="button"
                @click="openTemplates"
            >
                SMS Templates
            </button>
            <button
                class="px-4 py-2.5 text-left text-sm font-medium hover:bg-mist/70 dark:hover:bg-white/10"
                type="button"
                @click="openHistory"
            >
                SMS History
            </button>
        </div>

    <div v-if="panel === 'compose'" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[90vh] w-full max-w-md flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">Send Text</h2>
                <button class="text-sm font-medium" type="button" @click="closePanel">Close</button>
            </div>
            <form class="flex min-h-0 flex-col gap-4 overflow-y-auto" @submit.prevent="sendSms">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="sms-number">Mobile number</label>
                    <input
                        id="sms-number"
                        v-model="number"
                        class="h-11 rounded-xl border border-mist bg-white px-4 text-sm dark:border-white/10 dark:bg-white/5"
                        inputmode="tel"
                        required
                        type="tel"
                        placeholder="09171234567"
                    />
                    <p v-if="fieldError(errors, 'number')" class="text-sm text-danger">{{ fieldError(errors, 'number') }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium">Templates</p>
                    <p v-if="loading && templates.length === 0" class="text-sm text-ink/60 dark:text-foam/60">Loading templates…</p>
                    <p v-else-if="templates.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No templates yet.</p>
                    <div v-else class="flex flex-wrap gap-2">
                        <button
                            v-for="template in templates"
                            :key="template.id"
                            class="h-9 rounded-xl border border-mist px-3 text-sm font-medium hover:bg-mist/70 dark:border-white/15 dark:hover:bg-white/10"
                            type="button"
                            @click="appendTemplate(template)"
                        >
                            {{ template.name }}
                        </button>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="sms-message">Message</label>
                    <textarea
                        id="sms-message"
                        v-model="message"
                        class="min-h-28 rounded-xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5"
                        :maxlength="BODY_MAX"
                        required
                    />
                    <p class="text-xs text-ink/50 dark:text-foam/50">{{ DISCLAIMER }} will be added at the end.</p>
                    <p v-if="fieldError(errors, 'message')" class="text-sm text-danger">{{ fieldError(errors, 'message') }}</p>
                </div>
                <p v-if="fieldError(errors, 'status')" class="text-sm text-danger">{{ fieldError(errors, 'status') }}</p>
                <button
                    class="h-11 rounded-xl bg-pine text-sm font-medium text-foam disabled:opacity-70"
                    type="submit"
                    :disabled="sending"
                >
                    {{ sending ? 'Sending…' : 'Send' }}
                </button>
            </form>
        </div>
    </div>

    <div v-if="panel === 'templates'" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex max-h-[80vh] w-full max-w-lg flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-medium">SMS Templates</h2>
                <button class="text-sm font-medium" type="button" @click="closePanel">Close</button>
            </div>
            <form class="flex flex-col gap-3" @submit.prevent="saveTemplate">
                <input
                    v-model="templateForm.name"
                    class="h-11 rounded-xl border border-mist bg-white px-4 text-sm dark:border-white/10 dark:bg-white/5"
                    placeholder="Template name"
                    required
                    type="text"
                />
                <textarea
                    v-model="templateForm.body"
                    class="min-h-24 rounded-xl border border-mist bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5"
                    :maxlength="BODY_MAX"
                    placeholder="Message"
                    required
                />
                <p v-if="fieldError(templateErrors, 'name')" class="text-sm text-danger">{{ fieldError(templateErrors, 'name') }}</p>
                <p v-if="fieldError(templateErrors, 'body')" class="text-sm text-danger">{{ fieldError(templateErrors, 'body') }}</p>
                <div class="flex gap-2">
                    <button class="h-10 rounded-xl bg-pine px-4 text-sm font-medium text-foam" type="submit">
                        {{ templateForm.id ? 'Update template' : 'Save template' }}
                    </button>
                    <button
                        v-if="templateForm.id"
                        class="h-10 rounded-xl px-3 text-sm font-medium"
                        type="button"
                        @click="resetTemplateForm"
                    >
                        Cancel
                    </button>
                </div>
            </form>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <p v-if="loading" class="text-sm text-ink/60 dark:text-foam/60">Loading…</p>
                <p v-else-if="templates.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No templates yet.</p>
                <ul v-else class="flex flex-col gap-2">
                    <li
                        v-for="template in templates"
                        :key="template.id"
                        class="flex flex-col gap-2 rounded-xl border border-mist px-3 py-3 dark:border-white/10"
                    >
                        <p class="font-medium">{{ template.name }}</p>
                        <p class="text-sm text-ink/60 dark:text-foam/60">{{ template.body }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button class="text-sm font-medium text-pine dark:text-sand" type="button" @click="useTemplate(template)">
                                Use
                            </button>
                            <button class="text-sm font-medium" type="button" @click="editTemplate(template)">Edit</button>
                            <button class="text-sm font-medium text-danger" type="button" @click="deleteTemplate(template)">
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div v-if="panel === 'history'" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 px-4">
        <div class="flex h-[80vh] w-full max-w-lg flex-col gap-4 overflow-hidden rounded-2xl bg-foam p-6 dark:bg-ink">
            <div class="flex shrink-0 items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-medium">SMS History</h2>
                    <p class="text-sm text-ink/55 dark:text-foam/50">Texts sent during this shift.</p>
                </div>
                <button class="text-sm font-medium" type="button" @click="closePanel">Close</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-1">
                <p v-if="loading" class="text-sm text-ink/60 dark:text-foam/60">Loading…</p>
                <p v-else-if="history.length === 0" class="text-sm text-ink/60 dark:text-foam/60">No texts sent during this shift.</p>
                <ul v-else class="flex flex-col gap-2">
                    <li
                        v-for="row in history"
                        :key="row.id"
                        class="flex flex-col gap-1 rounded-xl border border-mist px-3 py-3 text-sm dark:border-white/10"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-col gap-1">
                                <p class="font-medium">{{ row.number }}</p>
                                <span
                                    class="w-fit rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="statusClass(row.status)"
                                >
                                    {{ row.status || 'Sent' }}
                                </span>
                            </div>
                            <button
                                class="shrink-0 text-sm font-medium text-pine disabled:opacity-70 dark:text-sand"
                                type="button"
                                :disabled="Boolean(resendingId)"
                                @click="resendSms(row)"
                            >
                                {{ resendingId === row.id ? 'Sending…' : 'Resend' }}
                            </button>
                        </div>
                        <p class="whitespace-pre-wrap text-ink/70 dark:text-foam/70">{{ row.body }}</p>
                        <p class="text-xs text-ink/50 dark:text-foam/50">
                            {{ row.branch }} · {{ row.sender }} · {{ row.sent_at }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    </Teleport>
</template>
