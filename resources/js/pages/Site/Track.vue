<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import InstructionPhoto from '../../components/InstructionPhoto.vue';
import SiteLayout from '../../layouts/SiteLayout.vue';

defineOptions({
    layout: SiteLayout,
});

const props = defineProps({
    order: {
        type: Object,
        default: null,
    },
    statuses: {
        type: Array,
        required: true,
    },
    placed: {
        type: Boolean,
        default: false,
    },
    textNotice: {
        type: String,
        default: null,
    },
    number: {
        type: String,
        default: '',
    },
    missing: {
        type: Boolean,
        default: false,
    },
});

const number = ref(props.number);
const currentIndex = computed(() => props.statuses.findIndex((step) => step.value === props.order?.status));
const shopHref = computed(() => (props.order?.branch_id ? `/?branch=${props.order.branch_id}` : '/'));

let timer = null;

function watchOrder() {
    window.clearInterval(timer);
    timer = null;

    if (!props.order) {
        return;
    }

    timer = window.setInterval(() => {
        if (document.hidden) {
            return;
        }

        router.reload({
            only: ['order', 'missing', 'number'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 8000);
}

watch(() => props.order?.number, watchOrder, { immediate: true });

onUnmounted(() => {
    window.clearInterval(timer);
});

function lookup() {
    router.get('/track', { number: number.value.trim() }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head :title="placed ? 'Order placed' : 'Track order'" />

    <main class="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-6">
        <Link :href="shopHref" class="inline-flex w-fit items-center gap-1 text-sm font-medium text-navy">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M12.5 4.5 7 10l5.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Back to Shop
        </Link>

        <form class="flex items-start gap-2" @submit.prevent="lookup">
            <div class="min-w-0 flex-1">
                <label class="sr-only" for="track-number">Order number</label>
                <input id="track-number" v-model="number" autocapitalize="characters" autocomplete="off" class="h-11 w-full rounded-xl border border-black/15 bg-white px-3 text-sm uppercase placeholder:normal-case" placeholder="Order number" type="text" />
                <p v-if="missing" class="mt-2 text-sm text-danger" role="status">We couldn't find that order.</p>
            </div>
            <button class="h-11 shrink-0 rounded-xl bg-navy px-5 text-sm font-medium text-white" type="submit">Track</button>
        </form>

        <template v-if="order">
            <div>
                <p v-if="order.branch" class="text-sm font-medium text-navy">{{ order.branch }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ placed ? 'Order placed' : order.status_label }}</h1>
                <p class="mt-1 text-lg font-semibold text-navy">{{ order.number }}</p>
                <p v-if="textNotice" class="mt-3 text-sm">{{ textNotice }}</p>
            </div>

            <ol class="grid gap-3 sm:grid-cols-4">
                <li v-for="(step, index) in statuses" :key="step.value" class="flex items-center gap-2 sm:flex-col sm:items-start">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="index <= currentIndex ? 'bg-navy' : 'bg-black/15'" />
                    <span class="text-xs" :class="step.value === order.status ? 'font-semibold text-navy' : 'text-black/60'">{{ step.label }}</span>
                </li>
            </ol>

            <section class="flex flex-col gap-2 rounded-2xl border border-black/10 bg-white p-4">
                <h2 class="text-base font-semibold">Delivery</h2>
                <p class="text-sm">{{ order.delivery.name }} · {{ order.delivery.fee_label }}</p>
                <p v-if="order.address" class="text-sm">{{ order.address }}</p>
                <div v-if="order.delivery.instructions || order.delivery.instruction_images?.length" class="flex flex-col gap-3 rounded-xl border border-navy/20 bg-navy/10 px-4 py-3 text-sm" role="status">
                    <p v-if="order.delivery.instructions">{{ order.delivery.instructions }}</p>
                    <InstructionPhoto
                        v-for="(image, index) in order.delivery.instruction_images"
                        :key="image"
                        :src="image"
                        :alt="`Delivery instruction photo ${index + 1}`"
                    />
                </div>
            </section>

            <section class="flex flex-col gap-2 rounded-2xl border border-black/10 bg-white p-4">
                <h2 class="text-base font-semibold">Payment</h2>
                <p class="text-sm">{{ order.payment.name }} · {{ order.payment.fee_label }}</p>
                <div v-if="order.payment.instructions || order.payment.instruction_images?.length" class="flex flex-col gap-3 rounded-xl border border-navy/20 bg-navy/10 px-4 py-3 text-sm" role="status">
                    <p v-if="order.payment.instructions">{{ order.payment.instructions }}</p>
                    <InstructionPhoto
                        v-for="(image, index) in order.payment.instruction_images"
                        :key="image"
                        :src="image"
                        :alt="`Payment instruction photo ${index + 1}`"
                    />
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-2xl border border-black/10 bg-white p-4">
                <h2 class="text-base font-semibold">Your Order</h2>
                <ul class="flex flex-col gap-2">
                    <li v-for="item in order.items" :key="`${item.name}-${item.quantity}`" class="flex items-start justify-between gap-3 text-sm">
                        <span>{{ item.name }} × {{ item.quantity }}</span>
                        <span class="font-medium text-ember">{{ item.total_label }}</span>
                    </li>
                </ul>
                <div class="flex flex-col gap-1 border-t border-black/10 pt-3 text-sm">
                    <p class="flex justify-between"><span>Subtotal</span><span>{{ order.subtotal_label }}</span></p>
                    <p class="flex justify-between"><span>Delivery fee</span><span>{{ order.delivery.fee_label }}</span></p>
                    <p class="flex justify-between"><span>Payment fee</span><span>{{ order.payment.fee_label }}</span></p>
                    <p v-if="order.voucher" class="flex justify-between"><span>Voucher {{ order.voucher }}</span><span>{{ order.discount_label }}</span></p>
                    <p class="flex justify-between pt-1 text-base font-semibold"><span>Total</span><span>{{ order.total_label }}</span></p>
                </div>
            </section>
        </template>
    </main>
</template>
